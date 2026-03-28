#!/usr/bin/env bash
#
# Simulate concurrent Razorpay subscription.charged webhook calls
# to reproduce the race condition in generateInvoiceNumber().
#
# Usage:
#   ./scripts/simulate-concurrent-webhooks.sh <BASE_URL> <PROCESSOR_ID> <WEBHOOK_SECRET> [CONCURRENCY]
#
# Example:
#   ./scripts/simulate-concurrent-webhooks.sh https://staging-crm.goonj.org 7 "" 5
#
# Arguments:
#   BASE_URL       - CiviCRM site URL (e.g., https://staging-crm.goonj.org)
#   PROCESSOR_ID   - Razorpay payment processor ID (check civicrm_payment_processor table)
#   WEBHOOK_SECRET - Razorpay webhook secret (empty string "" to skip signature)
#   CONCURRENCY    - Number of simultaneous webhook calls (default: 5)
#
# Prerequisites:
#   - curl, jq installed
#   - Valid subscription IDs that exist in civicrm_contribution_recur.processor_id
#   - The subscriptions must have is_test matching the payment processor's test mode
#
# What this does:
#   1. Generates N unique webhook payloads (each with a unique payment ID)
#   2. Fires all N requests simultaneously using background curl processes
#   3. Waits for all to complete
#   4. Reports which succeeded and which failed
#
# The race condition occurs when multiple requests simultaneously:
#   - Create a Contribution via API3
#   - Trigger hook_civicrm_post
#   - Enter generateInvoiceNumber()
#   - Call Contribution::update() inside the hook (re-entry)
#   - Hit CRM_Utils_Cache_SqlGroup->set() with a duplicate INSERT
#   - Catch block calls ROLLBACK, potentially undoing the contribution
#   - Receipt sending then fails with "Contribution does not exist"

set -euo pipefail

BASE_URL="${1:?Usage: $0 <BASE_URL> <PROCESSOR_ID> <WEBHOOK_SECRET> [CONCURRENCY]}"
PROCESSOR_ID="${2:?Missing PROCESSOR_ID}"
WEBHOOK_SECRET="${3:-}"
CONCURRENCY="${4:-5}"

IPN_URL="${BASE_URL}/?civiwp=CiviCRM&q=civicrm/payment/ipn&processor_id=${PROCESSOR_ID}"

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

echo "============================================"
echo "Concurrent Webhook Race Condition Simulator"
echo "============================================"
echo ""
echo "Target:      ${IPN_URL}"
echo "Concurrency: ${CONCURRENCY}"
echo "Signature:   ${WEBHOOK_SECRET:+(set)}${WEBHOOK_SECRET:-(none, skipping validation)}"
echo ""

# --------------------------------------------------------------------------
# You must provide real subscription IDs that exist in your CiviCRM database.
# These are looked up via: civicrm_contribution_recur.processor_id = <sub_id>
#
# Replace these with your actual test subscription IDs:
# --------------------------------------------------------------------------
SUBSCRIPTION_IDS=(
  "sub_SWb3vsfqGWbLeu"
  "sub_SWb4MYSXJDKlnx"
)

