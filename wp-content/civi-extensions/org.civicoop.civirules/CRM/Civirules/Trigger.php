<?php

abstract class CRM_Civirules_Trigger {

  /**
   * The Rule ID
   *
   * @var int
   */
  protected $ruleId;

  /**
   * The Rule Trigger ID
   *
   * @var int
   */
  protected $triggerId;

  /**
   * The Trigger Params
   *
   * @var array
   */
  protected $triggerParams;

  /**
   * @var \CRM_Civirules_TriggerData_TriggerData
   */
  protected \CRM_Civirules_TriggerData_TriggerData $triggerData;

  /**
   * @var string
   */
  protected $ruleTitle;

  /**
   * @var bool
   */
  protected $ruleDebugEnabled;

  /**
   * The Rule Conditions
   * Conditions are cached in this variable
   *
   * @var array
   */
  protected $ruleConditions;

  /**
   * @param int $ruleId
   *
   * @return void
   */
  public function setRuleId(int $ruleId) {
    $this->ruleId = $ruleId;
  }

  /**
   * This is stored as a serialized array in the database
   *
   * @param string $triggerParams
   *
   * @return void
   */
  public function setTriggerParams(string $triggerParams) {
    // Initialise as empty array in case we fail to unserialize (so we don't crash when trying to access uninitialised data).
    $this->triggerParams = [];
    try {
      $this->triggerParams = unserialize($triggerParams);
    }
    catch (TypeError $e) {
      \Civi::log()->error('CiviRules setTriggerParams: Could not unserialize trigger params.');
    }
  }

  /**
   * @return int
   */
  public function getRuleId(): int {
    return $this->ruleId;
  }

  /**
   * @param int $triggerId
   *
   * @return void
   */
  public function setTriggerId(int $triggerId) {
    $this->triggerId = $triggerId;
  }

  /**
   * @return int
   */
  public function getTriggerId(): int {
    return $this->triggerId;
  }

  /**
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return void
   */
  public function setTriggerData(\CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $this->triggerData = $triggerData;
  }

  /**
   * @return \CRM_Civirules_TriggerData_TriggerData
   */
  public function getTriggerData(): \CRM_Civirules_TriggerData_TriggerData {
    return $this->triggerData;
  }

  /**
   * Check if the triggerData has been set
   *
   * @return bool
   */
  public function hasTriggerData(): bool {
    return isset($this->triggerData);
  }

  /**
   * @return string
   */
  public function getRuleTitle(): string {
    if (!isset($this->ruleTitle) && !empty($this->ruleId)) {
      $rule = new CRM_Civirules_BAO_Rule();
      $rule->id = $this->ruleId;
      if ($rule->find(true)) {
        $this->ruleTitle = $rule->label;
      }
    }
    return $this->ruleTitle ?? '';
  }

  /**
   * @return bool
   */
  public function getRuleDebugEnabled(): bool {
    if (!isset($this->ruleDebugEnabled) && !empty($this->ruleId)) {
      $rule = new CRM_Civirules_BAO_Rule();
      $rule->id = $this->ruleId;
      if ($rule->find(true)) {
        $this->ruleDebugEnabled = $rule->is_debug;
      }
    }
    return $this->ruleDebugEnabled ?? FALSE;
  }

  /**
   * Retrieve rule conditions for the current rule.
   * Results are cached.
   *
   * @return array
   */
  public function getRuleConditions(): array {
    if (!isset($this->ruleConditions) && !empty($this->ruleId)) {
      $this->ruleConditions = CRM_Civirules_BAO_RuleCondition::getValues(['rule_id' => $this->ruleId]);
    }
    return $this->ruleConditions ?? [];
  }

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  abstract protected function reactOnEntity();

  /**
   * Returns the name of the trigger data class.
   *
   * This function could be overridden in a child class.
   *
   * @return String
   */
  public function getTriggerDataClassName() {
    return 'CRM_Civirules_TriggerData_TriggerData';
  }


  public function getProvidedEntities() {
    $additionalEntities = $this->getAdditionalEntities();
    foreach($additionalEntities as $entity) {
      $entities[$entity->key] = $entity;
    }

    $entity = $this->reactOnEntity();
    $entities[$entity->key] = $entity;

    return $entities;
  }

  /**
   * @return \CRM_Civirules_TriggerData_EntityDefinition
   */
  public function getReactOnEntity() {
    return $this->reactOnEntity();
  }

  /**
   * Checks whether the trigger provides a certain entity.
   *
   * @param string $entity
   *
   * @return bool
   */
  public function doesProvideEntity(string $entity): bool {
    $availableEntities = $this->getProvidedEntities();
    foreach($availableEntities as $providedEntity) {
      if (strtolower($providedEntity->entity) == strtolower($entity)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether the trigger provides a certain set of entities
   *
   * @param array<string> $entities
   *
   * @return bool
   */
  public function doesProvideEntities($entities): bool {
    $availableEntities = $this->getProvidedEntities();
    foreach($entities as $entity) {
      $entityPresent = FALSE;
      foreach ($availableEntities as $providedEntity) {
        if (strtolower($providedEntity->entity) == strtolower($entity)) {
          $entityPresent = TRUE;
        }
      }
      if (!$entityPresent) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $reactOnEntity = $this->reactOnEntity();
    $entities = [];
    if (strtolower($reactOnEntity->key) != strtolower('Contact')) {
      $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Contact', 'Contact', 'CRM_Contact_DAO_Contact', 'Contact');
    }
    return $entities;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a trigger
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleId) {
    return FALSE;
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription() {
    return '';
  }

  /**
   * Alter the trigger data with extra data
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
    $hook_invoker = CRM_Civirules_Utils_HookInvoker::singleton();
    $hook_invoker->hook_civirules_alterTriggerData($triggerData);
  }

  /**
   * Trigger a rule for this trigger
   *
   * @param string $op
   * @param string $objectName
   * @param int $objectId
   * @param object $objectRef
   * @param string $eventID
   */
  public function triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID) {
    if (!$this->hasTriggerData()) {
      throw new CRM_Core_Exception('CiviRules: Trigger data is empty. You need to call setTriggerData() first');
    }

    try {
      CRM_Civirules_Engine::triggerRule($this, $this->getTriggerData());
    }
    catch (Exception $e) {
      \Civi::log()->error('Failed to trigger rule: ' . $e->getMessage());
    }
  }

}
