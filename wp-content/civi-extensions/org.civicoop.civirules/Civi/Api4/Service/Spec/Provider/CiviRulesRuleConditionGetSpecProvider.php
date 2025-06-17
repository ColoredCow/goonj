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
class CiviRulesRuleConditionGetSpecProvider extends \Civi\Core\Service\AutoService implements Generic\SpecProviderInterface {

  /**
   * @param \Civi\Api4\Service\Spec\RequestSpec $spec
   *
   * @throws \CRM_Core_Exception
   */
  public function modifySpec(RequestSpec $spec): void {
    // Calculated field gets action parameter description
    $field = new FieldSpec('condition_params_display', 'CiviRulesRuleCondition', 'String');
    $field->setLabel(ts('Condition Params Description'))
      ->setTitle(ts('Condition Params Description'))
      ->setColumnName('condition_params')
      ->setDescription(ts('Human-readable description of condition parameters'))
      ->setType('Extra')
      ->setReadonly(TRUE)
      ->addOutputFormatter([__CLASS__, 'conditionParamsDisplay']);
    $spec->addFieldSpec($field);
  }

  /**
   * @param string $entity
   * @param string $action
   *
   * @return bool
   */
  public function applies($entity, $action): bool {
    return $entity === 'CiviRulesRuleCondition' && in_array($action, ['get', 'create']);
  }

  public static function conditionParamsDisplay(&$value, $row) {
    if (!empty($row['condition_id'])) {
      $conditionClass = \CRM_Civirules_BAO_CiviRulesCondition::getConditionObjectById($row['condition_id']);
      if ($conditionClass) {
        $conditionClass->setRuleConditionData(['condition_params' => $value]);
        $value = $conditionClass->userFriendlyConditionParams();
      }
    }
    else {
      $value = '';
    }
  }

}
