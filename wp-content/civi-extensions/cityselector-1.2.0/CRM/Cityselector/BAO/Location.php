<?php
use CRM_Cityselector_ExtensionUtil as E;

class CRM_Cityselector_BAO_Location {

  public static function getChainCityValues($parent_id = NULL, $flatten = FALSE) {
    $parent = \Civi::settings()->get('cityselector_parent');
    if (!$parent) {
      CRM_Core_Session::setStatus(E::ts('The cityselector settings has not been configured.'), '', 'error');
      return;
    }

    $parent_fieldname = $parent . "_id";
    $cities = [];
    $sql = "SELECT * FROM `civicrm_city`";
    if (!empty($parent_id)) {
      $sql .= " WHERE `{$parent_fieldname}` = {$parent_id} ";
    }
    $sql .= " ORDER BY `name`; ";
    $dao = CRM_Core_DAO::executeQuery($sql, []);

    while ($dao->fetch()) {
      // Format for quickform
      if ($flatten) {
        $cities[$dao->name] = $dao->name;
      }
      // Format for js
      else {
        $cities[] = ["value" => $dao->name, "key" => $dao->name];
      }
    }

    return $cities;
  }

  /**
   * When the cityselector parent settings is updated
   * the table needs to be created
   */
  public static function onChangeSettingParent($oldValue, $newValue, $metadata) {
    if ($newValue == 'county') {
      $install_sql = 'install_by_county.sql';
    }
    elseif ($newValue == 'state_province') {
      $install_sql = 'install_by_province.sql';
    }
    else {
      CRM_Core_Session::setStatus(E::ts('The cityselector parent has an invalid value and the setup has failed'), '', 'error');
      return;
    }

    try {
      CRM_Cityselector_Upgrader_Base::instance()->executeSqlFile("sql/{$install_sql}");
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts('The cityselector setup has failed. Please contact the SysAdmin'), '', 'error');
    }
    CRM_Core_Session::setStatus(E::ts('The cityselector parent setting has been configured. Remember to populate the `civicrm_city` table with your list of cities!'), '', 'success');
  }

  /**
   * Validates that the parent has not been defined already
   */
  public static function onValidateSettingParent($parent) {
    $parent_setting = \Civi::settings()->get('cityselector_parent');
    if (!empty($parent_setting) && ($parent_setting != $parent)) {
      CRM_Core_Session::setStatus(E::ts('The cityselector parent field has already been selected. It cannot been changed now, please contact the SysAdmin for more info.'), '', 'error');
      return FALSE;
    }
    return TRUE;
  }

}
