<?php

abstract class CRM_CivirulesActions_Generic_Api extends CRM_Civirules_Action {

  /**
   * The API version. We default to 3 for compatibility but new actions should set this to 4.
   *
   * @var int $apiVersion
   */
  private int $apiVersion = 3;

  /**
   * Set the API version to use
   *
   * @param int $apiVersion
   *
   * @return void
   * @throws \CRM_Core_Exception
   */
  protected function setApiVersion(int $apiVersion = 3) {
    if (!in_array($apiVersion, [3, 4])) {
      throw new CRM_Core_Exception('Unknown API version: ' . $apiVersion);
    }
    $this->apiVersion = $apiVersion;
  }

  /**
   * Get the API version
   *
   * @return int
   */
  protected function getApiVersion(): int {
    return $this->apiVersion;
  }

  /**
   * Method to get the api entity to process in this CiviRule action
   *
   * @return string
   */
  protected abstract function getApiEntity();

  /**
   * Method to get the api action to process in this CiviRule action
   *
   * @return string
   */
  protected abstract function getApiAction();

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array
   */
  protected function alterApiParameters(array $params, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    // This method could be overridden in subclasses to alter parameters to meet certain criteria
    return $params;
  }

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getApiEntity();
    $action = $this->getApiAction();

    $params = $this->getActionParameters();

    //alter parameters by subclass
    $params = $this->alterApiParameters($params, $triggerData);

    // execute the action
    $this->executeApiAction($entity, $action, $params);
  }

  /**
   * Executes the action
   *
   * This method could be overridden if needed
   *
   * @param string $entity
   * @param string $action
   * @param array $params

   * @throws Exception on api error
   */
  protected function executeApiAction(string $entity, string $action, array $params) {
    switch ($this->getApiVersion()) {
      case 3:
        try {
          civicrm_api3($entity, $action, $params);
        }
        catch (Throwable $e) {
          $message = "Civirules api3 action exception: {$e->getMessage()}. API call: {$entity}.{$action} with params: " . print_r($params, TRUE);
          \Civi::log('civirules')->error($message);
          throw new Exception($message);
        }
        break;

      case 4:
        try {
          civicrm_api4($entity, $action, $params);
        }
        catch (Throwable $e) {
          $message = "Civirules api4 action exception: {$e->getMessage()}. API call: {$entity}.{$action} with params: " . print_r($params, TRUE);
          \Civi::log('civirules')->error($message);
          throw new Exception($message);
        }
    }

  }

}
