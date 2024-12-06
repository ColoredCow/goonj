<?php

use CRM_Civirules_ExtensionUtil as E;

/**
 * Form controller class for the CiviRules view
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 21 Mar 2019
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_Form_RuleView extends CRM_Core_Form {

  private $_triggerList = [];
  private $_tagFilters = [];
  private $_triggerFilters = [];
  private $_includeDisabled = FALSE;
  private $_descriptionContainsFilter = NULL;
  private $_filterQuery = NULL;
  private $_filterQueryParams = [];

  /**
   * Overridden parent method to build the form
   */
  public function buildQuickForm() {
    $this->addEntityRef('tag_id', E::ts('Filter Tag(s)'), [
      'entity' => 'option_value',
      'api' => array(
        'params' => array('option_group_id' => 'civirule_rule_tag'),
      ),
      'placeholder' => E::ts('- Select Tag -'),
      'select' => ['minimumInputLength' => 0],
      'multiple' => TRUE,
    ]);
    $this->add('select', 'trigger_id', E::ts('Filter Trigger(s)'), $this->_triggerList, FALSE, [
      'multiple' => TRUE,
      'class' => 'crm-select2',
      'placeholder' => E::ts('- Select Trigger -'),
      ]);
    $this->add('text', 'desc_contains', E::ts('Description Contains'), ['size' => 40], FALSE);
    $this->addYesNo('include_disabled', E::ts('Show disabled Rules?'), [], FALSE);
    $this->addButtons([
      ['type' => 'submit', 'name' => E::ts('Filter'), 'isDefault' => TRUE],
      ]);
    // get existing rules
    $this->assign('rules', $this->getRules());
    parent::buildQuickForm();
  }

  /**
   * Method to set the included disabled to no
   * @return array|mixed|NULL
   */
  public function setDefaultValues() {
    if ($this->_includeDisabled) {
      $defaults['include_disabled'] = 1;
    }
    else {
      $defaults['include_disabled'] = 0;
    }
    return $defaults;
  }

  /**
   * Function to get the data
   *
   * @return array $rules
   * @access protected
   */
  private function getRules() {
    $rows = [];
    // get query based on filters
    $this->generateRuleQuery();
    $dao = CRM_Core_DAO::executeQuery($this->_filterQuery, $this->_filterQueryParams);
    while ($dao->fetch()) {
      $row = [];
      $elements = ['rule_id', 'label', 'trigger_label', 'description', 'is_active',
        'help_text', 'created_date', 'created_by_name', 'modified_date', 'modified_by_name', 'last_trigger_date', 'last_trigger_contactname'];
      $triggerDetail = CRM_Civirules_Utils::getRuleLogLatestTriggerDetail($dao->rule_id, 1);
      $triggerDetail = reset($triggerDetail);

      foreach ($elements as $element) {
        switch ($element) {
          case 'created_by_name':
            $row['created_by'] = CRM_Civirules_Utils::formatContactLink($dao->created_by_id, $dao->created_by_name);
            break;

          case 'modified_by_name':
            $row['modified_by'] = CRM_Civirules_Utils::formatContactLink($dao->modified_by_id, $dao->modified_by_name);
            break;

          case 'last_trigger_contactname':
            $row['last_trigger_contact'] = $triggerDetail['last_trigger_contact_link'] ?? '';
            break;

          case 'last_trigger_date':
            $row['last_trigger_date'] = $triggerDetail['last_trigger_date'];
            break;

          case 'is_active':
            $row['is_active'] = CRM_Civirules_Utils::formatIsActive($dao->is_active);
            $row['enabled'] = $dao->is_active;
            break;

          default:
            $row[$element] = $dao->$element;
        }
      }

      // If the rule has not been modified use the created by/date for the list of rules
      if (empty($row['modified_by'])) {
        $row['modified_by'] = $row['created_by'];
      }
      if (empty($row['modified_date'])) {
        $row['modified_date'] = $row['created_date'];
      }

      // add civirule tags
      $row['tags'] = implode(', ', CRM_Civirules_BAO_RuleTag::getTagLabelsForRule($dao->rule_id));
      $row['actions'] = $this->setRowActions($dao->rule_id, $dao->is_active);
      $rows[$dao->rule_id] = $row;
    }
    return $rows;
  }

  /**
   * Method to generate the query and query parameters to get the rules
   */
  private function generateRuleQuery() {
    $select = "SELECT DISTINCT(cr.id) AS rule_id, cr.label, ct.label AS trigger_label, cr.is_active, cr.description, cr.help_text,
cr.created_date, cr.created_user_id AS created_by_id, cc.sort_name AS created_by_name,
cr.modified_date, cr.modified_user_id AS modified_by_id, cc2.sort_name AS modified_by_name";
    $from = "FROM civirule_rule AS cr JOIN civirule_trigger AS ct ON cr.trigger_id = ct.id
LEFT JOIN civicrm_contact AS cc ON cr.created_user_id = cc.id
LEFT JOIN civicrm_contact AS cc2 ON cr.modified_user_id = cc2.id
LEFT JOIN civirule_rule_tag AS crt ON cr.id = crt.rule_id";
    $whereClauses = [];
    $index = 0;
    // set where clauses based on filters
    if ($this->_descriptionContainsFilter) {
      $index++;
      $whereClauses[] = 'cr.description LIKE %1';
      $this->_filterQueryParams[$index] = ['%' . $this->_descriptionContainsFilter . '%', 'String'];
    }
    if (!empty($this->_triggerFilters)) {
      $triggerValues = [];
      foreach ($this->_triggerFilters as $triggerFilter) {
        $index++;
        $triggerValues[] = '%' . $index;
        $this->_filterQueryParams[$index] = [$triggerFilter, 'Integer'];
      }
      $whereClauses[] = 'cr.trigger_id IN(' . implode(', ', $triggerValues) . ')';
    }
    if (!empty($this->_tagFilters)) {
      $tagFilters = explode(',', $this->_tagFilters);
      $tagValues = [];
      foreach ($tagFilters as $tagFilter) {
        $index++;
        $tagValues[] = '%' . $index;
        $this->_filterQueryParams[$index] = [$tagFilter, 'Integer'];
      }
      $whereClauses[] = 'crt.rule_tag_id IN(' . implode(', ', $tagValues) . ')';
    }
    if (!$this->_includeDisabled) {
      $index++;
      $whereClauses[] = 'cr.is_active = %' . $index;
      $this->_filterQueryParams[$index] = [1, 'Integer'];
    }
    if (!empty($whereClauses)) {
      $this->_filterQuery = "{$select} {$from} WHERE " . implode(' AND ', $whereClauses);
    }
    else {
      $this->_filterQuery = "{$select} {$from}";
    }
  }

  /**
   * Function to set the row action urls and links for each row
   *
   * @param int $ruleId
   * @param int $ruleEnabled
   * @return array $actions
   * @access protected
   */
  private function setRowActions($ruleId, $ruleEnabled) {
    $rowActions = array();
    $updateUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'reset=1&action=update&id='.
      $ruleId);
    $deleteUrl = CRM_Utils_System::url('civicrm/civirule/form/ruledelete', 'reset=1&action=delete&id='.
      $ruleId);
    $rowActions[] = '<a class="action-item civirule-update" title="Update" href="'.$updateUrl.'">'.ts('Edit').'</a>';
    if ($ruleEnabled == 1) {
      $rowActions[] = '<a class="action-item civirule-disable" onclick="civiruleEnableDisable(' . $ruleId . ', 0)" title="Disable" href="#">'.ts('Disable').'</a>';
    } else {
      $rowActions[] = '<a class="action-item civirule-enable" onclick="civiruleEnableDisable(' . $ruleId . ', 1)" title="Enable" href="#">'.ts('Enable').'</a>';
    }
    $rowActions[] = '<a class="action-item civirule-delete" title="Delete" href="'.$deleteUrl.'">'.ts('Delete').'</a>';
    return $rowActions;
  }

  /**
   * Overridden parent method to pre-load the form
   */
  public function preProcess() {
    $this->_tagFilters = CRM_Utils_Request::retrieveValue('tag_id', 'String');
    $this->_triggerFilters = CRM_Utils_Request::retrieveValue('trigger_id', 'String');
    $this->_descriptionContainsFilter = CRM_Utils_Request::retrieveValue('desc_contains', 'String');
    $this->_includeDisabled = CRM_Utils_Request::retrieveValue('include_disabled', 'Boolean');
    CRM_Utils_System::setTitle(E::ts('Manage CiviRules'));
    $this->setTriggerList();
    $this->assign('add_url', CRM_Utils_System::url('civicrm/civirule/form/rule',
      'reset=1&action=add', TRUE));
    parent::preProcess();
  }

  /**
   * Method to populate the trigger list
   */
  private function setTriggerList() {
    try {
      $triggers = civicrm_api3('CiviRuleTrigger', 'get', [
        'is_active' => 1,
        'options' => ['limit' => 0],
      ]);
      foreach ($triggers['values'] as $triggerId => $trigger) {
        $this->_triggerList[$triggerId] = $trigger['label'];
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
  }

  /**
   * Overridden parent method to process the form submission
   */
  public function postProcess() {
    $filter = FALSE;
    $checkElements = ['tag_id', 'trigger_id', 'desc_contains', 'include_disabled'];
    foreach ($checkElements as $checkElement) {
      if (isset($this->_submitValues[$checkElement]) && !empty($this->_submitValues[$checkElement])) {
        $filter = TRUE;
      }
    }
    if ($filter) {
      $filterUrl = CRM_Utils_System::url('civicrm/civirules/form/ruleview', [], TRUE);
      CRM_Core_Session::singleton()->pushUserContext($filterUrl);
    }
    parent::postProcess();
  }

}
