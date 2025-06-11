<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */
use CRM_Emailapi_ExtensionUtil as E;

class CRM_Emailapi_CivirulesAction_SendToRolesOnCase extends CRM_Civirules_Action {

  /**
   * @var array
   */
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

    // Find the related contact(s)
    $related_contacts = $this->getRelatedContacts($case['id'], $actionParams['relationship_type']);
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
   * @param $case_id
   * @param $relationship_type
   * @return array
   * @throws \Civi\Core\Exception\DBQueryException
   */
  protected function getRelatedContacts($case_id, $relationship_type) {
    $dir = 'b';
    if (stripos($relationship_type, 'b_') === 0) {
      $dir = 'a';
    }
    $relationship_type_id = substr($relationship_type, 2);
    $sql = "SELECT contact_id_{$dir} AS contact_id
        FROM civicrm_relationship r
        INNER JOIN civicrm_contact c ON c.id = r.contact_id_{$dir}
        WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURRENT_DATE()) AND (end_date IS NULL OR end_date >= CURRENT_DATE())
        AND c.is_deleted = 0
        AND case_id = %1";
    $sqlParams[1] = [$case_id, 'Integer'];
    if ($relationship_type_id) {
      $sql .= " AND relationship_type_id = %2";
      $sqlParams[2] = [$relationship_type_id, 'Integer'];
    }
    $dao = CRM_Core_DAO::executeQuery($sql,$sqlParams);
    $contacts = [];
    if ($dao) {
      while($dao->fetch()) {
        if (!in_array($dao->contact_id, $contacts)) {
          $contacts[] = $dao->contact_id;
        }
      }
    }
    return $contacts;
  }

  /**
   * @return array
   * @throws CRM_Core_Exception
   */
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

  /**
   * Get various types of help text for the action:
   *   - actionDescription: When choosing from a list of actions, explains what the action does.
   *   - actionDescriptionWithParams: When a action has been configured for a rule provides a
   *       user friendly description of the action and params (see $this->userFriendlyConditionParams())
   *   - actionParamsHelp (default): If the action has configurable params, show this help text when configuring
   * @param string $context
   *
   * @return string
   */
  public function getHelpText(string $context): string {
    switch ($context) {
      case 'actionDescriptionWithParams':
        return $this->userFriendlyConditionParams();

      case 'actionDescription':
        return E::ts('Send an email to roles on case');

      case 'actionParamsHelp':
        return E::ts('<p>This is the form where you can set what is going to happen with the email.</p>
    <p>The first few fields are relatively straightforward: the <strong>From Name</strong> is the name the email will be sent from and the <strong>From Email</strong> is the email address the email will be sent from. Leave these blank to use the configured defaults.</p>
    <p>The <strong>Message Template</strong> is where you select which CiviCRM message template will be used to compose the mail. You can create and edit them in <strong>Administer>Communications>Message Templates</strong></p>
    <p>The next section allows you to manipulate where the email will be sent to. By default it will be sent to all people with a role on the case. But you can restrict it by certain roles.<br/>
    By <strong>default</strong> the email will be sent to the <strong>primary email address of the contact</strong> in question.<br/>
    </p>
      <p>Finally you can specify an email address for the <strong>CC to</strong> (a copy of the email will be sent to this email address and the email address will be visible to the recipient of the email too) or the <strong>BCC to</strong> (a copy of the email will be sent to this email address and the email address will NOT be visible to the recipient of the email too).</p>
      <p>The sending of the email will also lead to an activity (type <em>Email</em>) being recorded for the contact in question, whatever email address will be used.</p>');
    }

    return $helpText ?? '';
  }

}
