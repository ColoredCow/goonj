<?php

use GuzzleHttp\Client;
use Civi\Api4\GlificGroupMap;
use Civi\Api4\Group;
use GuzzleHttp\Exception\RequestException;
use CRM\Civiglific\GlificHelper;

/**
 *
 */
class CRM_Civiglific_Page_GroupMapping extends CRM_Core_Page {

  /**
   *
   */
  public function run() {
    CRM_Utils_System::setTitle(ts('Rule Mapping Page'));

    $token = GlificHelper::getToken();
    if (!$token) {
      $this->assign('groups', []);
      $this->assign('mappings', []);
      $this->assign('error', 'Failed to get Glific access token');
      parent::run();
      return;
    }

    $glificGroups = $this->fetchGlificGroups($token);
    $civicrmGroups = $this->fetchCiviGroups();

    if (!empty($_POST['add_rule'])) {
      $this->handleRuleSubmission();
    }

    $mappings = $this->getExistingMappings($civicrmGroups);

    $this->assign('groups', $glificGroups);
    $this->assign('mappings', $mappings);
    $this->assign('civicrmGroups', $civicrmGroups);

    parent::run();
  }

  /**
   *
   */
  protected function fetchGlificGroups($token) {
    $url = rtrim(CIVICRM_GLIFIC_API_BASE_URL, '/') . '/api/';
    $client = new Client();

    try {
      $response = $client->post($url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => $token,
        ],
        'body' => json_encode([
          'query' => 'query { groups { id label } }',
        ]),
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $data['data']['groups'] ?? [];
    }
    catch (RequestException $e) {
      CRM_Core_Error::debug_log_message('Glific API HTTP error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   *
   */
  protected function fetchCiviGroups() {
    $rawGroups = Group::get(TRUE)
      ->addSelect('id', 'title')
      ->addWhere('is_active', '=', 1)
      ->execute();

    $groups = [];
    foreach ($rawGroups as $group) {
      $groups[$group['id']] = $group['title'];
    }

    return $groups;
  }

  /**
   *
   */
  protected function handleRuleSubmission() {
    $civiGroupId = (int) $_POST['civicrm_group_id'];
    $glificGroupId = (int) $_POST['glific_group_id'];

    if ($civiGroupId && $glificGroupId) {
      try {
        GlificGroupMap::create()
          ->addValue('group_id', $civiGroupId)
          ->addValue('collection_id', $glificGroupId)
          ->execute();

        $this->assign('success_message', 'New rule added successfully.');
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message('Error creating Glific Group Map: ' . $e->getMessage());
        $this->assign('error_message', 'Failed to add rule: ' . $e->getMessage());
      }
    }
    else {
      $this->assign('error_message', 'Please select both groups.');
    }
  }

  /**
   *
   */
  protected function getExistingMappings($civicrmGroups) {
    $glificGroupMaps = GlificGroupMap::get(TRUE)
      ->addSelect('id', 'group_id', 'collection_id', 'last_sync_date')
      ->execute();

    $mappings = [];
    foreach ($glificGroupMaps as $map) {
      $groupId = $map['group_id'];
      $groupTitle = $civicrmGroups[$groupId] ?? 'Unknown';

      $mappings[] = [
        'id' => $map['id'],
        'group_id' => $groupId,
        'collection_id' => $map['collection_id'],
        'last_sync_date' => $map['last_sync_date'],
        'group_name' => $groupTitle,
      ];
    }

    return $mappings;
  }

}
