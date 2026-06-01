<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
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

use Civi\ConfigItems\Entity\EntityDefinition;
use CRM_Civirules_ExtensionUtil as E;

class Definition extends EntityDefinition {

  /**
   * @return \Civi\ConfigItems\Entity\CiviRules\Exporter
   */
  protected $exporter;

  /**
   * @return \Civi\ConfigItems\Entity\CiviRules\Importer
   */
  protected $importer;

  /**
   * @param $name
   * @param $afterEntities
   * @param $beforeEntities
   */
  public function __construct($name, $afterEntities=[], $beforeEntities=[]) {
    parent::__construct($name, $afterEntities, $beforeEntities);
    $this->title_plural = E::ts('CiviRules');
    $this->title_single = E::ts('CiviRules');
  }

  /**
   * @return \Civi\ConfigItems\Entity\EntityImporter
   */
  public function getImporterClass() {
    if (!$this->importer) {
      $this->importer = new Importer($this);
    }
    return $this->importer;
  }

  /**
   * @return \Civi\ConfigItems\Entity\EntityExporter
   */
  public function getExporterClass() {
    if (!$this->exporter) {
      $this->exporter = new Exporter($this);
    }
    return $this->exporter;
  }

  /**
   * Returns the help text.
   * Return an empty string if no help is available.
   *
   * @return string
   */
  public function getExportHelpText() {
    return '';
  }

  /**
   * Returns the help text.
   * Return an empty string if no help is available.
   *
   * @return string
   */
  public function getImportHelpText() {
    return '';
  }

  /**
   * Returns the attribute in entity data for the name.
   *
   * @return string
   */
  public function getNameAttribute() {
    return 'name';
  }

  /**
   * Returns the attribute in entity data for the title.
   *
   * @return string
   */
  public function getTitleAttribute() {
    return 'label';
  }

  /**
   * Returns the attribute in entity data for the title.
   *
   * @return string
   */
  public function getIdAttribute() {
    return 'id';
  }

  /**
   * @return String
   */
  public function getApiEntityName() {
    return 'CiviRuleRule';
  }

  /**
   * @return String
   */
  public function getFileName() {
    return 'CiviRule';
  }

  /**
   * @param int $triggerId
   *
   * @return string
   */
  public function getTriggerName($triggerId) {
    try {
      return civicrm_api3('CiviRuleTrigger', 'getvalue', [
        'return' => 'name',
        'id' => $triggerId,
      ]);
    } catch (\CRM_Core_Exception $e) {
    }
    return $triggerId;
  }

  /**
   * @param int $conditionId
   *
   * @return string
   */
  public function getConditionName($conditionId) {
    try {
      return civicrm_api3('CiviRuleCondition', 'getvalue', [
        'return' => 'name',
        'id' => $conditionId,
      ]);
    } catch (\CRM_Core_Exception $e) {
    }
    return $conditionId;
  }

  /**
   * @param int $actionId
   *
   * @return string
   */
  public function getActionName($actionId) {
    try {
      return civicrm_api3('CiviRuleAction', 'getvalue', [
        'return' => 'name',
        'id' => $actionId,
      ]);
    } catch (\CRM_Core_Exception $e) {
    }
    return $actionId;
  }

  /**
   * @param int $triggerName
   *
   * @return string
   */
  public function getTriggerId($triggerName) {
    try {
      return civicrm_api3('CiviRuleTrigger', 'getvalue', [
        'return' => 'id',
        'name' => $triggerName,
      ]);
    } catch (\CRM_Core_Exception $e) {
    }
    return $triggerName;
  }

  /**
   * @param int $conditionName
   *
   * @return string
   */
  public function getConditionId($conditionName) {
    try {
      return civicrm_api3('CiviRuleCondition', 'getvalue', [
        'return' => 'id',
        'name' => $conditionName,
      ]);
    } catch (\CRM_Core_Exception $e) {
    }
    return $conditionName;
  }

  /**
   * @param int $actionName
   *
   * @return string
   */
  public function getActionId($actionName) {
    try {
      return civicrm_api3('CiviRuleAction', 'getvalue', [
        'return' => 'id',
        'name' => $actionName,
      ]);
    } catch (\CRM_Core_Exception $e) {
    }
    return $actionName;
  }


}
