<?php
/**
 * BAO Trigger for CiviRule Trigger
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_BAO_CiviRulesTrigger extends CRM_Civirules_DAO_Trigger {

  /**
   * Function to get values
   *
   * @return array $result found rows with data
   * @access public
   * @static
   */
  public static function getValues($params) {
    $result = [];
    $trigger = new CRM_Civirules_BAO_Trigger();
    if (!empty($params)) {
      $fields = self::fields();
      foreach ($params as $key => $value) {
        if (isset($fields[$key])) {
          $trigger->$key = $value;
        }
      }
    }
    $trigger->find();
    while ($trigger->fetch()) {
      $row = [];
      self::storeValues($trigger, $row);
      $result[$row['id']] = $row;
    }
    return $result;
  }

  /**
   * Deprecated function to add or update trigger
   *
   * @param array $params
   *
   * @return \CRM_Civirules_DAO_CiviRulesTrigger
   * @throws Exception when params is empty
   *
   * @deprecated
   */
  public static function add($params) {
    CRM_Core_Error::deprecatedFunctionWarning('writeRecord');
    return self::writeRecord($params);
  }

  /**
   * Function to delete a trigger with id
   *
   * @param int $triggerId
   * @throws Exception when triggerId is empty
   */
  public static function deleteWithId($triggerId) {
    if (empty($triggerId)) {
      throw new Exception('trigger id can not be empty when attempting to delete a civirule trigger');
    }
    $trigger = new CRM_Civirules_BAO_Trigger();
    $trigger->id = $triggerId;
    $trigger->delete();
  }

  /**
   * Function to disable a trigger
   *
   * @param int $triggerId
   * @throws Exception when triggerId is empty
   */
  public static function disable($triggerId) {
    if (empty($triggerId)) {
      throw new Exception('trigger id can not be empty when attempting to disable a civirule trigger');
    }
    $trigger = new CRM_Civirules_BAO_Trigger();
    $trigger->id = $triggerId;
    $trigger->find(true);
    self::writeRecord(['id' => $trigger->id, 'is_active' => 0]);
  }

  /**
   * Function to enable a trigger
   *
   * @param int $triggerId
   * @throws Exception when triggerId is empty
   */
  public static function enable($triggerId) {
    if (empty($triggerId)) {
      throw new Exception('trigger id can not be empty when attempting to enable a civirule trigger');
    }
    $trigger = new CRM_Civirules_BAO_Trigger();
    $trigger->id = $triggerId;
    $trigger->find(true);
    self::writeRecord(['id' => $trigger->id, 'is_active' => 1]);
  }

  /**
   * Function to retrieve the label of an eva triggerent with triggerId
   *
   * @param int $triggerId
   * @return string $trigger->label
   */
  public static function getTriggerLabelWithId($triggerId) {
    if (empty($triggerId)) {
      return '';
    }
    $trigger = new CRM_Civirules_BAO_Trigger();
    $trigger->id = $triggerId;
    $trigger->find(true);
    return $trigger->label;
  }

  /**
   * Get the trigger class based on class name or on objectName
   *
   * @param $className
   * @param bool $abort
   *
   * @return \CRM_Civirules_Trigger|false
   * @throws \Exception if abort is set to true and class does not exist or is not valid
   */
  public static function getPostTriggerObjectByClassName($className, bool $abort=TRUE) {
    if (empty($className)) {
      $className = 'CRM_Civirules_Trigger_Post';
    }
    return self::getTriggerObjectByClassName($className, $abort);
  }

  /**
   * Get the trigger class for this trigger
   *
   * @param $className
   * @param bool $abort if true this function will throw an exception if class could not be instantiated
   *
   * @return \CRM_Civirules_Trigger|false
   * @throws \Exception if abort is set to true and class does not exist or is not valid
   */
  public static function getTriggerObjectByClassName($className, bool $abort=TRUE) {
    if (!class_exists($className)) {
      if ($abort) {

        throw new Exception('CiviRule trigger class "' . $className . '" does not exist');
      }
      return FALSE;
    }

    $object = new $className();
    if (!$object instanceof CRM_Civirules_Trigger) {
      if ($abort) {
        throw new Exception('CiviRule trigger class "' . $className . '" is not a subclass of CRM_Civirules_Trigger');
      }
      return FALSE;
    }
    return $object;
  }

  /**
   * @param int $triggerId
   * @param bool $abort
   *
   * @return \CRM_Civirules_Trigger|false
   * @throws \Civi\Core\Exception\DBQueryException
   */
  public static function getTriggerObjectByTriggerId($triggerId, bool $abort=TRUE) {
    $sql = "SELECT t.*
            FROM `civirule_trigger` t
            WHERE t.`is_active` = 1 AND t.id = %1";

    $params[1] = [$triggerId, 'Integer'];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    if ($dao->fetch()) {
      if (!empty($dao->object_name) && !empty($dao->op) && empty($dao->cron)) {
        return self::getPostTriggerObjectByClassName($dao->class_name, $abort);
      } elseif (!empty($dao->class_name)) {
        return self::getTriggerObjectByClassName($dao->class_name, $abort);
      }
    }

    if ($abort) {
      throw new Exception('Could not find trigger with ID: '.$triggerId);
    }
    return FALSE;
  }

  /**
   * Method to check if a trigger exists with class_name or object_name/op
   *
   * @param array $params
   *
   * @return bool
   */
  public static function triggerExists($params) {
    if (isset($params['class_name']) && !empty($params['class_name'])) {
      $checkParams['class_name'] = $params['class_name'];
    } else {
      if (isset($params['object_name']) && isset($params['op']) && !empty($params['object_name']) && !empty($params['op'])) {
        $checkParams['object_name'] = $params['object_name'];
        $checkParams['op'] = $params['op'];
      }
    }
    if (!empty($checkParams)) {
      $foundTriggers = self::getValues($checkParams);
      if (!empty($foundTriggers)) {
        return TRUE;
      }
    }
    return FALSE;
  }
}
