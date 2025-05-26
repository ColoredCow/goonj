<?php

namespace CRM\Civiglific;

use GuzzleHttp\Client;
use CRM\Civiglific\GlificHelper;

/**
 * GlificClient
 * Acts as the API interface for everything Glific.
 */
class GlificClient {

  protected $client;
  protected $token;
  protected $url;

  public function __construct() {
    $this->client = new Client();
    $this->token = GlificHelper::getToken();
    $this->url = rtrim(CIVICRM_GLIFIC_API_BASE_URL, '/') . '/api/';
  }

  /**
   *
   */
  public function query($query, $variables = []) {
    try {
      $response = $this->client->post($this->url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => $this->token,
        ],
        'json' => [
          'query' => $query,
          'variables' => $variables,
        ],
      ]);

      return json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Glific API error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   *
   */
  public function createContact($name, $phone) {
    $query = '
      mutation($input: ContactInput!) {
        createContact(input: $input) {
          contact { id }
          errors { key message }
        }
      }
    ';
    $variables = ['input' => ['name' => $name, 'phone' => $phone]];
    $response = $this->query($query, $variables);
    return $response['data']['createContact']['contact']['id'] ?? NULL;
  }

  /**
   *
   */
  public function optinContact($phone, $name = NULL) {
    $query = '
      mutation optinContact($phone: String!, $name: String) {
        optinContact(phone: $phone, name: $name) {
          contact {
            id
            phone
            name
            optinTime
          }
          errors {
            key
            message
          }
        }
      }
    ';
    $variables = ['phone' => $phone, 'name' => $name];
    $response = $this->query($query, $variables);
    if (!empty($response['data']['optinContact']['errors'])) {
      \Civi::log()->error("Glific opt-in error for {$phone}: " . json_encode($response['data']['optinContact']['errors']));
    }
    return $response['data']['optinContact']['contact']['id'] ?? NULL;
  }

  /**
   *
   */
  public function addToGroup($contactId, $groupId) {
    $query = '
      mutation($input: ContactGroupInput!) {
        createContactGroup(input: $input) {
          contactGroup { id }
          errors { key message }
        }
      }
    ';
    $variables = ['input' => ['contactId' => $contactId, 'groupId' => $groupId]];
    return $this->query($query, $variables);
  }

  /**
   *
   */
  public function removeFromGroup($contactId, $groupId) {
    $query = '
      mutation updateGroupContacts($input: GroupContactsInput!) {
        updateGroupContacts(input: $input) {
          groupContacts {
            id
            value
            __typename
          }
          numberDeleted
          __typename
        }
      }
    ';
    $variables = [
      'input' => [
        'groupId' => $groupId,
        'addContactIds' => [],
        'deleteContactIds' => [$contactId],
      ],
    ];
    return $this->query($query, $variables);
  }

  /**
   *
   */
  public function getContactsInGroup($groupId) {
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
    $response = $this->query($query, $variables);

    if (empty($response['data']['group']['group']['contacts'])) {
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
   *
   */
  public function getContactIdByPhone($phone) {
    $query = <<<'GQL'
      query GetContactByPhone($phone: String!) {
        contacts(filter: { phone: $phone }) {
          id
          name
          phone
        }
      }
    GQL;

    $variables = ['phone' => $phone];
    $response = $this->query($query, $variables);

    return $response['data']['contacts'][0]['id'] ?? NULL;
  }

}
