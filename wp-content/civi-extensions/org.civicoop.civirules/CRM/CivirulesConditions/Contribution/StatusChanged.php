<?php
/**
 * Class for CiviRules Contribution status changed
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Contribution_StatusChanged extends CRM_CivirulesConditions_Generic_FieldValueChangeComparison {

  /**
   * Returns name of entity
   *
   * @return string
   */
  protected function getEntity() {
    return 'Contribution';
  }

  /**
   * Returns name of the field
   *
   * @return string
   */
  protected function getEntityStatusFieldName() {
    return 'contribution_status_id';
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
    return CRM_CivirulesConditions_Contribution_Status::getEntityStatusList(TRUE);
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
    return CRM_CivirulesConditions_Contribution_Status::getEntityStatusList(TRUE);
  }

}
