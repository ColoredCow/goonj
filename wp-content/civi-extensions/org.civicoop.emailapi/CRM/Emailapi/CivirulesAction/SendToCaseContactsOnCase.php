<?php
/**
 * @author Hitesh Jain <hitesh@compuco.io>
 * @license AGPL-3.0
 */
use CRM_Emailapi_ExtensionUtil as E;

class CRM_Emailapi_CivirulesAction_SendToCaseContactsOnCase extends CRM_Civirules_Action {

  protected static $alreadySend = [];

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $actionParams = $this->getActionParameters();
    $case = $triggerData->getEntityData('Case');
    $actionParams['case_id'] = $case['id'];

    // Find Case contact(s)
    $related_contacts = $this->getCaseContacts($case['id']);
    foreach($related_contacts as $related_contact_id) {
      // Make sure an email is only send once for a case.
      if (isset(self::$alreadySend[$case['id']][$related_contact_id])) {
        continue;
      }
      $params = $actionParams;
      $params['contact_id'] = $related_contact_id;

      // change email address if other location type is used, falling back on primary if set
      if (!empty($actionParameters['location_type_id'])) {
        $parameters['location_type_id'] = $actionParameters['location_type_id'];
      }
      $extra_data = (array) $triggerData;
      $params['extra_data'] = array_change_key_case($extra_data["\0CRM_Civirules_TriggerData_TriggerData\0entity_data"], CASE_LOWER);
      foreach ($params['extra_data'] as $entity => $values) {
        if (isset($values['id']) && $entity !== 'contact') {
          $params["{$entity}_id"] = $values['id'];
        }
      }
      //execute the action
      civicrm_api3('Email', 'send', $params);

      self::$alreadySend[$case['id']][$related_contact_id] = true;
    }
  }

  /**
   * Fetch all the case contacts for particular case id.
   *
   * @param string $caseId
   *   Case id.
   *
   * @return array
   *   Case contacts results.
   */
  protected function getCaseContacts($caseId) {
    $contacts = [];
    $caseContacts = civicrm_api4('CaseContact', 'get', [
      'where' => [
        ['case_id', '=', $caseId],
      ],
      'checkPermissions' => FALSE,
    ]);

    foreach ($caseContacts as $caseContact) {
      $contacts[] = $caseContact['contact_id'];
    }
    return $contacts;
  }

  public static function getRelationshipTypes() {
    $return = CRM_Emailapi_CivirulesAction_SendToRelatedContact::getRelationshipTypes('a_b');
    return $return;
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirules/actions/emailapi_rolesoncase', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $template = 'unknown template';
    $params = $this->getActionParameters();
    $messageTemplates = new CRM_Core_DAO_MessageTemplate();
    $messageTemplates->id = $params['template_id'];
    $messageTemplates->is_active = true;
    if ($messageTemplates->find(TRUE)) {
      $template = $messageTemplates->msg_title;
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
    $to = '';
    $relationship_types = self::getRelationshipTypes();
    if (isset($relationship_types[$params['relationship_type']])) {
      $to .= " with role: '".$relationship_types[$params['relationship_type']]."'";
    }

    $cc = "";
    if (!empty($params['cc'])) {
      $cc = ts(' and cc to %1', [1=>$params['cc']]);
    }
    $bcc = "";
    if (!empty($params['bcc'])) {
      $bcc = ts(' and bcc to %1', [1=>$params['bcc']]);
    }
    return ts('Send email from "%1 (%2 using %3)" with Template "%4" to %5 %6 %7', [
      1=>$params['from_name'],
      2=>$params['from_email'],
      3=>$locationText,
      4=>$template,
      5 => $to,
      6 => $cc,
      7 => $bcc
    ]);
  }

  /**
   * This function validates whether this action works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether an action is possible in the current setup.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    if ($trigger->doesProvideEntity('Case')) {
      return true;
    }
    return false;
  }
}
