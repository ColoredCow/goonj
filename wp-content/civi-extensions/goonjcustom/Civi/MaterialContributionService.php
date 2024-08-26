<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class MaterialContributionService extends AutoSubscriber {

  // See: CiviCRM > Administer > Communications > Schedule Reminders.
  const CONTRIBUTION_RECEIPT_REMINDER_ID = 6;

  const ACTIVITY_SOURCE_RECORD_TYPE_ID = 2;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_alterMailParams' => 'attachContributionReceiptToEmail',
    ];
  }

  /**
   * Attach material contribution receipt to the email.
   */
  public static function attachContributionReceiptToEmail(&$params, $context = NULL) {
    $reminderId = (int) $params['entity_id'];

    if ($context !== 'singleEmail' || $reminderId !== self::CONTRIBUTION_RECEIPT_REMINDER_ID) {
      return;
    }

    // Hack: Retrieve the most recent "Material Contribution" activity for this contact.
    $activities = Activity::get(TRUE)
      ->addSelect('*', 'contact.display_name')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addJoin('Contact AS contact', 'LEFT')
      ->addWhere('source_contact_id', '=', $params['contactId'])
      ->addWhere('activity_type_id:name', '=', 'Material Contribution')
      ->addWhere('activity_contact.record_type_id', '=', self::ACTIVITY_SOURCE_RECORD_TYPE_ID)
      ->addOrderBy('created_date', 'DESC')
      ->setLimit(1)
      ->execute();

    $contribution = $activities->first();

    if (!$contribution) {
      return;
    }

    $html = self::generateContributionReceiptHtml($contribution);
    $fileName = 'material_contribution_' . $contribution['id'] . '.pdf';
    $params['attachments'][] = \CRM_Utils_Mail::appendPDF($fileName, $html);
  }

  /**
   * Generate the HTML for the PDF from the activity data.
   *
   * @param array $activity
   *   The activity data.
   *
   * @return string
   *   The generated HTML.
   */
  private static function generateContributionReceiptHtml($activity) {
    // Format the activity date.
    $activityDate = date("F j, Y", strtotime($activity['activity_date_time']));

    // Start building the HTML.
    $html = '<html><body>';

    // Header: Material Receipt#<activity_id> (left) | Goonj address (right)
    $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="border: none;">';
    $html .= '<tr>';
    $html .= '<td style="text-align: left; vertical-align: top;">Material Receipt#' . $activity['id'] . '</td>';
    $html .= '<td style="text-align: right; vertical-align: top;">Goonj, C-544, Pocket C, Sarita Vihar, Delhi, India</td>';
    $html .= '</tr>';
    $html .= '</table>';

    // Line breaks.
    $html .= '<br><br><br>';

    // Appreciation message.
    $html .= '<div style="font-weight: bold; font-style: italic;">';
    $html .= '"We appreciate your contribution of pre used/new material. Goonj makes sure that the material reaches people with dignity and care."';
    $html .= '</div>';

    // Line breaks.
    $html .= '<br><br><br>';

    // Start of the table.
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';

    // Table rows.
    $html .= '<tr>';
    $html .= '<td>Description of Material</td>';
    $html .= '<td>' . $activity['subject'] . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>Received On</td>';
    $html .= '<td>' . $activityDate . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>From</td>';
    $html .= '<td>' . $activity['contact.display_name'] . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>Address</td>';
    $html .= '<td>B-38, Sarita Vihar, Delhi, Sarita Vihar, DELHI, 110076, India(TODO)</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>Email</td>';
    $html .= '<td>' . $activity['contact.display_name'] . '(TODO)</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>Phone</td>';
    // Hardcoded phone number.
    $html .= '<td>9876543210 (TODO)</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td>Delivered by (Name & contact no.)</td>';
    // Hardcoded delivery details.
    $html .= '<td>Self (TODO)</td>';
    $html .= '</tr>';

    // End of the table.
    $html .= '</table>';

    // End the HTML document.
    $html .= '</body></html>';

    return $html;
  }

}
