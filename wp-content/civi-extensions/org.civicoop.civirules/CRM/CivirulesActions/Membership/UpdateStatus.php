<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>, Sebastian Lisken <sebastian.lisken@civiservice.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Membership_UpdateStatus extends CRM_CivirulesActions_Generic_Api {

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array $params
   * @access protected
   */
  protected function alterApiParameters($params, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();
    //this function could be overridden in subclasses to alter parameters to meet certain criteraia
    $membership = $triggerData->getEntityData('Membership');
    $params['membership_id'] = $membership['id'];
    $params['status_id'] = $action_params['membership_status_id'];
    switch ($params['set_is_override']) {
      case 'set_true':
        $params['is_override'] = true;
        break;
      case 'set_false':
        $params['is_override'] = false;
        break;
    }
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
      $action_params['membership_status_id'] = civicrm_api3('MembershipStatus', 'getvalue', [
        'return' => 'name',
        'id' => $action_params['membership_status_id'],
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
      $action_params['membership_status_id'] = civicrm_api3('MembershipStatus', 'getvalue', [
        'return' => 'id',
        'name' => $action_params['membership_status_id'],
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
   * @return bool|string
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/membership/update_status', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   * @throws \CRM_Core_Exception
   */
  public function userFriendlyConditionParams() {
    $friendlyParams = '';
    $params = $this->getActionParameters();
    if ($params['membership_status_id']) {
      $status = civicrm_api3('MembershipStatus', 'getvalue', array(
        'return' => 'label',
        'id' => $params['membership_status_id']));
      $friendlyParams .= E::ts("Status: %1", array(1 => $status));
    }
    if ($params['set_is_override']) {
      $friendlyParams .= '; ' . E::ts('Status Override?');
      $options = [
      'set_true' => E::ts('set to override permanently'),
      'set_false' => E::ts('set to not override'),
      'dont_set' => E::ts('do not change setting'),
      ];
      $friendlyParams .= ' ' . $options[$params['set_is_override']];
    }
    return $friendlyParams;
  }

  /**
   * Method to set the api entity
   *
   * @return string
   * @access protected
   */
  protected function getApiEntity() {
    return 'Membership';
  }

  /**
   * Method to set the api action
   *
   * @return string
   * @access protected
   */
  protected function getApiAction() {
    return 'update';
  }

  /**
   * Validates whether this action works with the selected trigger.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    $entities = $trigger->getProvidedEntities();
    return isset($entities['Membership']);
  }

}