if [ ${#SUBSCRIPTION_IDS[@]} -lt 2 ]; then
  echo "ERROR: Need at least 2 subscription IDs. Edit the script to add them."
  exit 1
fi

# Generate a unique payment ID (simulates Razorpay's pay_XXXXX format)
generate_payment_id() {
  echo "pay_test_$(date +%s%N)_${1}"
}

# Compute HMAC-SHA256 signature if webhook secret is set
compute_signature() {
  local body="$1"
  local secret="$2"
  echo -n "$body" | openssl dgst -sha256 -hmac "$secret" -binary | xxd -p -c 256
}

# Build a subscription.charged webhook payload
build_payload() {
  local sub_id="$1"
  local pay_id="$2"
  local amount_paise="${3:-100000}" # Default Rs 1,000 = 100000 paise

  cat <<EOF
{
  "entity": "event",
  "account_id": "acc_test",
  "event": "subscription.charged",
  "contains": ["subscription", "payment"],
  "payload": {
    "subscription": {
      "entity": {
        "id": "${sub_id}",
        "plan_id": "plan_test",
        "status": "active",
        "current_start": null,
        "current_end": null,
        "notes": {
          "identity_type": "",
          "purpose": "Team 5000"
        }
      }
    },
    "payment": {
      "entity": {
        "id": "${pay_id}",
        "amount": ${amount_paise},
        "currency": "INR",
        "status": "captured",
        "method": "card"
      }
    }
  }
}
EOF
}

# --------------------------------------------------------------------------
# Step 1: Generate payloads
# --------------------------------------------------------------------------
echo "Generating ${CONCURRENCY} webhook payloads..."
echo ""

for i in $(seq 1 "$CONCURRENCY"); do
  # Round-robin across available subscription IDs
  sub_idx=$(( (i - 1) % ${#SUBSCRIPTION_IDS[@]} ))
  sub_id="${SUBSCRIPTION_IDS[$sub_idx]}"
  pay_id=$(generate_payment_id "$i")

  payload=$(build_payload "$sub_id" "$pay_id")
  printf '%s' "$payload" > "${TMPDIR}/payload_${i}.json"

  echo "  [${i}] sub=${sub_id} pay=${pay_id}"
done

echo ""

# --------------------------------------------------------------------------
# Step 2: Fire all requests simultaneously
# --------------------------------------------------------------------------
echo "Firing ${CONCURRENCY} concurrent POST requests..."
echo ""

for i in $(seq 1 "$CONCURRENCY"); do
  payload_file="${TMPDIR}/payload_${i}.json"

  # Build curl args — use @file to send exact bytes, matching what we sign
  curl_args=(
    -s -o "${TMPDIR}/response_${i}.txt"
    -w "%{http_code}"
    --max-time 30
    -X POST
    -H "Content-Type: application/json"
  )

  # Add signature header if secret is provided
  if [ -n "$WEBHOOK_SECRET" ]; then
    sig=$(compute_signature "$(cat "$payload_file")" "$WEBHOOK_SECRET")
    curl_args+=(-H "X-Razorpay-Signature: ${sig}")
  fi

  curl_args+=(--data-binary "@${payload_file}" "$IPN_URL")

  # Run in background, save HTTP status code
  (
    http_code=$(curl "${curl_args[@]}" 2>/dev/null || echo "000")
    echo "$http_code" > "${TMPDIR}/status_${i}.txt"
  ) &
done

# --------------------------------------------------------------------------
# Step 3: Wait for all requests to complete
# --------------------------------------------------------------------------
echo "Waiting for all requests to complete..."
wait
echo ""

# --------------------------------------------------------------------------
# Step 4: Report results
# --------------------------------------------------------------------------
echo "============================================"
echo "Results"
echo "============================================"
echo ""

success=0
failure=0

for i in $(seq 1 "$CONCURRENCY"); do
  status=$(cat "${TMPDIR}/status_${i}.txt" 2>/dev/null || echo "???")
  response=$(cat "${TMPDIR}/response_${i}.txt" 2>/dev/null | head -c 200 || echo "(no response)")

  if [ "$status" = "200" ]; then
    echo "  [${i}] HTTP ${status} - OK"
    ((success++)) || true
  else
    echo "  [${i}] HTTP ${status} - FAILED"
    echo "        Response: ${response}"
    ((failure++)) || true
  fi
done

echo ""
echo "--------------------------------------------"
echo "Success: ${success} / ${CONCURRENCY}"
echo "Failed:  ${failure} / ${CONCURRENCY}"
echo "--------------------------------------------"
echo ""

if [ "$failure" -gt 0 ]; then
  echo "Some requests failed! Check CiviCRM logs for:"
  echo "  - 'Invoice number generation failed'"
  echo "  - 'DB Error: already exists'"
  echo "  - 'Failed to send receipt for contribution ID'"
  echo "  - 'Contribution does not exist'"
  echo ""
  echo "Log location: check ConfigAndLog directory or wp-content/uploads/civicrm/ConfigAndLog/"
else
  echo "All requests succeeded. The race condition may not have triggered."
  echo ""
  echo "To increase the chance of hitting the race:"
  echo "  1. Increase concurrency:  $0 $BASE_URL $PROCESSOR_ID \"$WEBHOOK_SECRET\" 10"
  echo "  2. Add a temporary sleep(2) in generateInvoiceNumber() after SELECT FOR UPDATE"
  echo "  3. Run this script multiple times in quick succession"
fi
