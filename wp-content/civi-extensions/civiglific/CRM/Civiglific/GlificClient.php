<?php

namespace CRM\Civiglific;

use GuzzleHttp\Client;
use CRM\Civiglific\GlificHelper;

/**
 * GlificClient
 * Acts as the API interface for everything Glific.
 */
if (!class_exists('CRM\Civiglific\GlificClient')) {
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

  /**
   * Updates a contact's name in Glific.
   *
   * @param string $contactId
   *   The Glific contact ID.
   * @param string $name
   *   The new name for the contact.
   *
   * @return string|null The updated contact ID on success, or null on failure.
   */
  public function updateContact($contactId, $name) {
    $query = '
      mutation($id: ID!, $input: ContactInput!) {
        updateContact(id: $id, input: $input) {
          contact {
            id
            name
            phone
          }
          errors {
            key
            message
          }
        }
      }
    ';
    $variables = [
      'id' => $contactId,
      'input' => [
        'name' => $name,
      ],
    ];
    $response = $this->query($query, $variables);

    return $response['data']['updateContact']['contact']['id'];
  }

  /**
   * Gets a contact by ID from Glific.
   *
   * @param string $contactId
   *   The Glific contact ID.
   *
   * @return array The contact data (id, name, phone) or an empty array on failure.
   */
  public function getContactById($contactId) {
    $query = <<<'GQL'
      query GetContact($id: ID!) {
        contact(id: $id) {
          contact {
            id
            name
            phone
          }
          errors {
            key
            message
          }
        }
      }
    GQL;
    $variables = ['id' => $contactId];
    $response = $this->query($query, $variables);

    return $response['data']['contact']['contact'] ?? [];
  }

  /**
   * Uploads a media message (e.g., PDF) to Glific.
   *
   * @param string $url
   *   Public URL of the media file.
   * @param string|null $sourceUrl
   *   Optional source URL (defaults to $url).
   *
   * @return array|null
   *   Returns messageMedia { id, url } or null on failure.
   */
  public function createMessageMedia($url, $sourceUrl = NULL) {
    $query = '
      mutation($input: MessageMediaInput!) {
        createMessageMedia(input:$input) {
          messageMedia {
            id
            url
          }
          errors {
            key
            message
          }
        }
      }
    ';

    $variables = [
      'input' => [
        'url' => $url,
        'source_url' => $sourceUrl ?? $url,
      ],
    ];

    $response = $this->query($query, $variables);

    if (!empty($response['data']['createMessageMedia']['errors'])) {
      \Civi::log()->error("Glific createMessageMedia error: " . json_encode($response['data']['createMessageMedia']['errors']));
      return NULL;
    }

    return $response['data']['createMessageMedia']['messageMedia'] ?? NULL;
  }

  /**
   * Sends a message via Glific using template + media.
   *
   * @param string $receiverId
   *   The Glific contact ID (receiver).
   * @param string $mediaId
   *   The uploaded media ID from Glific.
   * @param int $templateId
   *   The approved template ID in Glific.
   * @param array $params
   *   Parameters for the template (e.g., contact name, invoice number).
   *
   * @return array|null
   *   Message response from Glific or null on failure.
   */
  public function sendMessage($receiverId, $mediaId, $templateId, array $params = []) {
    $query = '
      mutation CreateAndSendMessage($input: MessageInput!) {
        createAndSendMessage(input: $input) {
          message {
            id
            templateId
          }
          errors {
            key
            message
          }
        }
      }
    ';

    $variables = [
      'input' => [
        'body' => "",
        'senderId' => 1,
        'receiverId' => (string) $receiverId,
        'flow' => 'OUTBOUND',
        'type' => 'DOCUMENT',
        'mediaId' => (string) $mediaId,
        'isHsm' => true,
        'templateId' => (int) $templateId,
        'params' => array_values($params),
      ],
    ];

    \Civi::log()->info("Glific payload", $variables);

    $response = $this->query($query, $variables);

    \Civi::log()->info("Full response", $response );


    if (!empty($response['data']['createAndSendMessage']['errors'])) {
      \Civi::log()->error("Glific sendMessage error: " . json_encode($response['data']['createAndSendMessage']['errors']));
      return NULL;
    }

    return $response['data']['createAndSendMessage']['message'] ?? NULL;
  }


}

}