<?php

class CRM_Civirules_TriggerData_Edit extends CRM_Civirules_TriggerData_Post implements CRM_Civirules_TriggerData_Interface_OriginalData {

  protected $originalData = array();

  /**
   * @param string $entity
   * @param int $objectId
   * @param array $data
   * @param array $originalData
   * @param ?\CRM_Civirules_Trigger_Post $trigger
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function __construct($entity, $objectId, $data, $originalData, ?CRM_Civirules_Trigger_Post $trigger = NULL) {
    parent::__construct($entity, $objectId, $data, $trigger);

    if (!is_array($originalData)) {
      throw new Exception('Original data is not set or is not an array in EditTriggerData for CiviRules');
    }
    $this->originalData = CRM_Civirules_Engine::useCanonicalFieldNames($entity, $originalData);
  }

  public function getOriginalData() {
    return $this->originalData;
  }

  public function getOriginalEntity() {
    return $this->getEntity();
  }

}
