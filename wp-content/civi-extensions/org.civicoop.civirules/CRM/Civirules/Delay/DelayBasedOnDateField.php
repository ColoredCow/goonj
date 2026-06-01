<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Civirules_Delay_DelayBasedOnDateField extends CRM_Civirules_Delay_Delay {

  protected $modifier;

  protected $amount;

  protected $unit;

  protected $entity;

  protected $field;

  /**
   * Returns the DateTime to which an action is delayed to
   *
   * @param DateTime $date
   * @param CRM_Civirules_TriggerData_TriggerData
   * @return DateTime
   */
  public function delayTo(DateTime $date, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $data = $triggerData->getEntityData($this->entity);
    // issue 163 ()
    $field = substr($this->field, strlen($this->entity)+1);
    $entity = $triggerData->getEntity();
    $data = $this->addInCustomField($field, $data, $entity);
    if (isset($data[$field]) && !empty($data[$field])) {
      $newDate = new DateTime($data[$field]);
      $newDate->modify($this->getModifyString());
      return $newDate;
    }
    return $date;
  }
  /**
   * Add custom data if needed and relevant
   *
   * @param string $field
   * @param array $data
   * @param string $entity
   * @return array
   */
  private function addInCustomField(string $field, array $data, string $entity): array {
    $customData = NULL;
    if ($field && strpos($field, "custom_") !== FALSE) {
      $customFieldId = (int) str_replace("custom_", "", $field);
      if ($customFieldId) {
        try {
          $customField = Civi\Api4\CustomField::get(FALSE)
            ->addSelect('custom_group_id:name', 'name')
            ->addWhere('id', '=', $customFieldId)
            ->setLimit(1)
            ->execute()->first();
          if ($customField['custom_group_id:name'] && $customField['name']) {
            $customFieldName = $customField['custom_group_id:name'] . '.' . $customField['name'];
            switch ($entity) {
              case "Activity":
                try {
                  $customData = Civi\Api4\Activity::get(FALSE)
                    ->addSelect($customFieldName)
                    ->addWhere('id', '=', $data['id'])
                    ->setLimit(1)
                    ->execute()->first();
                }
                catch (CRM_Core_Exception $ex) {
                }
                break;
              default:
                try {
                  $customData = Civi\Api4\Contact::get()
                    ->addSelect($customFieldName)
                    ->addWhere('id', '=', $data['id'])
                    ->setLimit(1)
                    ->execute()->first();
                }
                catch (CRM_Core_Exception $ex) {
                }
                break;
            }
            if ($customData[$customFieldName]) {
              $data[$field] = $customData[$customFieldName];
            }
          }
        }
        catch (CRM_Core_Exception $ex) {
        }
      }
    }
    return $data;
  }


  protected function getModifyString() {
    $modify = $this->modifier.$this->amount.' '.$this->unit;
    return $modify;
  }

  public function getDescription() {
    return ts('Base delay on date field in trigger');
  }

  public function getDelayExplanation() {
    $field = substr($this->field, strlen($this->entity)+1);
    return ts('%1 of %2.%3', array(1 => $this->getModifyString(), 2=>$this->entity,3=>$field));
  }

  public function addElements(CRM_Core_Form &$form, $prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    $form->add('select', $prefix.'modifier', ts('Modifier'), array('-' => ts('Before'), '+' => ts('After')));
    $form->add('text', $prefix.'amount', ts('Amount'));
    $form->add('select', $prefix.'unit', ts('Unit'), array(
      'days' => ts('Day(s)'),
      'months' => ts('Month(s)'),
      'weeks' => ts('Week(s)'),
    ));

    $triggerClass = CRM_Civirules_BAO_CiviRulesTrigger::getTriggerObjectByTriggerId($rule->trigger_id, true);
    $triggerClass->setTriggerId($rule->trigger_id);
    $triggerClass->setTriggerParams($rule->trigger_params ?? '');


    $form->add('select', $prefix.'entity', ts('Entity'), $this->getEntityOptions($triggerClass), true);
    $form->add('select', $prefix.'field', ts('Field'), $this->getFields($triggerClass), true, array('class' => 'crm-select2'));
  }

  /**
   * Validate the values and set error message in $errors
   *
   * @param array $values
   * @param array $errors
   * @param prefix - The prefix for the form field name
   * @param CRM_Civirules_BAO_CiviRulesRule $rule
   * @return void
   */
  public function validate($values, &$errors, $prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    if (empty($values[$prefix.'modifier'])) {
      $errors[$prefix.'modifier'] = ts('You need to select before or after');
    }
    if (empty($values[$prefix.'amount'])) {
      $errors[$prefix.'amount'] = ts('You need to specify');
    }
    if (empty($values[$prefix.'unit'])) {
      $errors[$prefix.'unit'] = ts('You need to select an unit');
    }
    if (empty($values[$prefix.'entity'])) {
      $errors[$prefix.'entity'] = ts('You need to select an entity');
    }
    if (empty($values[$prefix.'field'])) {
      $errors[$prefix.'field'] = ts('You need to select a field');
    }
  }

  /**
   * Set the values
   *
   * @param array $values
   * @param prefix - The prefix for the form field name
   * @param CRM_Civirules_BAO_CiviRulesRule $rule
   * @return void
   */
  public function setValues($values, $prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    $this->modifier = $values[$prefix.'modifier'];
    $this->amount = $values[$prefix.'amount'];
    $this->unit = $values[$prefix.'unit'];
    $this->entity = $values[$prefix.'entity'];
    $this->field = $values[$prefix.'field'];
  }

  /**
   * Get the values
   *
   * @param prefix - The prefix for the form field name
   * @param CRM_Civirules_BAO_Rule $rule
   * @return array
   */
  public function getValues($prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    $values = array();
    $values[$prefix.'modifier'] = $this->modifier;
    $values[$prefix.'amount'] = $this->amount;
    $values[$prefix.'unit'] = $this->unit;
    $values[$prefix.'entity'] = $this->entity;
    $values[$prefix.'field'] = $this->field;
    return $values;
  }

  protected function getEntityOptions(CRM_Civirules_Trigger $triggerClass) {
    $return = array();
    foreach($triggerClass->getProvidedEntities() as $entityDef) {
      if (!empty($entityDef->daoClass) && class_exists($entityDef->daoClass)) {
        $return[$entityDef->entity] = $entityDef->label;
      }
    }
    return $return;
  }

  protected function getFields(CRM_Civirules_Trigger $triggerClass) {
    $return = array();
    foreach($triggerClass->getProvidedEntities() as $entityDef) {
      if (!empty($entityDef->daoClass) && class_exists($entityDef->daoClass)) {
        $key = $entityDef->entity . '_';
        $className = $entityDef->daoClass;
        if (!is_callable(array($className, 'fields'))) {
          continue;
        }
        $fields = call_user_func(array($className, 'fields'));
        foreach ($fields as $field) {
          if (!($field['type'] & CRM_Utils_Type::T_DATE)) {
            continue; //Field is not a Date field.
          }

          $fieldKey = $key . $field['name'];
          if (isset($field['title'])) {
            $label = trim($field['title']);
          } else {
            $label = "";
          }
          if (empty($label)) {
            $label = $field['name'];
          }
          $return[$fieldKey] = $label;
        }
        $customFields = $this->getCustomDatefieldsForEntity($entityDef->entity);
        foreach($customFields as $customFieldKey => $customFieldLabel) {
          $return[$key.$customFieldKey] = $customFieldLabel;
        }
      }
    }
    return $return;
  }

  protected function getCustomDatefieldsForEntity($entity) {
    $extends = array($entity);
    if ($entity == 'Contact') {
      $contact_types = civicrm_api3('ContactType', 'get', array());
      foreach($contact_types['values'] as $type) {
        $extends[] = $type['name'];
      }
    }

    $return = array();
    $processedGroups = array();
    foreach($extends as $extend) {
      $customGroups = civicrm_api3('CustomGroup', 'get', array('extends' => $extend));
      foreach($customGroups['values'] as $customGroup) {
        if (in_array($customGroup['id'], $processedGroups)) {
          continue;
        }
        if (!empty($customGroup['is_multiple'])) {
          //do not include multiple custom groups
          continue;
        }
        $return = $return + $this->getCustomDateFieldPerGroup($customGroup['id'], $customGroup['title']);
        $processedGroups[] = $customGroup['id'];
      }
    }

    return $return;
  }

  /**
   * Get custom date fields from the specified custom group.
   *
   * @param int $group_id custom group id
   * @param string $group_label custom group label
   * @return array
   */
  protected function getCustomDateFieldPerGroup($group_id, $group_label) {
    $fields = civicrm_api3('CustomField', 'get', [
      'custom_group_id' => $group_id,
      'data_type' => 'Date',
      'options' => ['limit' => 0],
    ]);
    $return = [];
    foreach ($fields['values'] as $field) {
      $key = 'custom_' . $field['id'];
      $return[$key] = $group_label . ': ' . $field['label'];
    }
    return $return;
  }

}
