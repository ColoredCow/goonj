<?php

use Civi\Api4\MembershipType;

/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */


class CRM_CivirulesActions_Membership_Add extends CRM_CivirulesActions_Generic_Api {

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array $params
   */
  protected function alterApiParameters($params, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();
    // this function could be overridden in subclasses to alter parameters to meet certain criteraia
    $params['contact_id'] = $triggerData->getContactId();
    $params['membership_type_id'] = $action_params['membership_type_id'];
    return $params;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    try {
      $action_params['membership_type_id'] = civicrm_api3('MembershipType', 'getvalue', [
        'return' => 'name',
        'id' => $action_params['membership_type_id'],
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    return $action_params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importActionParameters($action_params = NULL) {
    try {
      $action_params['membership_type_id'] = civicrm_api3('MembershipType', 'getvalue', [
        'return' => 'id',
        'name' => $action_params['membership_type_id'],
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    return parent::importActionParameters($action_params);
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/membership/add', $ruleActionId);
  }

  /**
   * Returns a user-friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function userFriendlyConditionParams() {
    $return = '';
    $params = $this->getActionParameters();
    $type = MembershipType::get(FALSE)
      ->addSelect('name')
      ->addWhere('id', '=', $params['membership_type_id'])
      ->execute()
      ->first()['name'];

    $return .= ts("Type: %1", [1 => $type]);
    return $return;
  }

  /**
   * Method to set the api entity
   *
   * @return string
   */
  protected function getApiEntity() {
    return 'Membership';
  }

  /**
   * Method to set the api action
   *
   * @return string
   */
  protected function getApiAction() {
    return 'create';
  }

}
