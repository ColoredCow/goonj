<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Case_AddRole extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $case = $triggerData->getEntityData("Case");
    $params = $this->getActionParameters();
    $contact_ids_a = [];
    $contact_ids_b = [];
    if (!empty($params['cid'])) {
      $contact_ids_a[] = $triggerData->getContactId();
      $contact_ids_b[] = $params['cid'];
    } else {
      $contact_ids_b[] = $triggerData->getContactId();
      try {
        $caseContacts = \Civi\Api4\CaseContact::get(TRUE)
          ->addWhere('case_id', '=', $case['id'])
          ->setLimit(0)
          ->execute();
        foreach ($caseContacts as $caseContact) {
          $contact_ids_a[] = $caseContact['contact_id'];
        }
      } catch (\Civi\API\Exception\UnauthorizedException|CRM_Core_Exception $e) {

      }
    }
    foreach ($contact_ids_a as $contact_id_a) {
      foreach ($contact_ids_b as $contact_id_b) {
        $api_params['contact_id_a'] = $contact_id_a;
        $api_params['contact_id_b'] = $contact_id_b;
        $api_params['relationship_type_id'] = $params['role'];
        $api_params['case_id'] = $case['id'];
        try {
          civicrm_api3('Relationship', 'create', $api_params);
        } catch (\Exception $ex) {
          // Do nothing
        }
      }
    }
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
      $action_params['role'] = civicrm_api3('RelationshipType', 'getvalue', [
        'return' => 'name_a_b',
        'id' => $action_params['role'],
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
      $action_params['role'] = civicrm_api3('RelationshipType', 'getvalue', [
        'return' => 'id',
        'name_a_b' => $action_params['role'],
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    return parent::importActionParameters($action_params);
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * @param int $ruleActionId
   * @return bool|string
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/case/addrole', $ruleActionId);
  }


  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $roles = self::getCaseRoles();
    if (!empty($params['cid'])) {
      $contactDisplayName = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('id', '=', $params['cid'])
        ->execute()
        ->first()['display_name'] ?? '';
      return E::ts('Add %2 to the case with role <em>%1</em>', [1 => $roles[$params['role']], 2 => $contactDisplayName]);
    } else {
      return E::ts('Add the triggering contact to the case with role <em>%1</em>', [1 => $roles[$params['role']]]);
    }
  }

  /**
   * Validates whether this action works with the selected trigger.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    $entities = $trigger->getProvidedEntities();
    return isset($entities['Case']);
  }

  /**
   * Returns a list of possible case roles
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public static function getCaseRoles() {
    $relationshipTypesApi = civicrm_api3('RelationshipType', 'get', ['options' => ['limit' => 0]]);
    $caseRoles = [];
    foreach($relationshipTypesApi['values'] as $relType) {
      $caseRoles[$relType['id']] = $relType['label_a_b'];
    }
    return $caseRoles;
  }
}
