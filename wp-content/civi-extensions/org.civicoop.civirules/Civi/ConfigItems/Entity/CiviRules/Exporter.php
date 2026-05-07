<?php
/**
 * Copyright (C) 2021  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Civi\ConfigItems\Entity\CiviRules;

use Civi\ConfigItems\Entity\EntityExporter;
use Civi\ConfigItems\ConfigurationForm;
use Civi\ConfigItems\FileFormat\EntityImportDataException;
use CRM_Civirules_BAO_Action;
use CRM_Civirules_BAO_Condition;
use CRM_Civirules_ExtensionUtil as E;

class Exporter implements EntityExporter {

  /**
   * @var \Civi\ConfigItems\Entity\CiviRules\Definition;
   */
  protected $entityDefinition;

  public function __construct(Definition $entityDefinition) {
    $this->entityDefinition = $entityDefinition;
  }

  /**
   * Returns the help text.
   * Return an empty string if no help is available.
   *
   * @return string
   */
  public function getHelpText() {
    return $this->entityDefinition->getExportHelpText();
  }


  /**
   * Returns the entity definition
   *
   * @return \Civi\ConfigItems\Entity\EntityDefinition
   */
  public function getEntityDefinition() {
    return $this->entityDefinition;
  }

  /**
   * Returns the export configuration form.
   * Returns false if this entity does not have a configuration for export.
   *
   * @return false|ConfigurationForm
   */
  public function getExportConfigurationForm() {
    return new ExportForm($this);
  }

  /**
   * @return array
   */
  public function getGroups() {
    return [
      'include' => E::ts('Include'),
      'remove' => E::ts('Mark as removed'),
    ];
  }

  /**
   * Returns attributes which should not be exported.
   *
   * Contains the ID attribute by default.
   *
   * @return array
   */
  public function getIgnoredAttributes() {
    $ignored = [];
    foreach($this->getGroups() as $group => $groupTitle) {
      $ignored[$group][] = $this->getEntityDefinition()->getIdAttribute();
      $ignored[$group][] = 'created_date';
      $ignored[$group][] = 'created_user_id';
      $ignored[$group][] = 'trigger_id';
    }
    return $ignored;
  }

  /**
   * Exports the entity
   *
   * @param $configuration
   * @param $config_item_set
   * @param string $directory
   * @return array
   */
  public function export($configuration, $config_item_set, $directory='') {
    $entityName = $this->getEntityDefinition()->getApiEntityName();
    $ignoredAttributes = $this->getIgnoredAttributes();
    $nameAttribute = $this->entityDefinition->getNameAttribute();
    $idAttribute = $this->entityDefinition->getIdAttribute();
    $data = [];

    try {
      $importData = [];
      if ($this->entityDefinition->getImporterClass() && $this->entityDefinition->getImporterClass()->entityImportDataExists($config_item_set)) {
        $importData = $this->entityDefinition
          ->getImporterClass()
          ->loadEntityImportData($config_item_set);
      }
    } catch (EntityImportDataException $ex) {
      // Do nothing.
    }

    $results = civicrm_api3($entityName, 'get', []);
    foreach ($results['values'] as $result) {
      $unmungedName = $result[$nameAttribute];
      $name = \CRM_Utils_String::munge($result[$nameAttribute]);
      foreach($this->getGroups() as $group => $groupTitle) {
        if (isset($configuration[$group]) && in_array($name, $configuration[$group])) {
          $data[$group][$unmungedName] = (array) $result;
          $data[$group][$unmungedName]['trigger'] = $this->entityDefinition->getTriggerName($data[$group][$unmungedName]['trigger_id']);
          $data[$group][$unmungedName]['conditions'] = $this->getConditions($data[$group][$unmungedName][$idAttribute]);
          $data[$group][$unmungedName]['actions'] = $this->getActions($data[$group][$unmungedName][$idAttribute]);
          foreach ($ignoredAttributes[$group] as $ignoredAttribute) {
            if (isset($data[$group]) && isset($data[$group][$unmungedName]) && isset($data[$group][$unmungedName][$ignoredAttribute])) {
              unset($data[$group][$unmungedName][$ignoredAttribute]);
            }
          }
          unset($importData[$group][$unmungedName]);
        }
      }
    }
    foreach($this->getGroups() as $group => $groupTitle) {
      if (isset($configuration[$group]) && $configuration[$group]) {
        foreach ($configuration[$group] as $name) {
          if (isset($importData[$group][$name])) {
            $data[$group][$name] = $importData[$group][$name];
          }
        }
      }
    }
    $data = $this->entityDefinition->alterEntityDataForExport($data, $configuration, $config_item_set);
    return $data;
  }

  /**
   * @param $ruleId
   * @return array
   */
  protected function getConditions($ruleId) {
    $return = [];
    try {
      $result = civicrm_api3('CiviRuleRuleCondition', 'get', [
        'rule_id' => $ruleId,
        ['options' => ['limit' => 0]],
      ]);
      foreach($result['values'] as $ruleCondition) {
        $condition = CRM_Civirules_BAO_Condition::getConditionObjectById($ruleCondition['condition_id'], false);
        if (!$condition) {
          continue;
        }
        $condition->setRuleConditionData($ruleCondition);

        $ruleCondition['condition_params'] = $condition->exportConditionParameters();
        $ruleCondition['condition'] = $this->getEntityDefinition()->getConditionName($ruleCondition['condition_id']);
        unset($ruleCondition['id']);
        unset($ruleCondition['rule_id']);
        unset($ruleCondition['condition_id']);
        $return[] = $ruleCondition;
      }
    } catch (\CRM_Core_Exception $e) {
      // Do nothing.
    }
    return $return;
  }

  /**
   * @param $ruleId
   * @return array
   */
  protected function getActions($ruleId) {
    $return = [];
    try {
      $result = civicrm_api3('CiviRuleRuleAction', 'get', [
        'rule_id' => $ruleId,
        ['options' => ['limit' => 0]],
      ]);
      foreach($result['values'] as $ruleAction) {
        $action = CRM_Civirules_BAO_Action::getActionObjectById($ruleAction['action_id'], false);
        if (!$action) {
          continue;
        }
        $action->setRuleActionData($ruleAction);

        $ruleAction['action_params'] = $action->exportActionParameters();
        $ruleAction['action'] = $this->getEntityDefinition()->getActionName($ruleAction['action_id']);
        unset($ruleAction['id']);
        unset($ruleAction['rule_id']);
        unset($ruleAction['action_id']);
        $return[] = $ruleAction;
      }
    } catch (\CRM_Core_Exception $e) {
      // Do nothing.
    }
    return $return;
  }


}
