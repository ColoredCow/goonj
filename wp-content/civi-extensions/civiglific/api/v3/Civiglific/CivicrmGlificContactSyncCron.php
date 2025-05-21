<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\SubscriptionHistory;
use Civi\Api4\Phone;
use Civi\Api4\GroupContact;
use Civi\Api4\GlificGroupMap;
use CRM\Civiglific\GlificClient;

/**
 * Define API spec.
 */
function _civicrm_api3_civiglific_civicrm_glific_contact_sync_cron_spec(&$spec) {}

/**
 * Cron job to sync contacts between CiviCRM group and Glific group.
 */
function civicrm_api3_civiglific_civicrm_glific_contact_sync_cron($params) {
  $glific = new GlificClient();
  $returnValues = [];

  $batchSize = 50;
  // Sleep time between batches (seconds) to reduce API rate limits and server load.
  $sleepSeconds = 1;

  $glificGroupMaps = GlificGroupMap::get(TRUE)
    ->addSelect('id', 'group_id', 'collection_id', 'last_sync_date')
    ->execute()
    ->jsonSerialize();

  foreach ($glificGroupMaps as $rule) {
    $civiGroupId = $rule['group_id'];
    $glificGroupId = $rule['collection_id'];
    $mapId = $rule['id'];
    $lastSyncDate = $rule['last_sync_date'];

    if (empty($lastSyncDate)) {
      $civiContacts = _getCiviContactsFromGroup($civiGroupId);
    }
    else {
      $civiContacts = _getNewlyAddedCiviContacts($civiGroupId, $lastSyncDate);
    }

    $removedContacts = _getRemovedCiviContacts($civiGroupId, $lastSyncDate);

    if (!empty($removedContacts)) {
      foreach ($removedContacts as $contact) {
        try {
          $normalizedPhone = $contact['phone'];
          $glificId = $glific->getContactIdByPhone($normalizedPhone);

          if ($glificId) {
            $glific->removeFromGroup($glificId, $glificGroupId);
            \Civi::log()->info(
              "Removed contact {$normalizedPhone} (Glific ID {$glificId}) from Glific group {$glificGroupId}"
            );
          }
          else {
            \Civi::log()->warning(
              "Could not find any Glific contact for phone {$normalizedPhone}; skipping removal."
            );
          }
        }
        catch (Exception $e) {
          \Civi::log()->error("Error removing contact {$contact['phone']} from Glific: " . $e->getMessage());
        }
      }
    }

    if (empty($civiContacts)) {
      continue;
    }

    $glificPhones = $glific->getContactsInGroup($glificGroupId);

    $civiPhones = array_column($civiContacts, 'phone');

    $totalContacts = count($civiContacts);
    for ($offset = 0; $offset < $totalContacts; $offset += $batchSize) {
      $batch = array_slice($civiContacts, $offset, $batchSize);

      foreach ($batch as $contact) {
        try {
          if (!in_array($contact['phone'], $glificPhones)) {
            $glificId = $glific->createContact($contact['name'], $contact['phone']);

            if ($glificId) {
              $glific->optinContact($contact['phone'], $contact['name']);

              $glific->addToGroup($glificId, $glificGroupId);
              $glificPhones[] = $contact['phone'];
            }
            else {
              \Civi::log()->error("Failed to create Glific contact for phone: {$contact['phone']}");
            }
          }
        }
        catch (Exception $e) {
          \Civi::log()->error("Error syncing contact {$contact['phone']}: " . $e->getMessage());
          continue;
        }
      }

      // Sleep to avoid hitting rate limits or causing timeouts.
      sleep($sleepSeconds);
    }

    GlificGroupMap::update()
      ->addValue('last_sync_date', date('Y-m-d H:i:s'))
      ->addWhere('id', '=', $mapId)
      ->execute();
  }

  return civicrm_api3_create_success($returnValues, $params, 'Civiglific', 'civicrm_glific_contact_sync_cron');
}

/**
 * Get newly added contacts to a CiviCRM group since last sync.
 */
function _getNewlyAddedCiviContacts($groupId, $lastSyncDate) {
  $result = [];

  $subscriptions = SubscriptionHistory::get(TRUE)
    ->addSelect('contact_id', 'date')
    ->addWhere('group_id', '=', $groupId)
    ->addWhere('status', '=', 'Added')
    ->addWhere('date', '>=', $lastSyncDate)
    ->execute();

  foreach ($subscriptions as $sub) {
    $contactId = $sub['contact_id'];

    $phones = Phone::get(TRUE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute();

    $contact = Contact::get(FALSE)
      ->addSelect('display_name')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    $displayName = $contact ? $contact['display_name'] : NULL;
    $phoneNumber = count($phones) ? $phones[0]['phone'] : NULL;

    if ($phoneNumber) {
      $result[] = [
        'name' => $displayName,
        'phone' => _normalizePhone($phoneNumber),
      ];
    }
  }

  return $result;
}

/**
 * Get contacts from a CiviCRM group.
 */
function _getCiviContactsFromGroup($groupId) {
  $result = [];

  $groupContacts = GroupContact::get(TRUE)
    ->addSelect('contact_id', 'contact_id.display_name')
    ->addWhere('group_id', '=', $groupId)
    ->addWhere('status', '=', 'Added')
    ->execute();

  foreach ($groupContacts as $gc) {
    $contactId = $gc['contact_id'];
    $displayName = $gc['contact_id.display_name'];

    $phones = Phone::get(TRUE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute();

    $phoneNumber = count($phones) ? $phones[0]['phone'] : NULL;

    $result[] = [
      'name' => $displayName,
      'phone' => _normalizePhone($phoneNumber),
    ];
  }

  return $result;
}

/**
 * Normalize phone numbers.
 */
function _normalizePhone($phone) {
  return preg_replace('/\D+/', '', $phone);
}

/**
 *
 */
function _getRemovedCiviContacts($groupId, $lastSyncDate) {
  $result = [];

  $removedSubs = SubscriptionHistory::get(TRUE)
    ->addSelect('contact_id', 'date')
    ->addWhere('group_id', '=', $groupId)
    ->addWhere('status', '=', 'Removed')
    ->addWhere('date', '>=', $lastSyncDate)
    ->execute();

  foreach ($removedSubs as $sub) {
    $contactId = $sub['contact_id'];

    $phones = Phone::get(TRUE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute();

    $phoneNumber = count($phones) ? $phones[0]['phone'] : NULL;

    if ($phoneNumber) {
      $result[] = [
        'contact_id' => $contactId,
        'phone' => _normalizePhone($phoneNumber),
      ];
    }
  }

  return $result;
}
