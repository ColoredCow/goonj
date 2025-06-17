<?php

use CRM_Civirules_ExtensionUtil as E;

/**
 * Class for Action "Set the Status of a Case"
 */
class CRM_CivirulesActions_Case_SetStatus extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $case = $triggerData->getEntityData('Case');
    $params = $this->getActionParameters();
    $params['id'] = $case['id'];

    $closedStatus = \Civi\Api4\OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'case_status')
      ->addWhere('grouping', '=', 'Closed')
      ->addWhere('value', '=', $params['status_id'])
      ->execute()
      ->first();

    // Set case end_date if we're closing the case. Clear end_date if we're (re)opening it.
    if (!empty($closedStatus)) {
      if (empty($case['end_date'])) {
        $endDate = new DateTime();
        $params['end_date'] = $endDate->format('Ymd');
        // Update the case roles
        $relQuery = 'UPDATE civicrm_relationship SET end_date=%2 WHERE case_id=%1 AND end_date IS NOT NULL';
        $relParams = [
          1 => [$case['id'], 'Integer'],
          2 => [$params['end_date'], 'Timestamp'],
        ];
        CRM_Core_DAO::executeQuery($relQuery, $relParams);
      }
    } else {
      $params['end_date'] = '';

      // Update the case roles
      $relQuery = 'UPDATE civicrm_relationship SET end_date=NULL WHERE case_id=%1';
      $relParams = [
        1 => [$case['id'], 'Integer'],
      ];
      CRM_Core_DAO::executeQuery($relQuery, $relParams);
    }

    //execute the action
    $this->executeApiAction('Case', 'create', $params);
  }

  /**
   * Executes the action
   *
   * This method could be overridden if needed
   *
   * @param $entity
   * @param $action
   * @param $parameters
   * @access protected
   * @throws Exception on api error
   */
  protected function executeApiAction($entity, $action, $parameters) {
    try {
      civicrm_api3($entity, $action, $parameters);
    } catch (Exception $e) {
      \Civi::log('civirules')->error('Set the Status of a Case: executeApiAction failed: ' . $e->getMessage());
      $formattedParams = '';
      foreach($parameters as $key => $param) {
        if (strlen($formattedParams)) {
          $formattedParams .= ', ';
        }
        $formattedParams .= $key.' = '.$param;
      }
      throw new Exception('Civirules api action exception '.$entity.'.'.$action.' ('.$formattedParams.')');
    }
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * @param int $ruleActionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/case/setstatus', $ruleActionId);
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
      $action_params['status_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'name',
        'value' => $action_params['status_id'],
        'option_group_id' => 'case_status',
      ]);
    } catch (Exception $e) {
      \Civi::log('civirules')->error('"Set the Status of a Case":  export error: ' . $e->getMessage());
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
      $action_params['status_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'value',
        'name' => $action_params['status_id'],
        'option_group_id' => 'case_status',
      ]);
    } catch (CRM_Core_Exception $e) {
      \Civi::log('civirules')->error('"Set the Status of a Case":  import error: ' . $e->getMessage());
    }
    return parent::importActionParameters($action_params);
  }


  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $status = CRM_Case_PseudoConstant::caseStatus();
    return E::ts('Set case status to: %1', [1 => $status[$params['status_id']]]);
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
    return isset($entities['Case']);
  }
}
