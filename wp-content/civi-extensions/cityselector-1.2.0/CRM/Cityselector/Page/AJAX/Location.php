<?php
use CRM_Cityselector_ExtensionUtil as E;

class CRM_Cityselector_Page_AJAX_Location extends CRM_Core_Page {

  public static function jqCity() {
    CRM_Utils_JSON::output(CRM_Cityselector_BAO_Location::getChainCityValues($_GET['_value']));
  }

}
