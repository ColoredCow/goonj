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
class CiviRulesRuleActionGetSpecProvider extends \Civi\Core\Service\AutoService implements Generic\SpecProviderInterface {

  /**
   * @param \Civi\Api4\Service\Spec\RequestSpec $spec
   *
   * @throws \CRM_Core_Exception
   */
  public function modifySpec(RequestSpec $spec): void {
    // Calculated field gets action parameter description
    $field = new FieldSpec('action_params_display', 'CiviRulesRuleAction', 'String');
    $field->setLabel(ts('Action Params Description'))
      ->setTitle(ts('Action Params Description'))
      ->setColumnName('action_params')
      ->setDescription(ts('Human-readable description of action parameters'))
      ->setType('Extra')
      ->setReadonly(TRUE)
      ->addOutputFormatter([__CLASS__, 'actionParamsDisplay']);
    $spec->addFieldSpec($field);

    // Calculated field gets action parameter description
    $field = new FieldSpec('action_delay_display', 'CiviRulesRuleAction', 'String');
    $field->setLabel(ts('Action Delay Description'))
      ->setTitle(ts('Action Delay Description'))
      ->setColumnName('delay')
      ->setDescription(ts('Human-readable description of action delay params'))
      ->setType('Extra')
      ->setReadonly(TRUE)
      ->addOutputFormatter([__CLASS__, 'actionDelayDisplay']);
    $spec->addFieldSpec($field);
  }

  /**
   * @param string $entity
   * @param string $action
   *
   * @return bool
   */
  public function applies($entity, $action): bool {
    return $entity === 'CiviRulesRuleAction' && in_array($action, ['get', 'create']);
  }

  /**
   * Format the action params for display
   *
   * @param $value
   * @param $row
   * @return void
   * @throws \Exception
   */
  public static function actionParamsDisplay(&$value, $row) {
    if (!empty($row['action_id'])) {
      $actionClass = \CRM_Civirules_BAO_CiviRulesAction::getActionObjectById($row['action_id']);
      if ($actionClass) {
        $actionClass->setRuleActionData(['action_params' => $value]);
        $value = $actionClass->userFriendlyConditionParams();
      }
    }
    else {
      $value = '';
    }
  }

  /**
   * Format the delay params for display
   *
   * @param $value
   * @param $row
   * @return void
   */
  public static function actionDelayDisplay(&$value, $row) {
    if (!empty($row['action_id'])) {
      if (!empty($value)) {
        try {
          $delayClass = unserialize($value);
          $value = $delayClass->getDelayExplanation();
        } catch (\Throwable $e) {
          $value = '';
        }
      }
    }
  }

}
