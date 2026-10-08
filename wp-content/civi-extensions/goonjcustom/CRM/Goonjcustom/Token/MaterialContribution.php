<?php

/**
 *
 */

use Civi\MaterialContributionService;
use Civi\Token\AbstractTokenSubscriber;
use Civi\Token\TokenRow;

/**
 * Tokens for the Material Contribution acknowledgment reminder.
 */
class CRM_Goonjcustom_Token_MaterialContribution extends AbstractTokenSubscriber {

  public function __construct() {
    parent::__construct('material_contribution', [
      'goonj_office' => \CRM_Goonjcustom_ExtensionUtil::ts('Goonj Office'),
    ]);
  }

  /**
   * Prints the office name, e.g. "Goonj Mumbai", or nothing when none is found.
   */
  public function evaluateToken(
    TokenRow $row,
    $entity,
    $field,
    $prefetch = NULL,
  ) {
    $activityId = $row->context['activityId'] ?? NULL;
    $officeName = $activityId ? MaterialContributionService::getGoonjOfficeName((int) $activityId) : '';

    $row->format('text/plain')->tokens($entity, $field, $officeName);
  }

}
