<?php
/**
 * BAO RuleAction for CiviRule Rule Tag
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_BAO_CiviRulesRuleTag extends CRM_Civirules_DAO_RuleTag  {

  /**
   * Function to get values
   *
   * @return array $result found rows with data
   * @access public
   * @static
   */
  public static function getValues($params) {
    $result = array();
    $ruleTag = new CRM_Civirules_BAO_RuleTag();
    if (!empty($params)) {
      $fields = self::fields();
      foreach ($params as $key => $value) {
        if (isset($fields[$key])) {
          $ruleTag->$key = $value;
        }
      }
    }
    $ruleTag->find();
    while ($ruleTag->fetch()) {
      $row = array();
      self::storeValues($ruleTag, $row);
      if (!empty($row['rule_id']) && !empty($row['rule_tag_id'])) {
        $result[$row['id']] = $row;
      } else {
        //invalid ruleTag because there is no linked tag or rule
        CRM_Civirules_BAO_RuleTag::deleteWithId($row['id']);
      }
    }
    return $result;
  }

  /**
   * Deprecated unction to add or update rule tag
   *
   * @param array $params
   *
   * @return \CRM_Civirules_DAO_CiviRulesRuleTag
   * @throws Exception when params is empty
   *
   * @deprecated
   */
  public static function add($params) {
    CRM_Core_Error::deprecatedFunctionWarning('writeRecord');
    return self::writeRecord($params);
  }

  /**
   * Function to delete a rule tag with id
   *
   * @param int $ruleTagId
   * @throws Exception when ruleTagId is empty
   * @access public
   * @static
   */
  public static function deleteWithId($ruleTagId) {
    if (empty($ruleTagId)) {
      throw new Exception('rule tag id can not be empty when attempting to delete a civirule rule tag');
    }
    $ruleTag = new CRM_Civirules_BAO_RuleTag();
    $ruleTag->id = $ruleTagId;
    $ruleTag->delete();
  }

}
