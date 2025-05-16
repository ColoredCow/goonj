<?php

use Civi\Api4\Group;

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

    // Get Glific Token.
    $token = glific_get_token();
    if (!$token) {
      $this->assign('groups', []);
      $this->assign('mappings', []);
      $this->assign('error', 'Failed to get Glific access token');
      parent::run();
      return;
    }

    // Fetch groups from Glific API.
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
    $glificGroups = $data['data']['groups'] ?? [];

    // Fetch CiviCRM groups and format as [id => title].
    $rawGroups = Group::get(TRUE)
      ->addSelect('id', 'title')
      ->addWhere('is_active', '=', 1)
      ->execute();

    $civicrmGroups = [];
    foreach ($rawGroups as $group) {
      $civicrmGroups[$group['id']] = $group['title'];
    }

    // Handle form submission.
    if (!empty($_POST['add_rule'])) {
      $civiGroupId = (int) $_POST['civicrm_group_id'];
      $glificGroupId = (int) $_POST['glific_group_id'];

      if ($civiGroupId && $glificGroupId) {
        $insertQuery = "INSERT INTO civicrm_glific_group_map (group_id, collection_id, last_sync_date)
                        VALUES (%1, %2, NOW())";
        CRM_Core_DAO::executeQuery($insertQuery, [
          1 => [$civiGroupId, 'Integer'],
          2 => [$glificGroupId, 'Integer'],
        ]);
        $this->assign('success_message', 'New rule added successfully.');
      }
      else {
        $this->assign('error_message', 'Please select both groups.');
      }
    }

    // Fetch existing mappings.
    $sql = "SELECT gm.id, gm.group_id, gm.collection_id, gm.last_sync_date, cg.title AS group_name
            FROM civicrm_glific_group_map gm
            LEFT JOIN civicrm_group cg ON cg.id = gm.group_id";
    $dao = CRM_Core_DAO::executeQuery($sql);

    $mappings = [];
    while ($dao->fetch()) {
      $mappings[] = [
        'id' => $dao->id,
        'group_id' => $dao->group_id,
        'collection_id' => $dao->collection_id,
        'last_sync_date' => $dao->last_sync_date,
        'group_name' => $dao->group_name,
      ];
    }

    // Assign to template.
    $this->assign('groups', $glificGroups);
    $this->assign('mappings', $mappings);
    $this->assign('civicrmGroups', $civicrmGroups);

    parent::run();
  }

}
