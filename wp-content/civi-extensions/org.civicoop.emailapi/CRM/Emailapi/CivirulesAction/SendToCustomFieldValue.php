<?php

class CRM_Emailapi_CivirulesAction_SendToCustomFieldValue extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $actionParams = $this->getActionParameters();
    if (!empty($actionParams['file_on_case'])) {
      $case = $triggerData->getEntityData('Case');
      $actionParams['case_id'] = $case['id'];
    }

    $customValueField = 'custom_' . $actionParams['custom_value'];
    $entityData = $triggerData->getEntityData($actionParams['entity']);
    $customValues = (array) ($entityData[$customValueField] ?? []);
    // Not all $triggerData contains custom field data, so look it up if necessary.
    if (!$customValues) {
      try {
        $customValues = (array) civicrm_api3($this->figureOutEntity($actionParams['entity']), 'getvalue', [
          'return' => $customValueField,
          'id' => $entityData['id'],
        ]);
      } catch (CRM_Core_Exception $e) {
        $customValues = [];
      }
    }
    $params = $actionParams;
    foreach ($customValues as $customValue) {
      $params['contact_id'] = $entityData['id'];
      if ($customValue) {
        $params['alternative_receiver_address'] = $customValue;
        $extra_data = (array) $triggerData;
        $params['extra_data'] = $extra_data["\0CRM_Civirules_TriggerData_TriggerData\0entity_data"];
        //execute the action
        civicrm_api3('Email', 'send', $params);
      }
    }
  }

  /**
   * Get a list of entities that use custom fields.
   *
   * @return array
   * @access public
   */
  public static function getCustomFieldEntities() {
    $return = ['' => '-- please select --'];
    try {
      $result = civicrm_api3('CustomField', 'get', [
        'sequential' => 1,
        'data_type' => 'String',
        'return' => ['custom_group_id.extends'],
        'options' => ['limit' => 0],
      ])['values'];
    }
    catch (CRM_Core_Exception $e) {
      $result = [];
    }
    foreach ($result as $field) {
      $return[$field['custom_group_id.extends']] = $field['custom_group_id.extends'];
    }
    $return = array_unique($return);
    asort($return);
    return $return;
  }

  /**
   * Ajax endpoint to adjust select options when entity field changes.
   */
  public static function getCustomFieldsForEntity($entity = NULL) {
    return CRM_Utils_JSON::output(self::_getCustomFieldsForEntity($entity));
  }

  /**
   * List the custom fields for an entity.
   */
  public static function _getCustomFieldsForEntity($entity = NULL) {
    if (!$entity) {
      $entity = CRM_Utils_Request::retrieve('entity', 'String');
    }
    $return = [];
    $params = [
      'data_type' => 'String',
      'options' => ['limit' => 0],
      'return' => ['id', 'label', 'custom_group_id.title'],
    ];
    if ($entity) {
      $params['custom_group_id.extends'] = $entity;
    }
    try {
      $result = civicrm_api3('CustomField', 'get', $params)['values'];
    }
    catch (CRM_Core_Exception $e) {
      $result = [];
    }
    foreach ($result as $field) {
      $return[$field['id']] = $field['custom_group_id.title'] . '::' . $field['label'];
    }
    asort($return);
    return $return;
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
    return CRM_Utils_System::url('civicrm/civirules/actions/emailapi_customfieldvalue', 'rule_action_id=' . $ruleActionId);
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

    $messageTemplates = new CRM_Core_DAO_MessageTemplate();
    $messageTemplates->id = $params['template_id'];
    $messageTemplates->is_active = TRUE;
    if ($messageTemplates->find(TRUE)) {
      $template = $messageTemplates->msg_title;
    }

    try {
      $to = civicrm_api3('CustomField', 'getvalue', ['return' => "label", 'id' => $params['custom_value']]);
    }
    catch (CRM_Core_Exception $e) {
      $to = '';
    }

    $cc = "";
    if (!empty($params['cc'])) {
      $cc = ts(' and cc to %1', [1 => $params['cc']]);
    }
    $bcc = "";
    if (!empty($params['bcc'])) {
      $bcc = ts(' and bcc to %1', [1 => $params['bcc']]);
    }
    return ts('Send e-mail from "%1 (%2)" with Template "%3" to %4 %5 %6', [
      1 => $params['from_name'],
      2 => $params['from_email'],
      3 => $template,
      4 => $to,
      5 => $cc,
      6 => $bcc,
    ]);
  }

  /**
   * If it's not a contact type, then just return itself. If it is a contact type, then return "Contact".
   * @param string $entity
   * @return string
   */
  private function figureOutEntity(string $entity): string {
    $ctypes = civicrm_api3('ContactType', 'get', ['return' => ['id', 'name']]);
    foreach ($ctypes['values'] as $ctype) {
      if ($ctype['name'] == $entity) {
        return 'Contact';
      }
    }
    return $entity;
  }

}
