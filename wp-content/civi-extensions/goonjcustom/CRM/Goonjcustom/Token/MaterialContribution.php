<?php

/**
 *
 */

use Civi\MaterialMonetaryAskService;
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
      'monetary_link' => \CRM_Goonjcustom_ExtensionUtil::ts('Monetary Contribution Link'),
    ]);
  }

  /**
   * Prints the office in brackets, e.g. "(Goonj Mumbai)", or nothing when none is found.
   */
  public function evaluateToken(
    TokenRow $row,
    $entity,
    $field,
    $prefetch = NULL,
  ) {
    $activityId = $row->context['activityId'] ?? NULL;

    // PROTOTYPE: personalised contribution-page link for the ack email.
    if ($field === 'monetary_link') {
      $url = $activityId ? MaterialMonetaryAskService::getMonetaryUrl((int) $activityId, NULL, 'ack_email', 30) : NULL;
      $row->format('text/plain')->tokens($entity, $field, $url ?: 'https://goonj.org/donate');
      return;
    }

    $officeName = $activityId ? MaterialContributionService::getGoonjOfficeName((int) $activityId) : '';

    $row->format('text/plain')->tokens($entity, $field, $officeName ? "($officeName)" : '');
  }

}
