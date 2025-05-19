<?php

/**
 * @file
 */

require_once __DIR__ . '/../../../CRM/Civiglific/GlificHelper.php';

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

  error_log('glificgroup:' . print_r($glificGroupMaps, TRUE));

  foreach ($glificGroupMaps as $rule) {
    $civiGroupId = $rule['group_id'];
    $glificGroupId = $rule['collection_id'];
    $mapId = $rule['id'];
    error_log('woking');

    // 2. Get contacts from CiviCRM group
    $civiContacts = _getCiviContactsFromGroup($civiGroupId);
    error_log('civiContacts:' . print_r($civiContacts, TRUE));

    // 3. Get contacts from Glific group
    $glificPhones = _getGlificContactsFromGroup($glificGroupId);
    error_log('glificPhones:' . print_r($glificPhones, TRUE));

    $civiPhones = array_column($civiContacts, 'phone');
    error_log('civiPhones:' . print_r($civiPhones, TRUE));

    // 4. Add new contacts to Glific group.
    foreach ($civiContacts as $contact) {
      if (!in_array($contact['phone'], $glificPhones)) {
        $glificId = _createGlificContact($contact['name'], $contact['phone']);
        if ($glificId) {
          _addContactToGlificGroup($glificId, $glificGroupId);
        }
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
 * Get contacts from a CiviCRM group.
 */
function _getCiviContactsFromGroup($groupId) {
  $result = [];

  error_log('woking contact');

  $groupContacts = GroupContact::get(TRUE)
    ->addSelect('contact_id', 'contact_id.display_name')
    ->addWhere('group_id', '=', $groupId)
  // Optional: skip removed contacts.
    ->addWhere('status', '=', 'Added')
    ->execute();
  error_log('groupContacts:' . print_r($groupContacts, TRUE));

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
  $phones = [];

  $query = '
    query {
      contacts(filter: { groupIds: [' . $groupId . '] }) {
        phone
      }
    }
  ';
  error_log('query:' . print_r($query, TRUE));

  $response = _glificGraphQLQuery($query);

  error_log('responseafter Body:' . print_r($response, TRUE));

  foreach ($response['data']['contacts'] ?? [] as $contact) {
    $phones[] = _normalizePhone($contact['phone']);
  }

  return $phones;
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
 * Helper: Call Glific GraphQL API using Guzzle.
 */
function _glificGraphQLQuery($query, $variables = []) {
  $client = new Client();

  // 🔁 Replace with your Glific API URL
  $url = rtrim(CIVICRM_GLIFIC_API_BASE_URL, '/') . '/api/';
  // 🔁 Replace with your actual token
  $token = glific_get_token();

  error_log('url:' . print_r($url, TRUE));
  error_log('token:' . print_r($token, TRUE));

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
    error_log('response11Body:' . print_r($response->getBody(), TRUE));

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
