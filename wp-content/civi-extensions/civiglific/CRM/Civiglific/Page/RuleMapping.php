<?php

use Civi\Api4\GlificGroupMap;

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

    // 1. Your existing Glific API cURL fetch code
    $token = glific_get_token();
    $groups = [];
    $error = NULL;
    if (!$token) {
      $error = 'Failed to get Glific access token';
    }
    else {
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
    }

    // 2. Fetch your own table data via API4
    $glificGroupMaps = [];
    try {
      $glificGroupMaps = GlificGroupMap::get()->execute();
    \Civi::log()->debug('wokting herer');
      
    }
    catch (\Exception $e) {
      CRM_Core_Error::debug_log_message('Error fetching GlificGroupMap via API4: ' . $e->getMessage());
    }

    // 3. Assign both datasets and error to the template
    $this->assign('groups', $groups);
    $this->assign('glificGroupMaps', $glificGroupMaps);
    $this->assign('error', $error);

    parent::run();
  }

}
