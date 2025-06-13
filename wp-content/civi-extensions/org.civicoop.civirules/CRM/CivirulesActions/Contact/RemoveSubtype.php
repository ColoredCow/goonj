<?php

class CRM_CivirulesActions_Contact_RemoveSubtype extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    $subTypes = CRM_Contact_BAO_Contact::getContactSubType($contactId);
    $typesToRemove = [];
    $changed = FALSE;
    $actionParams = $this->getActionParameters();
    foreach($actionParams['sub_type'] as $subType) {
      if (in_array($subType, $subTypes )) {
        $typesToRemove[] = $subType;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $updatedContactSubTypes = array_diff($subTypes, $typesToRemove);
      $updatedContactSubTypes = empty($updatedContactSubTypes) ? NULL : $updatedContactSubTypes;
      \Civi\Api4\Contact::update(FALSE)
        ->addValue('contact_sub_type', $updatedContactSubTypes)
        ->addWhere('id', '=', $contactId)
        ->execute();
    }
  }

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   *
   * @return bool
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/contact/subtype/remove', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $label = ts('Remove contact subtype');
    $subTypeLabels = [];
    $subTypes = CRM_Contact_BAO_ContactType::contactTypeInfo();
    foreach($params['sub_type'] as $subType) {
      $subTypeLabels[] = $subTypes[$subType]['parent_label'].' - '.$subTypes[$subType]['label'];
    }
    $label .= ': ' . implode(', ', $subTypeLabels);
    return $label;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    foreach($action_params['sub_type'] as $i=>$j) {
      try {
        $action_params['sub_type'][$i] = civicrm_api3('ContactType', 'getvalue', [
          'return' => 'name',
          'id' => $j,
        ]);
      } catch (CRM_Core_Exception $e) {
      }
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
    foreach($action_params['sub_type'] as $i=>$j) {
      try {
        $action_params['sub_type'][$i] = civicrm_api3('ContactType', 'getvalue', [
          'return' => 'id',
          'name' => $j,
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    }
    return parent::importActionParameters($action_params);
  }

}
