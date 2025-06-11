<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Emailapi_ExtensionUtil as E;

class CRM_Emailapi_CivirulesAction_SendToContactReference extends CRM_Civirules_Action {

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

    $contactReferenceField = 'custom_' . $actionParams['contact_reference'];
    $entityData = $triggerData->getEntityData($actionParams['entity']);
    // Find the contact reference contact's ID.
    $contactReferenceIds = (array) $entityData[$contactReferenceField];
    // Not all $triggerData contains custom field data, so look it up if necessary.
    if (!$contactReferenceIds) {
      $contactReferenceIds = (array) civicrm_api3(CRM_Core_BAO_CustomGroup::getEntityFromExtends($actionParams['entity']), 'getvalue', [
        'return' => $contactReferenceField,
        'id' => $entityData['id'],
      ]);
    }

    $params = $actionParams;
    foreach ($contactReferenceIds as $contactReferenceId) {
      $params['contact_id'] = $contactReferenceId;
      // change e-mailaddress if other location type is used, falling back on primary if set
      if (!empty($actionParameters['location_type_id'])) {
        $parameters['location_type_id'] = $actionParameters['location_type_id'];
      }
      $extra_data = (array) $triggerData;
      $params['extra_data'] = $extra_data["\0CRM_Civirules_TriggerData_TriggerData\0entity_data"];
      $params = $this->formatExtraData($params);
      //execute the action
      civicrm_api3('Email', 'send', $params);
    }
  }

  /**
   * Copied and slightly modified from CRM_Emailapi_CivirulesAction_Send::alterApiParameters().
   * Without this, the non-contact data doesn't get picked up because the ID is missing and the array key
   * is capitalized.
   */
  private function formatExtraData($parameters) {
    foreach ($parameters['extra_data'] as $entityCamelCase => $entityData) {
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
   * Get a list of entities that use custom fields.
   *
   * @return array
   */
  public static function getContactReferenceEntities() {
    $return[] = '-- please select --';
    $result = \Civi\Api4\CustomField::get(TRUE)
      ->addSelect('custom_group_id.extends')
      ->addClause('OR', ['data_type', '=', 'ContactReference'], ['AND', [['data_type', '=', 'EntityReference'], ['fk_entity', '=', 'Contact']]])
      ->execute()
      ->indexBy('custom_group_id.extends');
    foreach ($result as $field) {
      $return[$field['custom_group_id.extends']] = $field['custom_group_id.extends'];
    }
    $return = array_unique($return);
    asort($return);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirules/actions/emailapi_contactreference', $ruleActionId);
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
    $messageTemplates->is_active = TRUE;
    if ($messageTemplates->find(TRUE)) {
      $template = $messageTemplates->msg_title;
    }
    if (isset($params['location_type_id']) && !empty($params['location_type_id'])) {
      try {
        $locationText = 'location type ' . civicrm_api3('LocationType', 'getvalue', [
          'return' => 'display_name',
          'id' => $params['location_type_id'],
        ]) . ' with primary e-mailaddress as fall back';
      }
      catch (CRM_Core_Exception $ex) {
        $locationText = 'location type ' . $params['location_type_id'];
      }
    }
    else {
      $locationText = "primary e-mailaddress";
    }
    $to = "";
    try {
      $to = civicrm_api3('CustomField', 'getvalue', [
        'return' => "label",
        'id' => $params['contact_reference'],
      ]);
    }
    catch (CRM_Core_Exception $e) {
      // Do nothing
    }

    $cc = "";
    if (!empty($params['cc'])) {
      $cc = ts(' and cc to %1', [1 => $params['cc']]);
    }
    $bcc = "";
    if (!empty($params['bcc'])) {
      $bcc = ts(' and bcc to %1', [1 => $params['bcc']]);
    }
    return ts('Send e-mail from "%1 (%2 using %3)" with Template "%4" to %5 %6 %7', [
      1 => $params['from_name'],
      2 => $params['from_email'],
      3 => $locationText,
      4 => $template,
      5 => $to,
      6 => $cc,
      7 => $bcc,
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
        return E::ts('Send E-mail to a contact reference');

      case 'actionParamsHelp':
        return E::ts('<p>This is the form where you can set what is going to happen with the email.</p>
    <p>The first few fields are relatively straightforward: the <strong>From Name</strong> is the name the email will be sent from and the <strong>From Email</strong> is the email address the email will be sent from.</p>
    <p>The <strong>Message Template</strong> is where you select which CiviCRM message template will be used to compose the mail. You can create and edit them in <strong>Administer>Communications>Message Templates</strong></p>
    <p>The next section allows you to manipulate where the email will be sent to.<br/>
    <p>Finally you can specify an emailaddress for the <strong>CC to</strong> (a copy of the email will be sent to this email address and the email address will be visible to the recipient of the email too) or the <strong>BCC to</strong> (a copy of the email will be sent to this email address and the email address will NOT be visible to the recipient of the email too).</p>
    <p>The sending of the email will also lead to an activity (type <em>Email</em>) being recorded for the contact in question, whatever email address will be used.</p>');
    }

    return $helpText ?? '';
  }

}
