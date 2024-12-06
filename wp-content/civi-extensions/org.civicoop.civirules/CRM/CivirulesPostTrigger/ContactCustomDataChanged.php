<?php
/**
 * @author Véronique Gratioulet <veronique.gratioulet@atd-quartmonde.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Trigger when an Contact Custom Data changes.
 * @fixme: This should probably extend CRM_Civirules_Trigger_Post
 */
class CRM_CivirulesPostTrigger_ContactCustomDataChanged extends CRM_Civirules_Trigger {

  /**
   * @return string
   */
  protected static function getObjectName() {
    return 'Contact';
  }

  /**
   * @return array
   */
  private static function getTriggers() {
    $get_called_class = get_called_class();
    $triggers = CRM_Civirules_BAO_Rule::findRulesByClassname($get_called_class);

    $contactTriggers = CRM_Civirules_BAO_Rule::findRulesByObjectNameAndOp($get_called_class::getObjectName(), 'edit');
    foreach ($contactTriggers as $trigger) {
      if ($trigger instanceof CRM_Civirules_Trigger_Post) {
        $triggers[] = $trigger;
      }
    }

    return $triggers;
  }

  /**
   * @return \CRM_Civirules_TriggerData_EntityDefinition
   */
  public function reactOnEntity() {
    $get_called_class = get_called_class();
    $objectName = $get_called_class::getObjectName();
    return new CRM_Civirules_TriggerData_EntityDefinition($objectName, $objectName, $get_called_class::getDaoClassName(), 'Contact');
  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    return $entities;
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Contact_DAO_Contact';
  }

  /**
   * @return array|string[]
   */
  protected static function getEntityExtensions() {
    $get_called_class = get_called_class();
    $objectName = $get_called_class::getObjectName();
    if ('Contact' == $objectName) {
      $entity_extensions = ['Contact', 'Individual', 'Organization', 'Household'];
    }
    else {
      $entity_extensions = [$objectName];
    }
    return $entity_extensions;
  }

  /**
   * @param string $op
   * @param int $groupID
   * @param int $entityID
   * @param array $params
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function custom($op, $groupID, $entityID, &$params) {
    $config = \Civi\CiviRules\Config\ConfigContainer::getInstance();
    $custom_group = $config->getCustomGroupById($groupID);
    $get_called_class = get_called_class();
    $objectName = $get_called_class::getObjectName();
    $entity_extensions = self::getEntityExtensions();
    if (!in_array($custom_group['extends'], $entity_extensions)) {
      return;
    }
    $contact = [];
    if (!empty($entityID)) {
      $contact = civicrm_api3('Contact', 'getsingle', ['id' => $entityID]);
      foreach ($params as $field) {
        if (!empty($field['custom_field_id'])) {
          $value = $field['value'];
          if ($field['type'] === 'Timestamp' && is_string($value)) {
            $date = \DateTime::createFromFormat('YmdHis', $value);
            $value = $date ? $date->format('Y-m-d H:i:s') : NULL;
          }
          $contact['custom_' . $field['custom_field_id']] = $value;
          $contact['custom_' . $field['custom_field_id'] . '_group_id'] = $field['custom_group_id'];
          $contact['custom_' . $field['custom_field_id'] . '_entry_id'] = $field['id'] ?? NULL;
        }
      }
    }

    $oldData = CRM_Civirules_Utils_PreData::getPreData($objectName, $entityID, 1);
    $triggerData = new CRM_Civirules_TriggerData_Edit('Contact', $entityID, $contact, $oldData);

    self::trigger($triggerData);
  }

  /**
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return void
   */
  protected static function trigger(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    //find matching rules for this objectName and op
    $triggers = self::getTriggers();
    foreach ($triggers as $trigger) {
      $trigger->alterTriggerData($triggerData);
      CRM_Civirules_Engine::triggerRule($trigger, $triggerData);
    }
  }

}
