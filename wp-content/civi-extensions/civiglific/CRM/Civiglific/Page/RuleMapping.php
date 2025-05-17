<?php

require_once __DIR__ . '/../Helper.php';

/**
 *
 */
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 *
 */
class CRM_Civiglific_Page_RuleMapping extends CRM_Core_Page {

  /**
   *
   */
  public function run() {
    CRM_Utils_System::setTitle(ts('Rule Mapping Page'));

    $token = glific_get_token();
    if (!$token) {
      $this->assign('groups', []);
      $this->assign('error', 'Failed to get Glific access token');
      parent::run();
      return;
    }

    $url = rtrim(CIVICRM_GLIFIC_API_BASE_URL, '/') . '/api/';

    $client = new Client();

    try {
      $response = $client->post($url, [
        'headers' => [
          'Content-Type'  => 'application/json',
          'Authorization' => $token,
        ],
        'body' => json_encode([
          'query' => 'query { groups { id label } }',
        ]),
      ]);

      $body = $response->getBody()->getContents();
      $data = json_decode($body, TRUE);
      $groups = $data['data']['groups'] ?? [];

    }
    catch (RequestException $e) {
      CRM_Core_Error::debug_log_message('Glific API HTTP error: ' . $e->getMessage());
      $groups = [];
    }

    $this->assign('groups', $groups);
    parent::run();
  }

}
