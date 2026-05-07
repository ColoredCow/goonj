<?php

class CRM_CivirulesConditions_Activity_StatusChanged extends CRM_CivirulesConditions_Generic_FieldValueChangeComparison {

  /**
   * Returns name of entity
   *
   * @return string
   */
  protected function getEntity() {
    return 'Activity';
  }

  /**
   * Returns name of the field
   *
   * @return string
   */
  protected function getEntityStatusFieldName() {
    return 'status_id';
  }

  /**
   * Returns the value of the field for the condition
   * For example: I want to check if age > 50, this function would return the 50
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return mixed|null
   * @throws \CRM_Core_Exception
   */
  protected function getFieldValue(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $field = $this->getEntityStatusFieldName();

    $activityData = $triggerData->getEntityData($this->getEntity());
    // @todo why do we do this lookup? Otherwise we could use the generic function
    $data = civicrm_api3($this->getEntity(), 'getsingle', [
      'return' => [$field],
      'id' => $activityData['id'],
    ]);
    if (isset($data[$field])) {
      return $data[$field];
    }

    return null;
  }

  /**
   * Returns an array with all possible options for the field, in
   * case the field is a select field, e.g. gender, or financial type
   * Return false when the field is a select field
   *
   * This method could be overridden by child classes to return the option
   *
   * The return is an array with the field option value as key and the option label as value
   *
   * @return array
   */
  public function getFieldOptions() {
    return CRM_CivirulesConditions_Activity_Status::getEntityStatusList(TRUE);
  }

  /**
   * Returns an array with all possible options for the field, in
   * case the field is a select field, e.g. gender, or financial type
   * Return false when the field is a select field
   *
   * This method could be overridden by child classes to return the option
   *
   * The return is an array with the field option value as key and the option
   * label as value
   *
   * @return array
   */
  public function getFieldOptionsNames() {
    $return = [];
    $params = [
      'return' => ["name", "value"],
      'option_group_id' =>  'activity_status',
      'options' => ['limit' => 0, 'sort' => "label ASC"],
    ];
    try {
      $options = civicrm_api3('OptionValue', 'get', $params)['values'];
      foreach ($options as $option) {
        $return[$option['value']] = $option['name'];
      }
    } catch (CRM_Core_Exception $ex) {}

    return $return;
  }


  /**
   * Returns true when the field is a select option with multiple select
   *
   * @see getFieldOptions
   * @return bool
   */
  public function isMultiple() {
    return true;
  }

}
