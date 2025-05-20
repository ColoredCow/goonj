<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\SubscriptionHistory;
use CRM\Civiglific\GlificHelper;
use Civi\Api4\Phone;
use Civi\Api4\GroupContact;
use Civi\Api4\GlificGroupMap;
use GuzzleHttp\Client;

/**
 * Define API spec (no parameters).
 */
function _civicrm_api3_civiglific_civicrm_glific_contact_sync_cron_spec(&$spec) {}

/**
 * Cron job to sync contacts between CiviCRM group and Glific group.
 */
function civicrm_api3_civiglific_civicrm_glific_contact_sync_cron($params) {
  $returnValues = [];

  // 1. Fetch all sync rules
  $glificGroupMaps = GlificGroupMap::get(TRUE)
    ->addSelect('id', 'group_id', 'collection_id', 'last_sync_date')
    ->execute()
    ->jsonSerialize();

  foreach ($glificGroupMaps as $rule) {
    $civiGroupId = $rule['group_id'];
    $glificGroupId = $rule['collection_id'];
    $mapId = $rule['id'];
    $lastSyncDate = $rule['last_sync_date'];

    // 1. Get new or all contacts based on sync status
    if (empty($lastSyncDate)) {
      // Initial sync - get all contacts.
      $civiContacts = _getCiviContactsFromGroup($civiGroupId);
    }
    else {
      // Incremental sync - only get newly added contacts.
      $civiContacts = _getNewlyAddedCiviContacts($civiGroupId, $lastSyncDate);
    }

    if (empty($civiContacts)) {
      // Skip if there are no new contacts.
      continue;
    }

    // 3. Get contacts from Glific group
    $glificPhones = _getGlificContactsFromGroup($glificGroupId);

    $civiPhones = array_column($civiContacts, 'phone');
  error_log('civiPhones: ' . print_r($civiPhones, TRUE));


    // 4. Add new contacts to Glific group.
    foreach ($civiContacts as $contact) {
      try {
        if (!in_array($contact['phone'], $glificPhones)) {
          $glificId = _createGlificContact($contact['name'], $contact['phone']);
  error_log('glificId: ' . print_r($glificId, TRUE));

          if ($glificId) {
            // Opt-in contact after creation
            // _optinGlificContact($contact['phone'], $contact['name']);
            _addContactToGlificGroup($glificId, $glificGroupId);
          }
          else {
            \Civi::log()->error("Failed to create Glific contact for phone: {$contact['phone']}");
          }
        }
      }
      catch (Exception $e) {
        \Civi::log()->error("Error syncing contact {$contact['phone']}: " . $e->getMessage());
        // Continue to next contact.
        continue;
      }
    }

    // 6. Update last_sync_date
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

  // 1. Get contacts from SubscriptionHistory with 'Added' status
  $subscriptions = SubscriptionHistory::get(TRUE)
    ->addSelect('contact_id', 'date')
    ->addWhere('group_id', '=', $groupId)
    ->addWhere('status', '=', 'Added')
    ->addWhere('date', '>=', $lastSyncDate)
    ->execute();

  foreach ($subscriptions as $sub) {
    $contactId = $sub['contact_id'];

    // 2. Get display name and primary phone
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
  // Optional: skip removed contacts.
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
 * Get contact phone numbers from a Glific group.
 */
function _getGlificContactsFromGroup($groupId) {
  $query = <<<'GQL'
  query GetGroupContacts($groupId: ID!) {
    group(id: $groupId) {
      group {
        contacts {
          phone
        }
      }
    }
  }
  GQL;

  $variables = ['groupId' => $groupId];

  $response = _glificGraphQLQuery($query, $variables);

  if (
    empty($response['data']['group']['group']['contacts'])
  ) {
    return [];
  }

  $phones = [];
  foreach ($response['data']['group']['group']['contacts'] as $contact) {
    if (!empty($contact['phone'])) {
      $normalized = preg_replace('/\D+/', '', $contact['phone']);
      if ($normalized) {
        $phones[] = $normalized;
      }
    }
  }

  return array_unique($phones);
}

/**
 * Create a new contact in Glific.
 */
function _createGlificContact($name, $phone) {
  $query = '
    mutation($input: ContactInput!) {
      createContact(input: $input) {
        contact { id }
        errors { key message }
      }
    }
  ';

  $variables = [
    'input' => [
      'name' => $name,
      'phone' => $phone,
    ],
  ];

  $response = _glificGraphQLQuery($query, $variables);
  return $response['data']['createContact']['contact']['id'] ?? NULL;
}

/**
 * Add contact to a Glific group.
 */
function _addContactToGlificGroup($contactId, $groupId) {
  $query = '
    mutation($input: ContactGroupInput!) {
      createContactGroup(input: $input) {
        contactGroup { id }
        errors { key message }
      }
    }
  ';

  $variables = [
    'input' => [
      'contactId' => $contactId,
      'groupId' => $groupId,
    ],
  ];

  _glificGraphQLQuery($query, $variables);
}

/**
 * Call Glific GraphQL API using Guzzle.
 */
function _glificGraphQLQuery($query, $variables = []) {
  $client = new Client();

  $url = rtrim(CIVICRM_GLIFIC_API_BASE_URL, '/') . '/api/';
  $token = GlificHelper::getToken();

  try {
    $response = $client->post($url, [
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => $token,
      ],
      'json' => [
        'query' => $query,
        'variables' => $variables,
      ],
    ]);

    return json_decode((string) $response->getBody(), TRUE);
  }
  catch (Exception $e) {
    \Civi::log()->error('Glific API error: ' . $e->getMessage());
    return [];
  }
}

/**
 * Normalize phone numbers.
 */
function _normalizePhone($phone) {
  return preg_replace('/\D+/', '', $phone);
}

// /**
//  * Opt-in a contact in Glific.
//  */
// function _optinGlificContact($phone, $name = NULL) {
//   $query = '
//     mutation optinContact($phone: String!, $name: String) {
//       optinContact(phone: $phone, name: $name) {
//         contact {
//           id
//           phone
//           name
//           optinTime
//         }
//         errors {
//           key
//           message
//         }
//       }
//     }
//   ';

//   $variables = [
//     'phone' => $phone,
//     'name' => $name,
//   ];

//   $response = _glificGraphQLQuery($query, $variables);
//   error_log('respose: ' . print_r($response, TRUE));

//   // Optional: log if opt-in failed.
//   if (!empty($response['data']['optinContact']['errors'])) {
//     \Civi::log()->error("Glific opt-in error for {$phone}: " . json_encode($response['data']['optinContact']['errors']));
//   }

//   return $response['data']['optinContact']['contact']['id'] ?? NULL;
// }
