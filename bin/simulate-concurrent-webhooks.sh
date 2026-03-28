#!/usr/bin/env bash
#
# simulate-concurrent-webhooks.sh
#
# Simulates concurrent Razorpay subscription.charged webhook calls to test
# how CiviCRM handles multiple recurring payments arriving at the same time.
#
# This was built to reproduce and verify fixes for:
#   - coloredcow-admin/goonj-crm#305 (invoice generation race condition)
#   - coloredcow-admin/goonj-crm#306 (contribution_recur deadlock)
#
# =========================================================================
# USAGE
# =========================================================================
#
#   ./scripts/simulate-concurrent-webhooks.sh \
#       <BASE_URL> <PROCESSOR_ID> <WEBHOOK_SECRET> <SUBSCRIPTION_IDS> [CONCURRENCY]
#
# =========================================================================
# ARGUMENTS
# =========================================================================
#
#   BASE_URL          The CiviCRM site URL.
#                     Example: https://staging-crm.goonj.org
#
#   PROCESSOR_ID      The Razorpay payment processor ID from CiviCRM.
#                     This determines which processor config (API key, webhook
#                     secret) is used to validate the incoming webhook.
#
#                     How to find it:
#                       CiviCRM Admin > Administer > System Settings >
#                       Payment Processors — the ID column shows the number.
#                     Or via API Explorer:
#                       PaymentProcessor::get with select id, name, is_test
#
#                     Note: CiviCRM stores live and test processors as
#                     separate rows. A processor with ID 7 (live) will have
#                     a corresponding test processor (e.g., ID 8). Use the
#                     ID that matches your test contributions' mode.
#
#   WEBHOOK_SECRET    The Razorpay webhook secret used to sign payloads.
#                     The script computes HMAC-SHA256 signatures matching
#                     what Razorpay sends in the X-Razorpay-Signature header.
#
#                     How to find it:
#                       CiviCRM Admin > Administer > System Settings >
#                       Payment Processors > Edit the Razorpay processor —
#                       the "Webhook Secret" field in the Test/Live section.
#
#                     IMPORTANT: The UI may display the secret with different
#                     letter casing than what's stored in the database. Use
#                     the API Explorer to get the exact value:
#                       PaymentProcessor::get with select id, signature
#                     The "signature" field is the webhook secret.
#
#                     Pass an empty string "" to skip signature validation
#                     (only works if the processor has no secret configured).
#
#   SUBSCRIPTION_IDS  Comma-separated list of Razorpay subscription IDs that
#                     exist in civicrm_contribution_recur.processor_id.
#                     At least 2 are needed to simulate concurrent charges
#                     across different subscriptions.
#
#                     Example: "sub_ABC123,sub_DEF456"
#
#                     How to create test subscriptions:
#                       1. Open a contribution page in test-drive mode
#                          (e.g., /contribute/team-5000/)
#                       2. Check "I want to contribute every month"
#                       3. Complete payment with Razorpay test card
#                          (Mastercard 5267 3181 8797 5449, any future
#                          expiry, any CVV, check "Save card")
#                       4. The subscription ID appears in the thank-you
#                          page URL as subscription_id=sub_XXXXX
#                       5. Repeat for a second donor to get two IDs
#
#                     Or find existing ones via API Explorer:
#                       ContributionRecur::get with select id, processor_id
#                       where processor_id IS NOT NULL
#
#   CONCURRENCY       (Optional) Number of simultaneous webhook calls.
#                     Default: 5. Use 20 for more reliable race triggering.
#
# =========================================================================
# EXAMPLES
# =========================================================================
#
#   # 5 concurrent webhooks (default):
#   ./scripts/simulate-concurrent-webhooks.sh \
#       https://staging-crm.goonj.org 7 \
#       "t782RJEJZO+63BJCC+86JdPzuY2dsZzQX3UJeYXVjvY=" \
#       "sub_ABC123,sub_DEF456"
#
#   # 20 concurrent for higher collision probability:
#   ./scripts/simulate-concurrent-webhooks.sh \
#       https://staging-crm.goonj.org 7 \
#       "t782RJEJZO+63BJCC+86JdPzuY2dsZzQX3UJeYXVjvY=" \
#       "sub_ABC123,sub_DEF456" 20
#
#   # Skip signature (only if processor has no secret):
#   ./scripts/simulate-concurrent-webhooks.sh \
#       https://staging-crm.goonj.org 7 "" \
#       "sub_ABC123,sub_DEF456" 10
#
# =========================================================================
# PREREQUISITES
# =========================================================================
#
#   - curl and openssl installed (standard on macOS/Linux)
#   - xxd installed (part of vim, standard on most systems)
#   - Network access to the target CiviCRM instance
#   - Valid subscription IDs in the target database
#
# =========================================================================
# WHAT TO CHECK AFTER RUNNING
# =========================================================================
#
#   Check CiviCRM logs at:
#     wp-content/uploads/civicrm/ConfigAndLog/CiviCRM.*.log
#
#   Look for:
#     - "Invoice number generation failed" + "DB Error: already exists"
#       → Issue #305: cache race in generateInvoiceNumber()
#     - "Failed to process recurring payment" + "DB Error: constraint violation"
#       → Issue #306: deadlock on contribution_recur during Contribution::create
#     - "Failed to send receipt" + "Contribution does not exist"
#       → Issue #305: ROLLBACK wiped the contribution after hook failure
#     - "Retrying after Database deadlock encountered"
#       → Deadlock auto-retried by CiviCRM (may or may not succeed)
#
# =========================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------
BASE_URL="${1:?Usage: $0 <BASE_URL> <PROCESSOR_ID> <WEBHOOK_SECRET> <SUBSCRIPTION_IDS> [CONCURRENCY]}"
PROCESSOR_ID="${2:?Missing PROCESSOR_ID. See script header for how to find it.}"
WEBHOOK_SECRET="${3:-}"
SUBSCRIPTION_IDS_CSV="${4:?Missing SUBSCRIPTION_IDS. Provide comma-separated Razorpay subscription IDs.}"
CONCURRENCY="${5:-5}"

