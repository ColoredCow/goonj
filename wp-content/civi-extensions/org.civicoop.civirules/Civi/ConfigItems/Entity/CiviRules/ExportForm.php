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

use Civi\ConfigItems\ConfigurationForm;
use Civi\ConfigItems\ConfigurationFormCountable;
use Civi\ConfigItems\Tab;
use Civi\ConfigItems\FileFormat\EntityImportDataException;
use CRM_Civirules_ExtensionUtil as E;

class ExportForm implements ConfigurationForm, ConfigurationFormCountable, Tab {

  /**
   * @var \Civi\ConfigItems\Entity\SimpleEntity\Exporter
   */
  protected $entityExporter;

  public function __construct(Exporter $entityExporter) {
    $this->entityExporter = $entityExporter;
  }

  /**
   * @return string
   */
  public function getTitle() {
    return $this->entityExporter->getEntityDefinition()->getTitlePlural();
  }

  public function getHelpText() {
    return $this->entityExporter->getHelpText();
  }

  /**
   * @param \CRM_Core_Form $form
   * @param array $configuration
   * @param array $config_item_set
   */
  public function buildConfigurationForm(\CRM_Core_Form $form, $configuration, $config_item_set) {
    $entityDefinition = $this->entityExporter->getEntityDefinition();
    $form->assign('entityTitle', $this->getTitle());
    $form->assign('helpText', $this->getHelpText());
    $entityName = $entityDefinition->getApiEntityName();
    $entities = civicrm_api3($entityName, 'get', []);
    $radioButtons = $this->entityExporter->getGroups();


    $defaults = [];
    $idAttribute = $entityDefinition->getIdAttribute();
    $nameAttribute = $entityDefinition->getNameAttribute();
    $titleAttribute = $entityDefinition->getTitleAttribute();
    $elements = [];
    $nonExistingElements = [];
    foreach($entities['values'] as $entity) {
      $name = \CRM_Utils_String::munge($entity[$nameAttribute]);
      $elements[] = $entityName . '_' . $name;
      $form->addRadio($entityName . '_' . $name, $entity[$titleAttribute], $radioButtons, ['allowClear' => true], NULL, FALSE, []);
    }
    $form->assign('elements', $elements);

    try {
      if ($entityDefinition->getImporterClass() && $entityDefinition->getImporterClass()->entityImportDataExists($config_item_set)) {
        $importData = $entityDefinition->getImporterClass()->loadEntityImportData($config_item_set);
        $nonExistingRadioButtons = $radioButtons + ['' => E::ts('Do not include in export file')];
        foreach ($this->entityExporter->getGroups() as $group => $groupTitle) {
          if (!isset($importData[$group])) {
            $importData[$group] = [];
          }
          foreach ($importData[$group] as $entity) {
            $name = \CRM_Utils_String::munge($entity[$nameAttribute]);
            if (!isset($entity[$idAttribute]) && !in_array($entityName . '_' . $name, $elements)) {
              $nonExistingElements[] = $entityName . '_' . $name;
              $form->addRadio($entityName . '_' . $name, $entity[$titleAttribute], $nonExistingRadioButtons, ['allowClear' => FALSE], NULL, TRUE, []);
              $defaults[$entityName . '_' . $name] = $group;
            }
          }
        }
      }
    } catch (EntityImportDataException $ex) {
      // Do nothing.
    }
    $form->assign('non_existing_elements', $nonExistingElements);

    foreach($this->entityExporter->getGroups() as $group => $groupTitle) {
      if (isset($configuration[$group])) {
        foreach ($configuration[$group] as $val) {
          $defaults[$entityName . '_' . $val] = $group;
        }
      }
    }
    $form->setDefaults($defaults);
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $configuration
   *
   * @return int
   */
  public function getCount($configuration) {
    $count = 0;
    foreach ($this->entityExporter->getGroups() as $group => $groupTitle) {
      if (isset($configuration[$group])) {
        $count += count($configuration[$group]);
      }
    }
    return $count;
  }


  /**
   * Returns the name of the template for the configuration form.
   *
   * @return string
   */
  public function getConfigurationTemplateFileName() {
    return "CRM/ConfigItems/Entity/CiviRules/ExportForm.tpl";
  }

  /**
   * Process the submitted values and create a configuration array
   *
   * @param $submittedValues
   * @param array $config_item_set
   * @return array
   */
  public function processConfiguration($submittedValues, $config_item_set) {
    $entityName = $this->entityExporter->getEntityDefinition()->getApiEntityName();
    $config = [];
    foreach ($this->entityExporter->getGroups() as $group => $groupTitle) {
      $config[$group] = [];
    }
    foreach($submittedValues as $key => $val) {
      if (str_starts_with($key, $entityName . '_') && !empty($val)) {
        $entity = substr($key, strlen($entityName . '_'));
        $config[$val][] = $entity;
      }
    }
    return $config;
  }

  /**
   * This function is called to add tabs to the tabset.
   * Returns the $tabset
   *
   * @param $tabset
   * @param $configuration
   * @param $config_item_set
   * @param bool $reset
   * @return array
   */
  public function getTabs($tabset, $configuration, $config_item_set, $reset=FALSE) {
    $entityName = $this->entityExporter->getEntityDefinition()->getName();
    $url = \CRM_Utils_System::url('civicrm/admin/civiconfig/edit/entity', ['reset' => 1, 'id' => $config_item_set['id'], 'entity' => $entityName]);
    $tabset[$entityName] = [
      'title' => $this->entityExporter->getEntityDefinition()->getTitlePlural(),
      'active' => 1,
      'valid' => 1,
      'link' => $url,
      'current' => false,
      'count' => $this->getCount($configuration),
    ];
    return $tabset;
  }


}
