<?php

class CRM_CivirulesConditions_AfformSubmission_StatusChanged extends CRM_CivirulesConditions_Generic_FieldValueChangeComparison {

  /**
   * Returns name of entity
   *
   * @return string
   */
  protected function getEntity() {
    return 'AfformSubmission';
  }

  /**
   * The aliased (human readable) entity name (eg. FormBuilder Submission)
   *
   * @return string
   */
  protected function getEntityAlias(): string {
    return 'FormBuilder Submission';
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
    return array_column(Civi::entity('AfformSubmission')->getOptions('status_id'), 'label', 'id');
  }

  /**
   * Returns true when the field is a select option with multiple select
   *
   * @see getFieldOptions
   * @return bool
   */
  public function isMultiple() {
    return TRUE;
  }

}
