<?php

class CRM_CivirulesConditions_FieldValueComparison extends CRM_CivirulesConditions_Generic_ValueComparison {

  /**
   * Returns the value of the field for the condition
   * For example: I want to check if age > 50, this function would return the 50
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return mixed
   */
  protected function getFieldValue(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->conditionParams['entity'];
    $field = $this->conditionParams['field'];
    $dataIsOriginalData = false;

    if ($triggerData instanceof CRM_Civirules_TriggerData_Interface_OriginalData &&
        !empty($this->conditionParams['original_data'])) {
      $data = $triggerData->getOriginalData($entity);
      $dataIsOriginalData = true;
    } else {
      $data = $triggerData->getEntityData($entity);
    }

    // Check whether the field is custom field and whether the data is not original data.
    // When it is original data, the custom data should be present in the $data array.
    // If it is not original data then we have to retrieve the custom field from the database.
    // This is because the custom data is not available in the trigger data.
    if (strpos($field, 'custom_')===0 && !$dataIsOriginalData) {
      $custom_field_id = str_replace("custom_", "", $field);
      try {
        $params['entityID'] = (isset($data['id']) ? $data['id'] : null);
        $params[$field] = 1;
        $values = CRM_Core_BAO_CustomValueTable::getValues($params);

        $value = null;
        if (isset($values[$field])) {
          $value = $this->normalizeValue($values[$field]);
        } elseif (!empty($values['error_message'])) {
          $value = $triggerData->getCustomFieldValue($custom_field_id);
        }

        if ($value !== null) {
          $value = $this->convertMultiselectCustomfieldToArray($custom_field_id, $value);
          return $this->normalizeValue($value);
        }
      } catch (Exception $e) {
        //do nothing
      }
    } elseif (isset($data[$field])) {
      return $this->normalizeValue($data[$field]);
    }

    return null;
  }

  /**
   * Returns an array of value when the custom field is a multi select
   * otherwise just return the value
   *
   * @param int $custom_field_id
   * @param string $value
   *
   * @return array|string
   * @throws \CRM_Core_Exception
   */
  protected function convertMultiselectCustomfieldToArray($custom_field_id, $value) {
    if (CRM_Civirules_Utils_CustomField::isCustomFieldMultiselect($custom_field_id) && !is_array($value)) {
      $value = trim($value, CRM_Core_DAO::VALUE_SEPARATOR);
      $value = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
    }
    return $value;
  }

  /**
   * Returns the value for the data comparison
   *
   * @return mixed|null
   * @throws \CRM_Core_Exception
   */
  protected function getComparisonValue() {
    $value = parent::getComparisonValue();
    if (is_array($value)) {
      return $this->normalizeValue($value);
    } elseif (strlen($value) != 0) {
      return $this->normalizeValue($value);
    } else {
      return null;
    }
  }

  /**
   * @param mixed $value
   *
   * @return mixed|null
   */
  protected function normalizeValue($value) {
    if ($value === null) {
      return null;
    }
    //@todo normalize value based on the field

    $entity = $this->conditionParams['entity'];
    $field = $this->conditionParams['field'];  
    if ( $this->isDateField( $entity, $field ) ) {
      $value = Date('Ymd', strtotime($value));
    }

    return $value;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleConditionId
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/fieldvaluecomparison', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function userFriendlyConditionParams() {
    $value = $this->getComparisonValue();
    if (is_array($value)) {
      $value = implode(", ", $value);
    }
    $field = $this->conditionParams['field'];
    if (substr($field, 0, 7) === 'custom_') {
      $fieldID = substr($field, 7);
      try {
        $field = civicrm_api3('CustomField', 'getvalue', ['id' => $fieldID, 'return' => 'label']);
      }
      catch (Exception $e) {}
    }

    if (!empty($this->conditionParams['original_data'])) {
      $field .= ' (original value)';
    }
    return htmlentities($this->conditionParams['entity']. '.' . $field . ' ' . ($this->getOperator())) . ' ' . htmlentities($value);
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['field']) && (substr($params['field'], 0, 7) === 'custom_')) {
      try {
        $fieldID = substr($params['field'], 7);
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'id' => $fieldID,
        ]);
        $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
          'id' => $customField['custom_group_id'],
        ]);
        unset($params['field']);
        $params['custom_field'] = $customGroup['name'];
        $params['custom_group'] = $customField['name'];
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return $params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importConditionParameters($condition_params = NULL) {
    if (!empty($condition_params['custom_group']) && !empty($condition_params['custom_field'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'name' => $condition_params['custom_field'],
          'custom_group_id' => $condition_params['custom_group'],
        ]);


        $condition_params['field'] = 'custom_'.$customField['id'];
        unset($condition_params['custom_field']);
        unset($condition_params['custom_group']);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
