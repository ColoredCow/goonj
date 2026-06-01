<?php
/**
 * Class for CiviRules Participant status changed
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 1 Oct 2019
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Participant_StatusChanged extends CRM_CivirulesConditions_Generic_FieldValueChangeComparison {

  /**
   * Returns name of entity
   *
   * @return string
   */
  protected function getEntity() {
    return 'Participant';
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
    return CRM_CivirulesConditions_Participant_Status::getEntityStatusList(TRUE);
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
      'return' => ["name", "id"],
      'options' => ['limit' => 0, 'sort' => "name ASC"],
    ];
    try {
      $options = civicrm_api3('ParticipantStatusType', 'get', $params)['values'];
      foreach ($options as $option) {
        $return[$option['id']] = $option['name'];
      }
    } catch (CRM_Core_Exception $ex) {}

    return $return;
  }

}
