<?php
/**
 * Utils - class with generic functions CiviRules
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_Utils {

  /**
   * Function return display name of contact retrieved with contact_id
   *
   * @param int $contactId
   * @return string $contactName
   * @access public
   * @static
   */
  public static function getContactName($contactId) {
    if (empty($contactId)) {
      return '';
    }
    $params = array(
      'id' => $contactId,
      'return' => 'display_name');
    try {
      $contactName = civicrm_api3('Contact', 'Getvalue', $params);
    } catch (CRM_Core_Exception $ex) {
      $contactName = '';
    }
    return $contactName;
  }

  /**
   * Function to format is_active to yes/no
   *
   * @param int $isActive
   * @return string
   * @access public
   * @static
   */
  public static function formatIsActive($isActive) {
    if ($isActive == 1) {
      return ts('Yes');
    } else {
      return ts('No');
    }
  }

  /**
   * Helper function to generate a formatted contact link/name
   *
   * @param $contactId
   * @param $contactName
   *
   * @return string
   */
  public static function formatContactLink($contactId, $contactName) {
    if (empty($contactId)) {
      return NULL;
    }

    if (empty($contactName)) {
      return $contactId;
    }

    $hasViewContact = CRM_Contact_BAO_Contact_Permission::allow($contactId);

    if ($hasViewContact) {
      $contactViewUrl = CRM_Utils_System::url("civicrm/contact/view", "reset=1&cid={$contactId}");
      return "<a href=\"{$contactViewUrl}\">" . $contactName . "</a>";
    }
    else {
      return $contactName;
    }
  }

  /**
   * Public function to generate name from label
   *
   * @param $label
   * @return string
   * @access public
   * @static
   */
  public static function buildNameFromLabel($label) {
    $labelParts = explode(' ', strtolower($label));
    $nameString = implode('_', $labelParts);
    return substr($nameString, 0, 80);
  }

  /**
   * Public function to generate label from name
   *
   * @param $name
   * @return string
   * @access public
   * @static
   */
  public static function buildLabelFromName($name) {
    $labelParts = array();
    $nameParts = explode('_', strtolower($name));
    foreach ($nameParts as $namePart) {
      $labelParts[] = ucfirst($namePart);
    }
    return implode(' ', $labelParts);
  }

  /**
   * Function to return activity status list
   *
   * @return array $activityStatusList
   * @access public
   */
  public static function getActivityStatusList() {
    $activityStatusList = array();
    $activityStatusOptionGroupId = self::getOptionGroupIdWithName('activity_status');
    $params = array(
      'option_group_id' => $activityStatusOptionGroupId,
      'is_active' => 1,
      'options' => array('limit' => 0));
    $activityStatuses = civicrm_api3('OptionValue', 'Get', $params);
    foreach ($activityStatuses['values'] as $optionValue) {
      $activityStatusList[$optionValue['value']] = $optionValue['label'];
    }
    return $activityStatusList;
  }

  /**
   * Function to return activity type list
   *
   * @return array $activityTypeList
   * @access public
   */
  public static function getActivityTypeList() {
    $activityTypeList = array();
    $activityTypeOptionGroupId = self::getOptionGroupIdWithName('activity_type');
    $params = array(
      'option_group_id' => $activityTypeOptionGroupId,
      'is_active' => 1,
      'options' => array('limit' => 0));
    $activityTypes = civicrm_api3('OptionValue', 'Get', $params);
    foreach ($activityTypes['values'] as $optionValue) {
      $activityTypeList[$optionValue['value']] = $optionValue['label'];
    }
    return $activityTypeList;
  }

  /**
   * Function to return campaign type list
   *
   * @return array $campaignTypeList
   * @access public
   * @throws
   */
  public static function getCampaignTypeList() {
    $campaignTypeList = [];
    $campaignTypeOptionGroupId = self::getOptionGroupIdWithName('campaign_type');
    $params = [
      'option_group_id' => $campaignTypeOptionGroupId,
      'is_active' => 1,
      'options' => ['limit' => 0],
      ];
    $campaignTypes = civicrm_api3('OptionValue', 'get', $params);
    foreach ($campaignTypes['values'] as $optionValue) {
      $campaignTypeList[$optionValue['value']] = $optionValue['label'];
    }
    return $campaignTypeList;
  }

  /**
   * Function to return campaign status list
   *
   * @return array $campaignStatusList
   * @access public
   * @throws
   */
  public static function getCampaignStatusList() {
    $campaignStatusList = [];
    $campaignStatusOptionGroupId = self::getOptionGroupIdWithName('campaign_status');
    $params = [
      'option_group_id' => $campaignStatusOptionGroupId,
      'is_active' => 1,
      'options' => ['limit' => 0],
      ];
    $campaignStatus = civicrm_api3('OptionValue', 'get', $params);
    foreach ($campaignStatus['values'] as $optionValue) {
      $campaignStatusList[$optionValue['value']] = $optionValue['label'];
    }
    return $campaignStatusList;
  }

  /**
   * Function to get the option group id of an option group with name
   *
   * @param string $optionGroupName
   * @return int $optionGroupId
   * @throws Exception when no option group activity_type is found
   */
  public static function getOptionGroupIdWithName($optionGroupName) {
    $params = array(
      'name' => $optionGroupName,
      'return' => 'id');
    try {
      $optionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $params);
    } catch (CRM_Core_Exception $ex) {
      throw new Exception('Could not find an option group with the name '.$optionGroupName.
        ', error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
    return $optionGroupId;
  }

  /**
   * Function to get option label with value and option group id
   *
   * @param int $optionGroupId
   * @param mixed $optionValue
   * @return array|bool
   * @access public
   * @static
   */
  public static function getOptionLabelWithValue($optionGroupId, $optionValue) {
    if (empty($optionGroupId) or empty($optionValue)) {
      return FALSE;
    } else {
      $params = array(
        'option_group_id' => $optionGroupId,
        'value' => $optionValue,
        'return' => 'label'
      );
      try {
        return civicrm_api3('OptionValue', 'Getvalue', $params);
      } catch (CRM_Core_Exception $ex) {
        return false;
      }
    }
  }

  /**
   * Method to get the contribution status id with name
   *
   * @param string $statusName
   * @return int $statusId
   * @access public
   * @throws Exception when error from API
   * @static
   */
  public static function getContributionStatusIdWithName($statusName) {
    $optionGroupId = self::getOptionGroupIdWithName('contribution_status');
    $optionValueParams = array(
      'option_group_id' => $optionGroupId,
      'name' => $statusName,
      'return' => 'value');
    try {
      $statusId = (int) civicrm_api3('OptionValue', 'Getvalue', $optionValueParams);
    } catch (CRM_Core_Exception $ex) {
      throw new Exception('Could not retrieve a contribution status with name '.
        $statusName.', contact your system administrator. Error from API OptionValue Getvalue: '.$ex->getMessage());
    }
    return $statusId;
  }

  /**
   * Method to get the financial types
   * @return array
   */
  public static function getFinancialTypes() {
    $return = array();
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_financial_type` where `is_active` = 1");
    while($dao->fetch()) {
      $return[$dao->id] = $dao->name;
    }
    return $return;
  }

  /**
   * Method to get the membership types
   * @param bool $onlyActive
   * @return array
   */
  public static function getRelationshipTypes($onlyActive = TRUE) {
    $return = array();
    if ($onlyActive) {
      $params = array('is_active' => 1);
    } else {
      $params = array();
    }
    $params['options'] = array('limit' => 0);
    try {
      $relationshipTypes = civicrm_api3("RelationshipType", "Get", $params);
      foreach ($relationshipTypes['values'] as $relationshipType) {
        $return['a_b_' . $relationshipType['id']] = $relationshipType['label_a_b'] . ' (A-B)';
        $return['b_a_' . $relationshipType['id']] = $relationshipType['label_b_a'] . ' (B-A)';
      }
    } catch (CRM_Core_Exception $ex) {}
    asort($return);
    return $return;
  }

  /**
   * Method to get the membership types
   * @param bool $onlyActive
   * @return array
   */
  public static function getMembershipTypes($onlyActive = TRUE) {
    $membershipTypes = [];

    try {
      $membershipTypesAPI = \Civi\Api4\MembershipType::get(FALSE)
        ->addOrderBy('name', 'ASC');
      if ($onlyActive) {
        $membershipTypesAPI->addWhere('is_active', '=', TRUE);
      }
      $membershipTypes = $membershipTypesAPI
        ->execute()
        ->indexBy('id')
        ->column('name');
    } catch (Exception $e) {
      \Civi::log('civirules')->error('Error getting membership types: ' . $e->getMessage());
    }
    return $membershipTypes;
  }

  /**
   * Method to get the membership status
   * @param bool $onlyActive
   *
   * @return array
   */
  public static function getMembershipStatus(bool $onlyActive = TRUE): array {
    $membershipStatuses = [];

    try {
      $membershipStatusesAPI = \Civi\Api4\MembershipStatus::get(FALSE)
        ->addSelect('id', 'label');
      if ($onlyActive) {
        $membershipStatusesAPI->addWhere('is_active', '=', TRUE);
      }
      $membershipStatuses = $membershipStatusesAPI
        ->execute()
        ->indexBy('id')
        ->column('label');
    } catch (Exception $e) {
      \Civi::log('civirules')->error('Error getting membership statuses: ' . $e->getMessage());
    }
    return $membershipStatuses;
  }

  /**
   * Method to get the payment processors
   * @param bool $live
   *
   * @return array
   */
  public static function getPaymentProcessors($live = TRUE) {
    $return = [];
    if ($live) {
      $params = ['is_test' => 0];
    } else {
      $params = ['is_test' => 1];
    }
    $params['options'] = ['limit' => 0, 'sort' => "name ASC"];
    try {
      $paymentProcessors = civicrm_api3("PaymentProcessor", "Get", $params);
      foreach ($paymentProcessors['values'] as $paymentProcessor) {
        $return[$paymentProcessor['id']] = $paymentProcessor['name'];
      }
    } catch (CRM_Core_Exception $ex) {}
    return $return;
  }

  /**
   * Method to check if the incoming date is later than today
   *
   * @param mixed $inDate
   * @return boolean
   * @access public
   * @static
   */
  public static function endDateLaterThanToday($inDate) {
    $isLater = FALSE;
    try {
      $dateToBeChecked = new DateTime($inDate);
      $now = new DateTime();
      if ($dateToBeChecked > $now) {
        $isLater = TRUE;
      }
    } catch (Exception $ex) {}
    return $isLater;
  }

  /**
   * Method to calculate maximum menu key for navigationMenu hook
   *
   * @param $menuArray
   * @return mixed
   */
  public static function getMenuKeyMax($menuArray) {
    $max = array(max(array_keys($menuArray)));
    foreach($menuArray as $v) {
      if (!empty($v['child'])) {
        $max[] = self::getMenuKeyMax($v['child']);
      }
    }
    return max($max);
  }

  /**
   * Method to get the activity type list
   *
   * @return array
   */
  public static function getCampaignList() {
    $campaignList = array();
    try {
      $campaigns = civicrm_api3('Campaign', 'get', array(
        'sequential' => 1,
        'is_active' => 1,
        'options' => array('limit' => 0),
      ));
      foreach ($campaigns['values'] as $campaign) {
        if (isset($campaign['title'])) {
          $campaignList[$campaign['id']] = $campaign['title'];
        }
        else {
          $campaignList[$campaign['id']] = ts('(no title)');
        }
      }
      asort($campaignList);
    }
    catch (CRM_Core_Exception $ex) {
      $campaignList = array();
    }
    return $campaignList;
  }

  /**
   * Function to return event type list
   *
   * @return array $eventTypeList
   * @access public
   */
  public static function getEventTypeList() {
    $eventTypeList = array();
    $eventTypeOptionGroupId = self::getOptionGroupIdWithName('event_type');
    $params = array(
      'option_group_id' => $eventTypeOptionGroupId,
      'is_active' => 1,
      'options' => array('limit' => 0));
    $eventTypes = civicrm_api3('OptionValue', 'Get', $params);
    foreach ($eventTypes['values'] as $optionValue) {
      $eventTypeList[$optionValue['value']] = $optionValue['label'];
    }
    return $eventTypeList;
  }

  /**
   * Function to return scheduled reminder list
   *
   * @return array $scheduledReminderList
   * @access public
   */
  public static function getScheduledReminderList() {
    $scheduledReminderList = [];
    $reminders = CRM_Core_BAO_ActionSchedule::getList();
    foreach ($reminders as $reminder) {
      $scheduledReminderList[$reminder['id']] = $reminder['title'];
    }
    return $scheduledReminderList;
  }

  /**
   * Method to set the date operator options
   *
   * @return array
   */
  public static function getActivityDateOperatorOptions() {
    return array(
      'equals',
      'later than',
      'later than or equal',
      'earlier than',
      'earlier than or equal',
      'not equal',
      'between',
    );
  }

  /**
   * Method to set the generic comparison operators
   *
   * @return array
   */
  public static function getGenericComparisonOperatorOptions() {
    return array(
      'equals',
      'greater than',
      'greater than or equal',
      'less than',
      'less than or equal',
      'not equal',
    );
  }

  /**
   * Method to get the civirules base path
   *
   * @return string
   * @throws CRM_Core_Exception
   */
  public static function getCivirulesPath() {
    $container = CRM_Extension_System::singleton()->getFullContainer();
    return $container->getPath('org.civicoop.civirules');
  }


  /**
   * Reads a part of the rule into an array to make it comparable with
   * other rules. Used to determine of both rules are clones of each other,
   * rules with the same actions
   *
   * @param $ruleId
   *
   * @return array
   */
  public static function ruleCompareFormat($ruleId, $triggerId = NULL) {

    $result = [];
    if (!$triggerId) {
      $triggerId = civicrm_api3('CiviRuleRule', 'getvalue', [
        'id' => $ruleId,
        'return' => 'trigger_id'
      ]);
    }
    $result['triggerId'] = $triggerId;

    $dao = CRM_Core_DAO::executeQuery('SELECT condition_link,condition_id,condition_params,is_active FROM civirule_rule_condition WHERE rule_id = %1 ORDER BY id', [
      1 => [$ruleId, 'Integer']
    ]);

    $result ['conditions'] = [];
    while ($dao->fetch()) {
      $result ['conditions'][] = [
        'condition_link' => $dao->condition_link,
        'condition_id' => $dao->condition_id,
        'condition_params' => $dao->condition_params,
        'is_active' => $dao->is_active,
      ];
    };

    $dao = CRM_Core_DAO::executeQuery('SELECT action_id ,action_params, delay, ignore_condition_with_delay, is_active FROM civirule_rule_action WHERE rule_id = %1 ORDER BY id', [
      1 => [$ruleId, 'Integer']
    ]);
    $result['actions'] = [];
    while ($dao->fetch()){
      $result ['actions'][] = [
        'action_id' => $dao->action_id,
        'action_params' => $dao->action_params,
        'delay' => $dao->delay,
        'ignore_condition_with_delay' => $dao->ignore_condition_with_delay,
        'is_active' => $dao->is_active,
        ];
    }
    return $result;
  }

  /**
   * Method om dao in array te stoppen en de 'overbodige' data er uit te slopen
   *
   * @param  $dao
   * @return array
   */
  public static function moveDaoToArray($dao) {
    $ignores = array('N', 'id', 'entity_id');
    $columns = get_object_vars($dao);
    // first remove all columns starting with _
    foreach ($columns as $key => $value) {
      if (substr($key, 0, 1) == '_') {
        unset($columns[$key]);
      }
      if (in_array($key, $ignores)) {
        unset($columns[$key]);
      }
    }
    return $columns;
  }

  /**
   * Returns the object name of a certain object.
   * When the object is contact it will try to retrieve the contact type
   * and use this as the object name.
   *
   * @param \CRM_Core_DAO $object
   *
   * @return array|string|NULL
   */
  public static function getObjectNameFromObject(\CRM_Core_DAO $object)
  {
    static $contact_types = []; // Array with contact ID and value the contact type.
    $tableName = $object->getTableName();
    if (empty($tableName)) {
      return NULL;
    }
    $objectName = CRM_Core_DAO_AllCoreTables::getEntityNameForTable($object->getTableName());
    if ($objectName == 'Contact' && isset($object->contact_type)) {
      $objectName = $object->contact_type;
    } elseif ($objectName == 'Contact' && isset($contact_types[$object->id])) {
      $objectName = $contact_types[$object->id];
    } elseif ($objectName == 'Contact' && isset($object->id)) {
      try {
        $contact_types[$object->id] = civicrm_api3('Contact', 'getvalue', ['return' => 'contact_type', 'id' => $object->id]);
        $objectName = $contact_types[$object->id];
      } catch (\Exception $e) {
        // Do nothing
      }
    }
    return $objectName;
  }

  /**
   * Method to check if Api4 is active in the current installation
   *
   * @return bool
   * @deprecated
   */
  public static function isApi4Active() {
    if (function_exists('civicrm_api4')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the history of when the rule was triggered. Returns an array in reverse date order
   *
   * @param int $ruleID
   * @param int $count
   *
   * @return array
   */
  public static function getRuleLogLatestTriggerDetail($ruleID, $count = 1) {
    $triggerHistory = [];
    $sql = "SELECT log_date, contact_id, sort_name
    FROM civirule_rule_log crl
    LEFT JOIN civicrm_contact cc ON cc.id = crl.contact_id
    WHERE rule_id = %1
    ORDER BY log_date DESC LIMIT %2";
    $queryParams = [
      1 => [$ruleID, 'Integer'],
      2 => [$count, 'Integer']
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $queryParams);
    while ($dao->fetch()) {
      $triggerHistory[] = [
        'last_trigger_date' => $dao->log_date ?? '',
        'last_trigger_contactid' => $dao->contact_id ?? '',
        'last_trigger_contactname' => $dao->sort_name ?? '',
        'last_trigger_contact_link' => CRM_Civirules_Utils::formatContactLink($dao->contact_id ?? '', $dao->sort_name ?? '')
      ];
    }
    return $triggerHistory;
  }

  /**
   * Method to get the group title with group id
   *
   * @param int $groupId
   * @return array|int|mixed
   */
  public static function getGroupTitleWithId(int $groupId) {
    if (function_exists('civicrm_api4')) {
      try {
        $groups = \Civi\Api4\Group::get()
          ->addSelect('title')
          ->addWhere('id', '=', $groupId)
          ->execute();
        $group = $groups->first();
        if ($group['title']) {
          return $group['title'];
        }
      }
      catch (CRM_Core_Exception $ex) {
      }
    }
    else {
      try {
        return civicrm_api3('Group', 'getvalue', [
          'return' => 'title',
          'id' => $groupId,
        ]);
      }
      catch (CRM_Core_Exception $ex) {
      }
    }
    return $groupId;
  }
  /**
   * Method to get the periods available
   *
   * @return string[]
   */
  public static function getPeriods() {
    return ['years', 'quarters', 'months', 'weeks', 'days'];
  }

  /**
   * Method to get the list of active groups
   *
   * @return array
   */
  public static function getGroupList() {
    $groups = \Civi\Api4\Group::get(FALSE)
      ->addSelect('id', 'title')
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_hidden', '=', FALSE)
      ->execute();
    $groupList = [];
    foreach ($groups as $group) {
      $groupList[$group['id']] = $group['title'];
    }
    asort($groupList);
    return $groupList;
  }

  /**
   * Method to get list of active frequency units
   *
   * @return array
   */
  public static function getFrequencyUnits() {
    $optionGroupName = "recur_frequency_units";
    $units = [];
    if (function_exists('civicrm_api4')) {
      try {
        $optionValues = \Civi\Api4\OptionValue::get()
          ->addSelect('label', 'value')
          ->addWhere('option_group_id:name', '=', $optionGroupName)
          ->addWhere('is_active', '=', TRUE)
          ->execute();
        foreach ($optionValues as $optionValue) {
          $units[$optionValue['value']] = $optionValue['label'];
        }
      }
      catch (CRM_Core_Exception $ex) {
      }
    }
    else {
      try {
        $result = civicrm_api3('OptionValue', 'get', [
          'options' => ['limit' => 0],
          'sequential' => 1,
          'option_group_id' => $optionGroupName,
          'is_active' => TRUE,
          'return' => ['label', 'value']
        ]);
        foreach ($result['values'] as $optionValue) {
          $units[$optionValue['value']] = $optionValue['label'];
        }
      }
      catch (CRM_Core_Exception $ex) {
      }
    }
    return $units;
  }

  /**
   * Method to get event title with name
   *
   * @param int $eventId
   * @return array|false|mixed|null
   */
  public static function getEventTitle(int $eventId) {
    if (!empty($eventId)) {
      if (function_exists('civicrm_api4')) {
        try {
          $events = \Civi\Api4\Event::get()
            ->addSelect('title')
            ->addWhere('id', '=', $eventId)
            ->execute();
          $event = $events->first();
          if ($event['title']) {
            return $event['title'];
          }
        }
        catch (CRM_Core_Exception $ex) {
        }
      }
      else {
        try {
          $eventTitle = civicrm_api3('Event', 'getvalue', [
            'id' => $eventId,
            'return' => 'title',
          ]);
          if ($eventTitle) {
            return $eventTitle;
          }
        }
        catch (CRM_Core_Exception $ex) {
        }

      }
    }
    return NULL;
  }

  /**
   * Method to get participant status label
   *
   * @param int $participantStatusId
   * @return array|false|mixed|null
   */
  public static function getParticipantStatusLabel(int $participantStatusId) {
    if (!empty($participantStatusId)) {
      try {
        $label = civicrm_api3('ParticipantStatusType', 'getvalue', [
          'id' => $participantStatusId,
          'return' => 'label',
        ]);
        if ($label) {
          return $label;
        }
      }
      catch (CRM_Core_Exception $ex) {
      }
    }
    return NULL;
  }

  /**
   * Method to get contact types
   *
   * @return array
   */
  public static function getContactTypes($parent_operator = 'IS NULL', $parent_value = null) {
    return \Civi\Api4\ContactType::get(FALSE)
      ->addSelect('label', 'name')
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('parent_id', $parent_operator, $parent_value)
      ->execute()
      ->indexBy('name')
      ->column('label');
  }

  /**
   * @return int|null
   */
  public static function getCancelledMembershipStatusId(): ?int {
    $statusId = NULL;
    try {
      $membershipStatus = \Civi\Api4\MembershipStatus::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', '=', 'Cancelled')
        ->execute()->first();
      if ($membershipStatus['id']) {
        $statusId = $membershipStatus['id'];
      }
    }
    catch (\CRM_Core_Exception $ex) {
    }
    return $statusId;
  }

}

