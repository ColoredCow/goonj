<?php

class CRM_CivirulesConditions_Membership_Status extends CRM_CivirulesConditions_Generic_Status {

  /**
   * The entity name (eg. Membership)
   * @return string
   */
  protected function getEntity() {
    return 'Membership';
  }

  /**
   * The entity status field (eg. membership_status_id)
   * @return string
   */
  public function getEntityStatusFieldName() {
    return 'status_id';
  }

  /**
   * Returns an array of statuses as [ id => label ]
   * @param bool $active
   * @param bool $inactive
   *
   * @return array
   */
  public static function getEntityStatusList($active = TRUE, $inactive = FALSE) {
    $return = [];
    $params = [];
    if ($active && !$inactive) {
      $params = ['is_active' => 1];
    }
    elseif ($inactive && !$active) {
      $params = ['is_active' => 0];
    }
    $params['options'] = ['limit' => 0, 'sort' => "label ASC"];

    try {
      $apiMembershipStatus = civicrm_api3("MembershipStatus", "Get", $params)['values'];
      foreach ($apiMembershipStatus as $membershipStatus) {
        $return[$membershipStatus['id']] = $membershipStatus['label'];
      }
    } catch (CRM_Core_Exception $ex) {}
    return $return;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['status_id']) && is_array($params['status_id'])) {
      foreach($params['status_id'] as $i => $j) {
        $params['status_id'][$i] = civicrm_api3('MembershipStatus', 'getvalue', [
          'return' => 'name',
          'id' => $j,
        ]);
      }
    } elseif (!empty($params['status_id'])) {
      try {
        $params['status_id'] = civicrm_api3('MembershipStatus', 'getvalue', [
          'return' => 'name',
          'id' => $params['status_id'],
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return $params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importConditionParameters($condition_params = NULL) {
    if (!empty($condition_params['status_id']) && is_array($condition_params['status_id'])) {
      foreach($condition_params['status_id'] as $i => $j) {
        $condition_params['status_id'][$i] = civicrm_api3('MembershipStatus', 'getvalue', [
          'return' => 'id',
          'name' => $j,
        ]);
      }
    } elseif (!empty($condition_params['status_id'])) {
      try {
        $condition_params['status_id'] = civicrm_api3('MembershipStatus', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['status_id'],
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
