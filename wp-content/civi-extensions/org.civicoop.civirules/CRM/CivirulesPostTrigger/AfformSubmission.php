<?php

use Civi\Api4\Afform;
use Civi\Api4\AfformSubmission;
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_AfformSubmission extends CRM_Civirules_Trigger_Post {

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'AfformSubmission');
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an
   * empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Afform_DAO_AfformSubmission';
  }

  /**
   * Checks whether the trigger provides a certain entity.
   *
   * @param string $entity
   *
   * @return bool
   */
  public function doesProvideEntity(string $entity): bool {
    // Ideally we'd check if Afform supports the entity first
    // Even better, we'd load the specific Afform and check what entities it has.
    // For now just say we support all entities.
    return TRUE;
  }

  /**
   * Checks whether the trigger provides a certain set of entities
   *
   * @param array<string> $entities
   *
   * @return bool
   */
  public function doesProvideEntities($entities): bool {
    // Ideally we'd check if Afform supports the entity first
    // Even better, we'd load the specific Afform and check what entities it has.
    // For now just say we support all entities.
    return TRUE;
  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    // @todo: This needs to support all the entities on the form.
    // But there doesn't seem to be an easy way to get that info using Afform::get()
    // For now just hardcode a few.
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Activity', 'Activity', 'CRM_Activity_DAO_Activity' , 'Activity');
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('ActivityContact', 'ActivityContact', 'CRM_Activity_DAO_ActivityContact' , 'ActivityContact');
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Case', 'Case', 'CRM_Case_DAO_Case' , 'Case');
    return $entities;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a
   * trigger
   *
   * Return false if you do not need extra data input
   *
   * @param $ruleId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/trigger/afformsubmission', $ruleId);
  }

  /**
   * Get various types of help text for the trigger:
   *   - triggerDescription: When choosing from a list of triggers, explains
   * what the trigger does.
   *   - triggerDescriptionWithParams: When a trigger has been configured for a
   * rule provides a user friendly description of the trigger and params (see
   * $this->getTriggerDescription())
   *   - triggerParamsHelp (default): If the trigger has configurable params,
   * show this help text when configuring
   *
   * @param string $context
   *
   * @return string
   */
  public function getHelpText(string $context = 'triggerParamsHelp'): string {
    switch ($context) {
      case 'triggerDescription':
        return E::ts('Trigger on %1', [1 => $this->getObjectName()]);

      case 'triggerDescriptionWithParams':
        return $this->getTriggerDescription();

      case 'triggerParamsHelp':
        switch ($this->getOp()) {
          case 'create|edit':
            return E::ts('Choose the Formbuilder forms to trigger on. The first Entity of each type will 
            be available as context for the rule (eg. for conditions/actions etc). Eg. Individual1=Contact,Activity1=Activity,Case1=Case etc.')
              . '<br> ' . E::ts('Select if you want to trigger on Create and/or Edit');

          case 'delete':
          default:
            return '';
        }
      default:
        return parent::getHelpText($context);
    }
  }

  /**
   * Returns a calculated description of this trigger
   * If the trigger has parameters this this function should provide a
   * user-friendly description of those parameters See also: getHelpText() You
   * could return the contents of getHelpText('triggerDescriptionWithParams')
   * if you want a generic description and the trigger has no configurable
   * parameters.
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $afforms = Afform::get(FALSE)
      ->addWhere('type:name', '=', 'form')
      ->addSelect('name', 'title')
      ->execute()->indexBy('name')->getArrayCopy();

    $selectedForms = explode(',', $this->triggerParams['rule_afform_select'] ?? '');
    foreach ($selectedForms as $formName) {
      if (in_array($formName, array_keys($afforms))) {
        $formTitles[] = '<a href=' . Civi::url('backend://civicrm/admin/afform#/edit/' . $formName) . ' target="_blank">' . $afforms[$formName]['title'] . '</a>';
      }
    }

    $options = CRM_CivirulesTrigger_Form_Form::getTriggerOptions();
    $triggerOps = explode(',', $this->triggerParams['trigger_op'] ?? '');
    foreach ($options as $option) {
      if (in_array($option['id'], $triggerOps)) {
        $triggerOptions[] = $option['text'];
      }
    }
    $text = E::ts('Trigger for forms: %1 on %2', [
      1 => implode(', ', $formTitles ?? []),
      2 => implode(', ', $triggerOptions ?? []),
    ]);
    return $text;
  }

  /**
   * Override alter trigger data.
   *
   */
  public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
    $afformSubmission = $triggerData->getEntityData('AfformSubmission');
    // Submission data isn't necessarily the complete record, so let's merge the db data in.
    $dataInDatabase = AfformSubmission::get(FALSE)
      ->addWhere('id', '=', $afformSubmission['id'])
      ->execute()
      ->first();
    $triggerData->setEntityData('AfformSubmission', array_merge($dataInDatabase, $afformSubmission));
    $submissionData = json_decode($afformSubmission['data'], TRUE);

    foreach ($submissionData as $entityName => $data) {
      if (!isset($data[0])) {
        continue;
      }
      if (!str_ends_with($entityName, '1')) {
        // For now we only support Entity1 for each entity type (eg. Individual1, Activity1 etc)
        continue;
      }
      if (empty($data[0]['id'])) {
        // If we have no ID we obviously can't use it..
        continue;
      }
      $entityID = $data[0]['id'];
      $realEntityName = str_replace('1', '', $entityName);
      if (in_array($realEntityName, [
        'Individual',
        'Household',
        'Organization',
      ])) {
        $realEntityName = 'Contact';
      }
      try {
        $entity = civicrm_api4($realEntityName, 'get', [
          'where' => [
            ['id', '=', $entityID],
          ],
          'checkPermissions' => FALSE,
        ], 0)->getArrayCopy();
        $triggerData->setEntityData($realEntityName, $entity);
      }
      catch (\Throwable $t) {
        // Do nothing. There could be an exception when something doesn't exist or it's not a real entity
      }
    }

    parent::alterTriggerData($triggerData);
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
    $this->setTriggerData($this->getTriggerDataFromPost($op, $objectName, $objectId, $objectRef, $eventID));
    if ($this->getTriggerParams()['rule_afform_select'] !== $this->getTriggerData()->getEntityData('AfformSubmission')['afform_name']) {
      parent::triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
    }
  }

}
