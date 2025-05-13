<?php

class CRM_Civiglific_Page_RuleMapping extends CRM_Core_Page {
    public function run() {
        error_log('RuleMapping page is running');
        CRM_Utils_System::setTitle(ts('Rule Mapping Page'));
        parent::run(); // this will render RuleMapping.tpl
      }
}
