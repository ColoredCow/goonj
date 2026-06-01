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

use Civi\ConfigItems\Entity\EntityImporter;
use Civi\ConfigItems\FileFormat\EntityImportDataException;
use CRM_Core_Exception;
use CRM_Civirules_BAO_Action;
use CRM_Civirules_BAO_Condition;
use CRM_Civirules_ExtensionUtil as E;
use CRM_Core_DAO;
use CRM_Core_Session;
use Exception;

class Importer implements EntityImporter {

  /**
   * @var \Civi\ConfigItems\Entity\SimpleEntity\Definition
   */
  protected $entityDefinition;

  /**
   * @var \Civi\ConfigItems\ConfigurationForm
   */
  protected $form;

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
    return $this->entityDefinition->getImportHelpText();
  }

  /**
   * Load the entity data.
   *
   * @param $config_item_set
   * @return array
   * @throws \Civi\ConfigItems\FileFormat\EntityImportDataException
   */
  public function loadEntityImportData($config_item_set) {
    $fileFactory = civiconfig_get_fileformat_factory();
    if (empty($config_item_set['import_file_format'])) {
      return [];
    }
    $fileFormat = $fileFactory->getFileFormatClass($config_item_set['import_file_format']);
    $entityData = $fileFormat->loadEntityImportData($config_item_set, $this->entityDefinition->getName(), $this->getEntityDefinition()->getFileName());
    foreach($this->entityDefinition->getExporterClass()->getGroups() as $group => $groupTitle) {
      if (isset($entityData[$group])) {
        $entityData[$group] = $this->checkEntityDataForExistence($entityData[$group]);
      }
    }
    return $entityData;
  }

  /**
   * Load the entity data.
   *
   * @param $config_item_set
   * @return bool
   * @throws \Civi\ConfigItems\FileFormat\EntityImportDataException
   */
  public function entityImportDataExists($config_item_set) {
    if (empty($config_item_set['import_file_format'])) {
      return FALSE;
    }
    $fileFactory = civiconfig_get_fileformat_factory();
    $fileFormat = $fileFactory->getFileFormatClass($config_item_set['import_file_format']);
    try {
      $entityData = $fileFormat->loadEntityImportData($config_item_set, $this->entityDefinition->getName(), $this->getEntityDefinition()->getFileName());
      if (empty($entityData)) {
        return FALSE;
      }
    } catch (EntityImportDataException $ex) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Check records in entity data whether they exists and if so add their ID to $entityData.
   *
   * @param $entityData
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function checkEntityDataForExistence($entityData) {
    $entity = $this->entityDefinition->getApiEntityName();
    foreach($entityData as $name => $data) {
      try {
        $result = civicrm_api3($entity, 'getvalue', [
          'return' => $this->getEntityDefinition()->getIdAttribute(),
          $this->entityDefinition->getNameAttribute() => $data[$this->entityDefinition->getNameAttribute()],
          'options' => [
            'sort' => $this->getEntityDefinition()
                ->getIdAttribute() . ' ASC',
          ],
          'limit' => 1,
        ]);
        $entityData[$name][$this->getEntityDefinition()->getIdAttribute()] = $result;
      } catch (CRM_Core_Exception $e) {
        // Do nothing
      }
    }
    return $entityData;
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
   * Returns the import configuration form.
   * Returns false if this entity does not have a configuration for import.
   *
   * @return false|\Civi\ConfigItems\ConfigurationForm
   */
  public function getImportConfigurationForm() {
    if (!$this->form) {
      $this->form = new ImportForm($this);
    }
    return $this->form;
  }

  /**
   * @return array
   */
  public function getGroups() {
    return [
      'include' => E::ts('Include %1', [1=>$this->entityDefinition->getTitlePlural()]),
      'remove' => E::ts('Removed %1', [1=>$this->entityDefinition->getTitlePlural()]),
    ];
  }

  /**
   * @param $group
   * @param $data
   *
   * @return array
   */
  public function getOptions($group, $data) {
    if ($group == 'include' && isset($data[$this->entityDefinition->getIdAttribute()])) {
      return [
        0 => E::ts('Do not update'),
        1 => E::ts('Update'),
      ];
    } elseif ($group == 'include' && !isset($data[$this->entityDefinition->getIdAttribute()])) {
      return [
        1 => E::ts('Add'),
        0 => E::ts('Do not add'),
      ];
    } elseif ($group == 'remove' && isset($data[$this->entityDefinition->getIdAttribute()])) {
      return  [
        0 => E::ts('Keep'),
        1 => E::ts('Remove')
      ];
    }
    return [];
  }

  /**
   * @param $group
   * @param $data
   *
   * @return string|void
   */
  public function getDefaultOption($group, $data) {
    if ($group == 'include' && isset($data[$this->entityDefinition->getIdAttribute()])) {
      return '0';
    } elseif ($group == 'include' && !isset($data[$this->entityDefinition->getIdAttribute()])) {
      return '1';
    } elseif ($group == 'remove') {
      return  '0';
    }
  }

  /**
   * Add tasks to the import queue.
   *
   * You can add multiple tasks, for example if a task might take long, such as installing
   * an extension you can add a task for each extension. This way we prevent browser timeouts.
   *
   * @param \Civi\ConfigItems\QueueService $queue
   * @param $configuration
   * @param $config_item_set
   * @return void
   */
  public function addImportTasksToQueue(\Civi\ConfigItems\QueueService $queue, $configuration, $config_item_set) {
    if ($this->entityImportDataExists($config_item_set)) {
      $callback = [static::class, 'runImportTask'];
      $params = [
        $configuration,
        $config_item_set,
        $this->entityDefinition->getName()
      ];
      $entityTitle = $this->entityDefinition->getTitlePlural();
      $queue->addCallbackToCurrentTask($entityTitle, $callback, $params);
    }
  }

  /**
   * Import data.
   *
   * @param $configuration
   * @param $config_item_set
   * @param \CRM_Queue_TaskContext $ctx
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   * @throws \Civi\ConfigItems\FileFormat\EntityImportDataException
   */
  public function import($configuration, $config_item_set, \CRM_Queue_TaskContext $ctx) {
    $entityData = $this->loadEntityImportData($config_item_set);
    $entityData = $this->entityDefinition->alterEntityDataForImport($entityData, $configuration, $config_item_set);
    $entityName = $this->entityDefinition->getApiEntityName();
    $nameAttribute = $this->entityDefinition->getNameAttribute();
    $titleAttribute = $this->entityDefinition->getTitleAttribute();
    $groups = $this->entityDefinition->getExporterClass()->getGroups();
    foreach($groups as $group => $groupTitle) {
      foreach ($entityData[$group] as $data) {
        if (isset($configuration[$group]) && isset($configuration[$group][$data[$nameAttribute]]) && $configuration[$group][$data[$nameAttribute]]) {
          $apiAction = $this->getApiAction($group, $data);
          if ($apiAction) {
            $params = $this->getApiParams($group, $data, $configuration[$group][$data[$nameAttribute]]);
            try {
              $result = civicrm_api3($entityName, $apiAction, $params);
              $ruleId = $result['id'];
              if ($apiAction == 'create' && $ruleId) {
                $this->addRuleConditions($ruleId, $data);
                $this->addRuleActions($ruleId, $data);
              }
            } catch (CRM_Core_Exception|Exception $ex) {
              CRM_Core_Session::setStatus($ex->getMessage(), E::ts("Could not %1 '%2' %3", [1=>$apiAction, 2=>$data[$titleAttribute], 3=>$this->entityDefinition->getTitleSingle()]), 'error');
            }
          }
        }
      }
    }
  }

  /**
   * @param $ruleId
   * @param $data
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Exception
   */
  protected function addRuleConditions($ruleId, $data) {
    $sqlParams[1] = [$ruleId, 'Integer'];
    $sql = "DELETE FROM `civirule_rule_condition` WHERE `rule_id` = %1";
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
    foreach($data['conditions'] as $ruleCondition) {
      $ruleCondition['condition_id'] = $this->getEntityDefinition()->getConditionId($ruleCondition['condition']);
      unset($ruleCondition['condition']);
      $condition = CRM_Civirules_BAO_Condition::getConditionObjectById($ruleCondition['condition_id'], false);
      if (!$condition) {
        continue;
      }
      $condition->setRuleConditionData($ruleCondition);
      $ruleCondition['condition_params'] = $condition->importConditionParameters($ruleCondition['condition_params']);
      $ruleCondition['rule_id'] = $ruleId;
      civicrm_api3('CiviRuleRuleCondition', 'create', $ruleCondition);
    }
  }

  /**
   * @param $ruleId
   * @param $data
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Exception
   */
  protected function addRuleActions($ruleId, $data) {
    $sqlParams[1] = [$ruleId, 'Integer'];
    $sql = "DELETE FROM `civirule_rule_action` WHERE `rule_id` = %1";
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
    foreach($data['actions'] as $ruleAction) {
      $ruleAction['action_id'] = $this->getEntityDefinition()->getActionId($ruleAction['action']);
      unset($ruleAction['action']);
      $action = CRM_Civirules_BAO_Action::getActionObjectById($ruleAction['action_id'], false);
      if (!$action) {
        continue;
      }
      $action->setRuleActionData($ruleAction);
      $ruleAction['action_params'] = $action->importActionParameters($ruleAction['action_params']);
      $ruleAction['rule_id'] = $ruleId;
      civicrm_api3('CiviRuleRuleAction', 'create', $ruleAction);
    }
  }

  /**
   * Run the import task.
   *
   * @param $configuration
   * @param $config_item_set
   * @param $entityName
   * @param \CRM_Queue_TaskContext $ctx
   * @throws \Civi\API\Exception\NotImplementedException
   * @throws \Civi\ConfigItems\FileFormat\EntityImportDataException
   */
  public static function runImportTask($configuration, $config_item_set, $entityName, \CRM_Queue_TaskContext $ctx) {
    $factory = civiconfig_get_entity_factory();
    /**
     * @var \Civi\ConfigItems\Entity\SimpleEntity\Importer
     */
    $importer = $factory->getEntityDefinition($entityName)->getImporterClass();
    $importer->import($configuration, $config_item_set, $ctx);
  }

  /**
   * @param $group
   * @param $data
   *
   * @return string|void
   */
  protected function getApiAction($group, $data) {
    if ($group == 'include' && isset($data[$this->entityDefinition->getIdAttribute()])) {
      return 'create';
    } elseif ($group == 'include' && !isset($data[$this->entityDefinition->getIdAttribute()])) {
      return 'create';
    } elseif ($group == 'remove' && isset($data[$this->entityDefinition->getIdAttribute()])) {
      return 'delete';
    }
  }

  /**
   * Return the api parameters for import.
   * @param $group
   * @param $data
   * @param $configurationValue
   *
   * @return array
   */
  protected function getApiParams($group, $data, $configurationValue) {
    $ignoredAttributes = $this->getIgnoredAttributes();
    $idAttribute = $this->entityDefinition->getIdAttribute();
    $apiAction = $this->getApiAction($group, $data);
    $params = [];
    switch($apiAction) {
      case 'create':
        $data['trigger_id'] = $this->getEntityDefinition()->getTriggerId($data['trigger']);
        unset($data['trigger']);
        foreach($data as $key => $val) {
          if ($val === null) {
            $data[$key] = '';
          }
        }
        $params = $data;
        if ($group == 'include' && isset($data[$idAttribute])) {
          $id = $data[$idAttribute];
          $params[$idAttribute] = $id;
        }
        foreach ($ignoredAttributes[$group][$configurationValue] as $ignoredAttribute) {
          unset($params[$ignoredAttribute]);
        }
        unset($params['conditions']);
        unset($params['actions']);
        break;
      case 'delete':
        if (isset($data[$idAttribute]) && $data[$idAttribute]) {
          $id = $data[$idAttribute];
          $params[$idAttribute] = $id;
        }
        break;
    }
    return $params;
  }

  /**
   * Returns attributes which should not be exported.
   *
   * Contains the ID attribute by default.
   *
   * @return array
   */
  protected function getIgnoredAttributes() {
    $ignored = [];
    return $ignored;
  }

}
