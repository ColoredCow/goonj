<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Membership_EndDateChanged extends CRM_CivirulesConditions_Generic_FieldChanged {

  private $conditionParams = array();

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = array();
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Method to check if the condition is valid
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    //not the right trigger. The trigger data should contain also
    if (!$triggerData instanceof CRM_Civirules_TriggerData_Interface_OriginalData) {
      return false;
    }
    $entity = $this->getEntity();
    if ( strtolower($entity) != strtolower($triggerData->getOriginalEntity()) ) {
      return false;
    }
    // we need to check to see if the data being submitted actually contains the field we are comparing. if not, return false, no change
    $compareField = $this->getField();
    $compareEntityData = $triggerData->getEntityData($entity);
    $compareEntityCustomData = $triggerData->getEntityCustomData();
    if ( array_key_exists($compareField, $compareEntityData) || array_key_exists($compareField, $compareEntityCustomData) ) {
      $fieldData = $this->getFieldData($triggerData);
    } else {
      return false;
    }
    $originalData = $this->getOriginalFieldData($triggerData);

    if (empty($fieldData) && empty($originalData)) {
      return false; //both original and new data are null so assume not changed
    } elseif ($fieldData == $originalData) {
      return false; //both data are equal so assume not changed
    }

    if (isset($this->conditionParams['end_date_after_old_end_date']) || !$this->conditionParams['end_date_after_old_end_date']) {
      if (empty($originalData) && !empty($fieldData)) {
        return false;
      } elseif (!empty($originalData) && empty($fieldData)) {
        return true;
      } elseif ($fieldData > $originalData) {
        return true;
      }
    } else {
      return true;
    }

    return false;
  }

  /**
   * Returns the value of the field for the condition
   * For example: I want to check if age > 50, this function would return the 50
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return
   * @access protected
   * @abstract
   */
  protected function getFieldValue(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getEntity();
    $data = $triggerData->getEntityData($entity);
    $field = $this->getField();
    if (isset($data[$field])) {
      return $data[$field];
    }
    return null;
  }

  /**
   * Returns name of entity
   *
   * @return string
   * @access protected
   */
  protected function getEntity() {
    return 'Membership';
  }

  /**
   * Returns name of the field
   *
   * @return string
   * @access protected
   */
  protected function getField() {
    return 'end_date';
  }

  /**
   * This method could be overridden in subclasses to
   * transform field data to a certain type
   *
   * E.g. a date field could be transformed to a DataTime object so that
   * the comparison is easier
   *
   * @param mixed $fieldData
   * @return mixed
   * @access protected
   */
  protected function transformFieldData($fieldData) {
    if (empty($fieldData)) {
      return null;
    }
    try {
      return new \DateTime($fieldData);
    } catch (Exception $e) {
      return null;
    }
  }

  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/membershipenddatechanged', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    if (isset($this->conditionParams['end_date_after_old_end_date']) && $this->conditionParams['end_date_after_old_end_date']) {
      return E::ts('End date after old end date');
    }
    return '';
  }

}
