<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

namespace Civi\Api4\Service\Spec\Provider;

use Civi\Api4\Service\Spec\FieldSpec;
use Civi\Api4\Service\Spec\RequestSpec;

/**
 * @service
 * @internal
 */
class CiviRulesRuleGetSpecProvider extends \Civi\Core\Service\AutoService implements Generic\SpecProviderInterface {

  /**
   * @param \Civi\Api4\Service\Spec\RequestSpec $spec
   *
   * @throws \CRM_Core_Exception
   */
  public function modifySpec(RequestSpec $spec): void {
    // Calculated field counts contacts in group
    $field = new FieldSpec('trigger_params_display', 'CiviRulesRule', 'String');
    $field->setLabel(ts('Trigger Params Description'))
      ->setTitle(ts('Trigger Params Description'))
      ->setColumnName('trigger_params')
      ->setDescription(ts('Human-readable description of trigger parameters'))
      ->setType('Extra')
      ->setReadonly(TRUE)
      ->addOutputFormatter([__CLASS__, 'description']);
    $spec->addFieldSpec($field);
  }

  /**
   * @param string $entity
   * @param string $action
   *
   * @return bool
   */
  public function applies($entity, $action): bool {
    return $entity === 'CiviRulesRule' && in_array($action, ['get', 'create']);
  }

  public static function description(&$value, $row) {
    $triggerClass = \CRM_Civirules_BAO_Trigger::getTriggerObjectByTriggerId($row['trigger_id'], FALSE);
    if ($triggerClass) {
      $triggerClass->setTriggerId($row['trigger_id']);
      $triggerClass->setTriggerParams($row['trigger_params_display']);
      $value = $triggerClass->getTriggerDescription();
    }
  }

}
