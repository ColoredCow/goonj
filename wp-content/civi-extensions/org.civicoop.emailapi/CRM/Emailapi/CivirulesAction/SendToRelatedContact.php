<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_Emailapi_CivirulesAction_SendToRelatedContact extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $actionParams = $this->getActionParameters();
    if (!empty($actionParams['file_on_case'])) {
      $case = $triggerData->getEntityData('Case');
      $actionParams['case_id'] = $case['id'];
    }

    // Find the related contact(s)
    $contactId = $triggerData->getContactId();
    $related_contacts = $this->getRelatedContacts($contactId, $actionParams['relationship_type'], $actionParams['relationship_option']);
    foreach($related_contacts as $related_contact_id) {
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
    }
  }

    /**
     * @param $contact_id
     * @param $relationship_type
     * @param $relationship_option
     *
     * @return array
     * @throws \Civi\Core\Exception\DBQueryException
     */
  protected function getRelatedContacts($contact_id, $relationship_type, $relationship_option) {
    $dir = 'b';
    $inverse_dir = 'a';
    if (stripos($relationship_type, 'b_') === 0) {
      $dir = 'a';
      $inverse_dir = 'b';
    }
    $relationship_type_id = substr($relationship_type, 2);
    $dao = false;
    switch ($relationship_option) {
      case 'all_active':
        $dao = CRM_Core_DAO::executeQuery("
            SELECT contact_id_{$dir} AS contact_id
            FROM civicrm_relationship r
            INNER JOIN civicrm_contact c ON c.id = r.contact_id_{$dir}
            WHERE contact_id_{$inverse_dir} = %1 AND relationship_type_id = %2 AND is_active = 1 AND (start_date IS NULL OR start_date <= CURRENT_DATE()) AND (end_date IS NULL OR end_date >= CURRENT_DATE())
            AND c.is_deleted = 0
        ", [
          1 => [$contact_id, 'Integer'],
          2 => [$relationship_type_id, 'Integer'],
        ]);
        break;
      case 'recent_active':
        $dao = CRM_Core_DAO::executeQuery("
            SELECT contact_id_{$dir} as contact_id, r.id, start_date, (CASE WHEN r.start_date IS NULL THEN 1 ELSE 0 END) AS start_date_not_null
            FROM civicrm_relationship r
            INNER JOIN civicrm_contact c ON c.id = r.contact_id_{$dir}
            WHERE contact_id_{$inverse_dir} = %1 AND relationship_type_id = %2 AND is_active = 1 AND (start_date IS NULL OR start_date <= CURRENT_DATE()) AND (end_date IS NULL OR end_date >= CURRENT_DATE())
            AND c.is_deleted = 0
            ORDER BY start_date_not_null, r.start_date DESC, r.id DESC
            LIMIT 0, 1
        ", [
          1 => [$contact_id, 'Integer'],
          2 => [$relationship_type_id, 'Integer'],
        ]);
        break;
      case 'recent_inactive':
        $dao = CRM_Core_DAO::executeQuery("
            SELECT contact_id_{$dir} as contact_id, r.id, end_date, (CASE WHEN r.end_date IS NULL THEN 1 ELSE 0 END) AS end_date_not_null
            FROM civicrm_relationship r
            INNER JOIN civicrm_contact c ON c.id = r.contact_id_{$dir}
            WHERE contact_id_{$inverse_dir} = %1 AND relationship_type_id = %2 AND is_active = 0
            AND c.is_deleted = 0
            ORDER BY end_date_not_null, r.end_date DESC, r.id DESC
            LIMIT 0, 1
        ", [
          1 => [$contact_id, 'Integer'],
          2 => [$relationship_type_id, 'Integer'],
        ]);
        break;
    }

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
   */
  public static function getRelationshipOptions() {
    return [
      'all_active' => ts('All active related contacts'),
      'recent_active' => ts('The most recent active related contact'),
      'recent_inactive' => ts('The most recent inactive related contact'),
    ];
  }

  /**
   * @param string $dir
   *
   * @return array
   * @throws CRM_Core_Exception
   */
  public static function getRelationshipTypes($dir = 'both') {
    $return = [];
    $relationshipTypes = civicrm_api3('RelationshipType', 'Get', ['is_active' => 1, 'options' => ['limit' => 0]]);
    foreach ($relationshipTypes['values'] as $relationshipType) {
      switch($dir) {
        case 'a_b':
          $return['a_'.$relationshipType['id']] = $relationshipType['label_a_b'];
          break;
        case 'b_a':
          $return['b_'.$relationshipType['id']] = $relationshipType['label_b_a'];
          break;
        case 'both':
          $return['a_'.$relationshipType['id']] = $relationshipType['label_a_b'];
          $return['b_'.$relationshipType['id']] = $relationshipType['label_b_a'];
          break;
      }

    }
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirules/actions/emailapi_relatedcontact', $ruleActionId);
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
    $version = CRM_Core_BAO_Domain::version();
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
    $relationship_options = self::getRelationshipOptions();
    if ($relationship_options[$params['relationship_option']]) {
      $to = $relationship_options[$params['relationship_option']];
    }
    if (isset($relationship_types[$params['relationship_type']])) {
      $to .= " with relationship: '".$relationship_types[$params['relationship_type']]."'";
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
        return E::ts('Send an email to related contact');

      case 'actionParamsHelp':
        return E::ts('<p>This is the form where you can set what is going to happen with the email.</p>
    <p>The first few fields are relatively straightforward: the <strong>From Name</strong> is the name the email will be sent from and the <strong>From Email</strong> is the email address the email will be sent from. Leave these blank to use the configured defaults.</p>
    <p>The <strong>Message Template</strong> is where you select which CiviCRM message template will be used to compose the mail. You can create and edit them in <strong>Administer>Communications>Message Templates</strong></p>
    <p>The next section allows you to manipulate where the email will be sent to.<br/>
    By <strong>default</strong> the email will be sent to the <strong>primary email address of the related contact</strong> in question.<br/>
    You can select which related contacts should receive the email:
    <ul>
      <li><strong>All active related contacts</strong>,</li>
      <li><strong>The most recent active related contact</strong>, this will select the contact with the newest start date of the relationship,</li>
      <li>or <strong>The most recent inactive related contact</strong>, this will select the contact with the latest end date of the relationship</li>
    </ul>
    </p>
      <p>Finally you can specify an email address for the <strong>CC to</strong> (a copy of the email will be sent to this email address and the email address will be visible to the recipient of the email too) or the <strong>BCC to</strong> (a copy of the email will be sent to this email address and the email address will NOT be visible to the recipient of the email too).</p>
      <p>The sending of the email will also lead to an activity (type <em>Email</em>) being recorded for the contact in question, whatever email address will be used.</p>');
    }

    return $helpText ?? '';
  }
}
