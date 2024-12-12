<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * Class for CiviRule Condition Emailapi
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Emailapi_CivirulesAction_Send extends CRM_CivirulesActions_Generic_Api {

  /**
   * Method to get the api entity to process in this CiviRule action
   *
   * @access protected
   * @abstract
   */
  protected function getApiEntity() {
    return 'Email';
  }

  /**
   * Method to get the api action to process in this CiviRule action
   *
   * @access protected
   * @abstract
   */
  protected function getApiAction() {
    return 'send';
  }

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $parameters
   * @param CRM_Civirules_TriggerData_TriggerData $rtiggerData
   * @return array
   * @access protected
   */
  protected function alterApiParameters($parameters, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    //this method could be overridden in subclasses to alter parameters to meet certain criteria
    $contactId = $triggerData->getContactId();
    $parameters['contact_id'] = $contactId;
    $actionParameters = $this->getActionParameters();
    // change email address if other location type is used, falling back on primary if set
    if (!empty($actionParameters['location_type_id'])) {
      $parameters['location_type_id'] = $actionParameters['location_type_id'];
    }
    if (!empty($actionParameters['alternative_receiver_address'])) {
      $parameters['alternative_receiver_address'] = $actionParameters['alternative_receiver_address'];
    }
    if (!empty($actionParameters['file_on_case'])) {
      $case = $triggerData->getEntityData('Case');
      $parameters['case_id'] = $case['id'];
    }
    if ($triggerData->getEntityData('Activity')) {
      $activity = $triggerData->getEntityData('Activity');
      $parameters['activity_id'] = $activity['id'];
    }
    if (!empty($actionParameters['cc'])) {
      $parameters['cc'] = $actionParameters['cc'];
    }
    if (!empty($actionParameters['bcc'])) {
      $parameters['bcc'] = $actionParameters['bcc'];
    }
    if (!empty($actionParameters['disable_smarty'])) {
      $parameters['disable_smarty'] = $actionParameters['disable_smarty'];
    }
    $extra_data = ((array) $triggerData)["\0CRM_Civirules_TriggerData_TriggerData\0entity_data"] ?? [];
    $parameters['extra_data'] = [];
    foreach ($extra_data as $entityCamelCase => $entityData) {
      // Convert Foo to foo and FooBar to foo_bar
      $entity_snake_case = mb_strtolower(preg_replace(
        '/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/',
        '_', $entityCamelCase));
      // Copy the data to extra_data under the lowercase snake case name key.
      $parameters['extra_data'][$entity_snake_case] = $entityData;
      // For non-contact entities, create a top level ..._id key
      if (isset($entityData['id']) && $entity_snake_case !== 'contact') {
        $parameters[$entity_snake_case . '_id'] = $entityData['id'];
        // Note: CRM_Emailapi_Utils_Tokens will again change this key from
        // foo_bar_id to foo_barId. Despite looking wrong, this is correct
        // in terms of the token processor's needs.
      }
    }
    return $parameters;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   * $access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/civirules/actions/emailapi', 'rule_action_id='.$ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $template = 'unknown template';
    $params = $this->getActionParameters();
    if (empty($params['from_email']) && empty($params['from_name'])) {
      if (!empty($params['from_email_option'])) {
        $fromEmailOptionLabel = \Civi\Api4\OptionValue::get(FALSE)
            ->addSelect('label')
            ->addWhere('value', '=', $params['from_email_option'])
            ->addWhere('option_group_id:name', '=', 'from_email_address')
            ->addWhere('is_active', '=', TRUE)
            ->execute()
            ->first()['label'] ?? E::ts('ERROR - missing from email');
        $fromAddress = htmlspecialchars($fromEmailOptionLabel);
      }
      else {
        $fromAddress = E::ts('Default domain from email address');
      }
    }
    else {
      $fromAddress = htmlspecialchars("\"{$params['from_name']} <{$params['from_email']}>\"");
    }
    $messageTemplates = new CRM_Core_DAO_MessageTemplate();
    $messageTemplates->id = $params['template_id'];
    $messageTemplates->is_active = true;
    if ($messageTemplates->find(TRUE)) {
      $template = "<a href='"
        . CRM_Utils_System::url('civicrm/admin/messageTemplates/add', ['action' => 'update', 'id' => $messageTemplates->id, 'reset' => 1])
        . "'>$messageTemplates->msg_title</a>";
    }
    if (isset($params['location_type_id']) && !empty($params['location_type_id'])) {
      try {
        $locationText = 'location type ' . civicrm_api3('LocationType', 'getvalue', [
            'return' => 'display_name',
            'id' => $params['location_type_id'],
          ]) . ' with primary email address as fall back';
      }
      catch (CRM_Core_Exception $ex) {
        $locationText = 'location type ' . $params['location_type_id'];
      }
    }
    else {
      $locationText = "primary email address";
    }
    $to = E::ts('the contact (using %1)', [1 => $locationText]);
    if (!empty($params['alternative_receiver_address'])) {
      $to = $params['alternative_receiver_address'];
    }
    $cc = "";
    if (!empty($params['cc'])) {
      $cc = E::ts(' and cc to %1', [1=>$params['cc']]);
    }
    $bcc = "";
    if (!empty($params['bcc'])) {
      $bcc = E::ts(' and bcc to %1', [1=>$params['bcc']]);
    }
    return E::ts("Send email from '%1' with Template '%2' to %3 %4 %5", [
      1 => $fromAddress,
      2 => $template,
      3 => $to,
      4 => $cc,
      5 => $bcc
    ]);
  }
  /**
   * alterApiParameters is a protected method, defined by the Civirules
   * extension and as such we cannot make it public. The public method below
   * exposes that function enabling us to have phpunit tests for it.
   */
  public function alterApiParametersForTesting($parameters, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    return $this->alterApiParameters($parameters, $triggerData);
  }
}
