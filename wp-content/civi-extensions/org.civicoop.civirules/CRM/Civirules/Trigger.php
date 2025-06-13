<?php

abstract class CRM_Civirules_Trigger {

  /**
   * The Rule ID
   *
   * @var int
   */
  protected int $ruleId;

  /**
   * The Rule Trigger ID
   *
   * @var int
   */
  protected int $triggerId;

  protected string $triggerName;

  /**
   * The Trigger Params
   *
   * @var array
   */
  protected array $triggerParams;

  /**
   * @var \CRM_Civirules_TriggerData_TriggerData
   */
  protected \CRM_Civirules_TriggerData_TriggerData $triggerData;

  /**
   * @var string
   */
  protected string $ruleTitle;

  /**
   * @var bool
   */
  protected bool $ruleDebugEnabled;

  /**
   * The Rule Conditions
   * Conditions are cached in this variable
   *
   * @var array
   */
  protected array $ruleConditions;

  public function __construct($trigger = NULL) {
    if (isset($trigger)) {
      $this->triggerId = $trigger['id'] ?? NULL;
      $this->triggerName = $trigger['name'] ?? NULL;
    }
  }

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
    try {
      $triggerParams = unserialize($triggerParams);
      // If unserialize fails, FALSE is returned. We need an array
      $this->triggerParams = $triggerParams ?: [];
    }
    catch (TypeError $e) {
      \Civi::log()->error('CiviRules setTriggerParams: Could not unserialize trigger params.');
      // Something went wrong, set to empty array
      $this->triggerParams = [];
    }
  }

  /**
   * @return array
   */
  public function getTriggerParams(): array {
    return $this->triggerParams;
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
      $this->ruleConditions = \Civi\Api4\CiviRulesRuleCondition::get(FALSE)
        ->addWhere('rule_id', '=', $this->ruleId)
        ->addOrderBy('weight', 'ASC')
        ->addOrderBy('id', 'ASC')
        ->execute()
        ->getArrayCopy();
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

  /**
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  public function getProvidedEntities(): array {
    if (empty(\Civi::$statics[__CLASS__]['getProvidedEntities'])) {
      $additionalEntities = $this->getAdditionalEntities();
      foreach ($additionalEntities as $entity) {
        $entities[$entity->key] = $entity;
      }

      $entity = $this->reactOnEntity();
      $entities[$entity->key] = $entity;
      \Civi::$statics[__CLASS__]['getProvidedEntities'] = $entities;
    }

    return \Civi::$statics[__CLASS__]['getProvidedEntities'];
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
    return $this->doesProvideEntities([$entity]);
  }

  /**
   * Checks whether the trigger provides a certain set of entities
   *
   * @param array<string> $entities
   *
   * @return bool
   */
  public function doesProvideEntities(array $entities): bool {
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
   * Returns a calculated description of this trigger
   * If the trigger has parameters this this function should provide a user-friendly description of those parameters
   * See also: getHelpText()
   * You could return the contents of getHelpText('triggerDescriptionWithParams') if you want a generic description and the trigger has no configurable
   * parameters.
   *
   * @return string
   */
  public function getTriggerDescription() {
    // If you implement getHelpText('triggerDescriptionWithParams') then you don't need to implement this function!
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
    catch (Throwable $e) {
      \Civi::log()->error('Failed to trigger rule: ' . $e->getMessage());
    }
  }

  /**
   * Get various types of help text for the trigger:
   *   - triggerDescription: When choosing from a list of triggers, explains what the trigger does.
   *   - triggerDescriptionWithParams: When a trigger has been configured for a rule provides a
   *       user friendly description of the trigger and params (see $this->getTriggerDescription())
   *   - triggerParamsHelp (default): If the trigger has configurable params, show this help text when configuring
   * @param string $context
   *
   * @return string
   */
  public function getHelpText(string $context): string {
    // Child classes should override this function

    switch ($context) {
      case 'triggerDescriptionWithParams':
        return $this->getTriggerDescription();

      case 'triggerDescription':
      case 'triggerParamsHelp':
      default:
        // Historically getHelpText() was on the form class.
        // But we have no way to get the form class - only the path via getExtraDataInputUrl()
        // The Form *does* have access to the trigger class via $this->triggerClass so if getHelpText()
        //   is on the triggerClass we can just do $this->triggerClass->getHelpText().

        // getHelpText() doesn't exist on trigger class.
        // Try to get Form class for trigger and see if getHelpText() exists there
        $classBits = explode('_', get_class($this));

        $formClass = $classBits[0] . '_' . $classBits[1] . '_Form';
        for ($i = 2; $i < count($classBits); $i++) {
          $formClass .= '_' . $classBits[$i];
        }
        if (class_exists($formClass) && method_exists($formClass, 'getHelpText')) {
          $helpText = (new $formClass())->getHelpText();
        }
    }

    return $helpText ?? '';
  }

}