# Parse comma-separated subscription IDs into array
IFS=',' read -ra SUBSCRIPTION_IDS <<< "$SUBSCRIPTION_IDS_CSV"

if [ ${#SUBSCRIPTION_IDS[@]} -lt 2 ]; then
  echo "ERROR: Need at least 2 subscription IDs (got ${#SUBSCRIPTION_IDS[@]})."
  echo "Provide comma-separated IDs: sub_AAA,sub_BBB"
  exit 1
fi

IPN_URL="${BASE_URL}/?civiwp=CiviCRM&q=civicrm/payment/ipn&processor_id=${PROCESSOR_ID}"

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

echo "============================================"
echo "Concurrent Webhook Race Condition Simulator"
echo "============================================"
echo ""
echo "Target:        ${IPN_URL}"
echo "Concurrency:   ${CONCURRENCY}"
echo "Subscriptions: ${SUBSCRIPTION_IDS[*]}"
echo "Signature:     ${WEBHOOK_SECRET:+(set)}${WEBHOOK_SECRET:-(none, skipping validation)}"
echo ""

# ---------------------------------------------------------------------------
# Helper functions
# ---------------------------------------------------------------------------

# Generate a unique payment ID (simulates Razorpay's pay_XXXXX format)
generate_payment_id() {
  echo "pay_test_$(date +%s%N)_${1}"
}

# Compute HMAC-SHA256 signature matching Razorpay's webhook verification.
# See: civirazorpay/lib/razorpay/src/Utility.php verifySignature()
# PHP uses hash_hmac('sha256', $payload, $secret) which returns lowercase hex.
# We replicate this with openssl.
compute_signature() {
  local body="$1"
  local secret="$2"
  echo -n "$body" | openssl dgst -sha256 -hmac "$secret" -binary | xxd -p -c 256
}

# Build a subscription.charged webhook payload matching the structure
# expected by processSubscriptionCharged() in Razorpay.php.
build_payload() {
  local sub_id="$1"
  local pay_id="$2"
  local amount_paise="${3:-100000}" # Default Rs 1,000 = 100000 paise

  cat <<PAYLOAD
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
          "purpose": ""
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
PAYLOAD
}

# ---------------------------------------------------------------------------
# Step 1: Generate payloads
# ---------------------------------------------------------------------------
echo "Generating ${CONCURRENCY} webhook payloads..."
echo ""

for i in $(seq 1 "$CONCURRENCY"); do
  # Round-robin across available subscription IDs
  sub_idx=$(( (i - 1) % ${#SUBSCRIPTION_IDS[@]} ))
  sub_id="${SUBSCRIPTION_IDS[$sub_idx]}"
  pay_id=$(generate_payment_id "$i")

  payload=$(build_payload "$sub_id" "$pay_id")
  # Use printf to avoid trailing newline — curl --data-binary sends exact
  # bytes, and the HMAC signature must match what the server receives.
  printf '%s' "$payload" > "${TMPDIR}/payload_${i}.json"

  echo "  [${i}] sub=${sub_id} pay=${pay_id}"
done

echo ""

# ---------------------------------------------------------------------------
# Step 2: Fire all requests simultaneously
# ---------------------------------------------------------------------------
echo "Firing ${CONCURRENCY} concurrent POST requests..."
echo ""

for i in $(seq 1 "$CONCURRENCY"); do
  payload_file="${TMPDIR}/payload_${i}.json"

  curl_args=(
    -s -o "${TMPDIR}/response_${i}.txt"
    -w "%{http_code}"
    --max-time 60
    -X POST
    -H "Content-Type: application/json"
  )

  # Sign the payload if webhook secret is provided.
  # Uses --data-binary (not -d) to preserve exact bytes matching the signature.
  if [ -n "$WEBHOOK_SECRET" ]; then
    sig=$(compute_signature "$(cat "$payload_file")" "$WEBHOOK_SECRET")
    curl_args+=(-H "X-Razorpay-Signature: ${sig}")
  fi

  curl_args+=(--data-binary "@${payload_file}" "$IPN_URL")

  # Run in background
  (
    http_code=$(curl "${curl_args[@]}" 2>/dev/null || echo "000")
    echo "$http_code" > "${TMPDIR}/status_${i}.txt"
  ) &
done

# ---------------------------------------------------------------------------
# Step 3: Wait for all requests to complete
# ---------------------------------------------------------------------------
echo "Waiting for all requests to complete..."
wait
echo ""

# ---------------------------------------------------------------------------
# Step 4: Report results
# ---------------------------------------------------------------------------
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
  echo "Some requests failed! Check CiviCRM logs for errors."
  echo "See the script header for what to look for."
else
  echo "All requests returned HTTP 200."
  echo ""
  echo "NOTE: HTTP 200 does not guarantee all payments succeeded."
  echo "Errors inside hook_civicrm_post are caught silently."
  echo "Always check the CiviCRM logs to confirm."
  echo ""
  echo "To increase collision probability:"
  echo "  - Increase concurrency to 20"
  echo "  - Run multiple batches back-to-back"
fi
