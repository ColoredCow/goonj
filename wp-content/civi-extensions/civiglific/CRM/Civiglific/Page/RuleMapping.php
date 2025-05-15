<?php

require_once __DIR__ . '/../Helper.php';

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
    $query = json_encode([
      'query' => 'query { groups { id label } }',
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Authorization: ' . $token,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      CRM_Core_Error::debug_log_message('Glific API cURL error: ' . curl_error($ch));
    }

    curl_close($ch);

    $data = json_decode($response, TRUE);
    $groups = $data['data']['groups'] ?? [];

    $this->assign('groups', $groups);
    parent::run();
  }

}
