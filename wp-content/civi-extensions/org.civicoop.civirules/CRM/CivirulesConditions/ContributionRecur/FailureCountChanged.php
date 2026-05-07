<?php

class CRM_CivirulesConditions_ContributionRecur_FailureCountChanged extends CRM_CivirulesConditions_Generic_FieldValueChangeComparison {

  /**
   * Returns name of entity
   *
   * @return string
   */
  protected function getEntity() {
    return 'ContributionRecur';
  }

  /**
   * Returns name of the field
   *
   * @return string
   */
  protected function getEntityStatusFieldName() {
    return 'failure_count';
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/true
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    $entities = $trigger->getProvidedEntities();
    if (isset($entities[$this->getEntity()])) {
      return TRUE;
    }
    return FALSE;
  }

}
