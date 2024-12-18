<?php

/**
 * Trigger data
 * If you have custom triggers you can create a subclass of this class
 * and change where needed
 *
 */
abstract class CRM_Civirules_TriggerData_TriggerData {

  /**
   * Contains data for entities available in the trigger
   *
   * @var array
   */
  private array $entity_data = [];

  /**
   * Entity ID of the primary trigger data e.g. the activity id
   * @fixme: Add type int to property. We can't do this yet because delayed execution actions are stored in civicrm_queue_item serialized
   *   When unserialized PHP throws a fatal error because entity_id was a string in CiviRules < 3.6
   *
   * @var int
   */
  protected $entity_id = 0;

  /**
   * Entity name of the primary trigger data e.g. 'contact' or 'activity'
   *
   * @var string
   */
  protected $entity_name = NULL;

  /**
   * Contains data of custom fields.
   *
   * Takes the format of
   *   custom_field_id => id => value
   * where id is the is of the record in the custom_group set.
   *
   * @var array
   */
  private array $custom_data = [];

  /**
   * The Contact ID
   * @fixme: Add type int to property. We can't do this yet because delayed execution actions are stored in civicrm_queue_item serialized
   *   When unserialized PHP throws a fatal error because entity_id was a string in CiviRules < 3.6
   *
   * @var int
   */
  protected $contact_id = 0;

  /**
   * Is this Trigger being executed with a delay (set at runtime when executing actions)
   * @var bool
   */
  public bool $isDelayedExecution = FALSE;

  /**
   * The datetime (YmdHis) when the rule was triggered. Only set if we are delaying execution.
   * This will contain the original trigger time which can be used by actions (eg. to fill in an activity scheduled date).
   *
   * @var string
   */
  public string $delayedSubmitDateTime = '';

  /**
   * @var CRM_Civirules_Trigger
   */
  protected CRM_Civirules_Trigger $trigger;

  public function __construct() {
  }

  /**
   * @param $entity_id
   */
  public function setEntityId($entity_id) {
    $this->entity_id = $entity_id;
  }

  /**
   * @return Int
   */
  public function getEntityId() {
    if ($this->entity_id) {
      return $this->entity_id;
    } else if ($this->entity_data[$this->entity_name]['id'] ?? false) {
      return $this->entity_data[$this->entity_name]['id'];
    } else {
      return NULL;
    }
  }

  /**
   * Set which entity from the entitydata is the primary one EntityName (eg. Contact).
   * This MUST use the correct capitalisation eg. Contact, EntityTag
   *
   */
  public function setEntity($entity_name) {
    $this->entity_name = $entity_name;
  }


  /**
   * For triggers that have a "primary" entity return the EntityName (eg. Contact).
   * Otherwise return NULL
   * This MUST return the correct capitalisation eg. Contact, EntityTag
   *
   * @return null|string
   */
  public function getEntity() {
    return $this->entity_name;
  }

  /**
   * Set the trigger
   *
   * @param CRM_Civirules_Trigger $trigger
   */
  public function setTrigger(CRM_Civirules_Trigger $trigger) {
    $this->trigger = $trigger;
  }

  /**
   * @return CRM_Civirules_Trigger
   */
  public function getTrigger() {
    return $this->trigger;
  }

  /**
   * Returns the ID of the contact used in the trigger
   *
   * @return int
   */
  public function getContactId() {
    if (!empty($this->contact_id)) {
      return $this->contact_id;
    }
    if (!empty($this->entity_data['Contact']['id'])) {
      return $this->entity_data['Contact']['id'];
    }
    foreach($this->entity_data as $entity => $data) {
      if (!empty($data['contact_id'])) {
        return $data['contact_id'];
      }
    }
    return null;
  }

  public function setContactId($contact_id) {
    $this->contact_id = $contact_id;
  }

  /**
   * Returns an array with data for an entity
   *
   * If entity is not available then an empty array is returned
   *
   * @param string $entity
   * @return array
   */
  public function getEntityData($entity) {
    $validContacts = ['Contact', 'Organization', 'Individual', 'Household'];
    if (isset($this->entity_data[$entity]) && is_array($this->entity_data[$entity])) {
      return $this->entity_data[$entity];
    } elseif (isset($this->entity_data[strtolower($entity)]) && is_array($this->entity_data[strtolower($entity)])) {
      return $this->entity_data[strtolower($entity)];
    } elseif (in_array($entity, $validContacts) && $this->getContactId()) {
      $contactObject = new CRM_Contact_BAO_Contact();
      $contactObject->id = $this->getContactId();
      $contactData = [];
      if ($contactObject->find(true)) {
        CRM_Core_DAO::storeValues($contactObject, $contactData);
      }
      return $contactData;
    }
    return [];
  }

  /**
   * Method to return originalData if present
   *
   * @return array
   */
  public function getOriginalData() {
    if (isset($this->originalData)) {
      return $this->originalData;
    }
    else {
      return [];
    }
  }


  /**
   * Returns an array of custom fields in param format
   *
   * @return array
   */
  public function getEntityCustomData() {
    $customFields = [];
    if (!isset($this->custom_data)) {
      return $customFields;
    } elseif (!is_array($this->custom_data) ) {
      return $customFields;
    }
    foreach ($this->custom_data as $custom_field_id => $custom_field_value ) {
      $customFields['custom_' . $custom_field_id] = $this->getCustomFieldValue($custom_field_id);
    }
    return $customFields;
  }


  /**
   * Sets data for an entity
   *
   * @param string $entity
   * @param array $data
   * @param bool $is_primary
   */
  public function setEntityData($entity, $data, $is_primary = false) {
    if (is_array($data)) {
      $this->entity_data[$entity] = $data;
    }

    if ($is_primary) {
      $this->setEntity($entity);
    }
  }

  /**
   * Sets custom data into the trigger data
   * The custom data usually comes from within the pre hook where it is available
   *
   * @param int $custom_field_id
   * @param int $id id of the record in the database -1 for new ones
   * @param $value
   */
  public function setCustomFieldValue($custom_field_id, $id, $value) {
    $this->custom_data[$custom_field_id][$id] = $value;
  }

  /**
   * Returns an array of values for custom field
   *
   * @param $custom_field_id
   * @return array
   */
  public function getCustomFieldValues($custom_field_id) {
    if (isset($this->custom_data[$custom_field_id])) {
      return $this->custom_data[$custom_field_id];
    }
    return [];
  }

  /**
   * Returns value of a custom field.
   *
   * In case the custom group is a multirecord group the first record in the list is returned.
   *
   * @param $custom_field_id
   * @return mixed
   */
  public function getCustomFieldValue($custom_field_id) {
    if (!empty($this->custom_data[$custom_field_id])) {
      return reset($this->custom_data[$custom_field_id]);
    }
    return null;
  }

}
