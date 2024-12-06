<?php
/**
 * BAO Condition for CiviRule Condition
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_BAO_CiviRulesCondition extends CRM_Civirules_DAO_Condition {

  /**
   * Cache for CiviRule Condition ID - class name mapping
   *
   * @var array
   */
  protected static $conditionClassNames = [];

  /**
   * Function to get values
   *
   * @return array $result found rows with data
   * @access public
   * @static
   */
  public static function getValues($params) {
    $result = [];
    $condition = new CRM_Civirules_BAO_Condition();
    if (!empty($params)) {
      $fields = self::fields();
      foreach ($params as $key => $value) {
        if (isset($fields[$key])) {
          $condition->$key = $value;
        }
      }
    }
    $condition->find();
    while ($condition->fetch()) {
      $row = [];
      self::storeValues($condition, $row);
      $result[$row['id']] = $row;
    }
    return $result;
  }

  /**
   * Deprecated add or update condition
   *
   * @param array $params
   *
   * @return \CRM_Civirules_DAO_CiviRulesCondition
   * @throws Exception when params is empty
   *
   * @deprecated
   */
  public static function add($params) {
    CRM_Core_Error::deprecatedFunctionWarning('writeRecord');
    return self::writeRecord($params);
  }

  /**
   * Function to delete a condition with id
   *
   * @param int $conditionId
   * @throws Exception when conditionId is empty
   * @access public
   * @static
   */
  public static function deleteWithId($conditionId) {
    if (empty($conditionId)) {
      throw new Exception('condition id can not be empty when attempting to delete a civirule condition');
    }

    if (!CRM_Core_DAO::checkTableExists(self::getTableName())) {
      return;
    }

    //delete rows from rule_condition to prevent a foreign key constraint error
    CRM_Core_DAO::executeQuery("DELETE FROM `civirule_rule_condition` where `condition_id` = %1", [1 => [$conditionId, 'Integer']]);

    $condition = new CRM_Civirules_BAO_Condition();
    $condition->id = $conditionId;
    $condition->delete();
    return;
  }

  /**
   * Function to disable a condition
   *
   * @param int $conditionId
   * @throws Exception when conditionId is empty
   * @access public
   * @static
   */
  public static function disable($conditionId) {
    if (empty($conditionId)) {
      throw new Exception('condition id can not be empty when attempting to disable a civirule condition');
    }
    $condition = new CRM_Civirules_BAO_Condition();
    $condition->id = $conditionId;
    $condition->find(true);
    self::writeRecord(['id' => $condition->id, 'is_active' => 0]);
  }

  /**
   * Function to enable a condition
   *
   * @param int $conditionId
   * @throws Exception when conditionId is empty
   * @access public
   * @static
   */
  public static function enable($conditionId) {
    if (empty($conditionId)) {
      throw new Exception('condition id can not be empty when attempting to enable a civirule condition');
    }
    $condition = new CRM_Civirules_BAO_Condition();
    $condition->id = $conditionId;
    $condition->find(true);
    self::writeRecord(['id' => $condition->id, 'is_active' => 1]);
  }

  /**
   * Function to retrieve the label of a condition with conditionId
   *
   * @param int $conditionId
   * @return string $condition->label
   * @access public
   * @static
   */
  public static function getConditionLabelWithId($conditionId) {
    if (empty($conditionId)) {
      return '';
    }
    $condition = new CRM_Civirules_BAO_Condition();
    $condition->id = $conditionId;
    $condition->find(true);
    return $condition->label;
  }

  /**
   * Get the condition class for this condition
   *
   * @param int $conditionID
   * @param bool $abort if true this function will throw an exception if class could not be instanciated
   *
   * @return CRM_Civirules_Condition|FALSE
   * @throws Exception if abort is set to true and class does not exist or is not valid
   */
  public static function getConditionObjectById(int $conditionID, bool $abort=TRUE) {
    if (isset(self::$conditionClassNames[$conditionID])) {
      $className = self::$conditionClassNames[$conditionID];
    } else {
      $condition = new CRM_Civirules_BAO_Condition();
      $condition->id = $conditionID;
      if (!$condition->find(TRUE)) {
        if ($abort) {
          throw new Exception('CiviRule could not find condition');
        }
        return FALSE;
      }

      $className = $condition->class_name;
      if (!class_exists($className)) {
        if ($abort) {
          throw new Exception('CiviRule condition class "' . $className . '" does not exist');
        }
        return FALSE;
      }
      self::$conditionClassNames[$conditionID] = $className;
    }

    $object = new $className();
    if (!$object instanceof CRM_Civirules_Condition) {
      if ($abort) {
        throw new Exception('CiviRule condition class "' . $className . '" is not a subclass of CRM_Civirules_Condition');
      }
      return FALSE;
    }
    return $object;
  }
}
