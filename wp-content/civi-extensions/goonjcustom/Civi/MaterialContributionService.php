<?php

namespace Civi;

use Civi\Api4\ActionSchedule;
use Civi\Api4\Activity;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class MaterialContributionService extends AutoSubscriber {
  const ACTIVITY_SOURCE_RECORD_TYPE_ID = 2;

  /**
   * Fetch the Material Contribution Receipt Reminder ID.
   */
  public static function getContributionReceiptReminderId() {
    try {
      $materialContributionReceiptReminder = ActionSchedule::get(TRUE)
        ->addWhere('name', '=', 'Material_Contribution_Receipt')
        ->execute()->single();

      return $materialContributionReceiptReminder['id'];
    }
    catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('Error fetching Contribution Receipt Reminder ID: ' . $e->getMessage());
      return NULL;
    }
  }

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
    if (!isset($params['entity_id'])) {
      return;
    }

    $reminderId = (int) $params['entity_id'];
    $contributionReceiptReminderId = self::getContributionReceiptReminderId();

    if ($context !== 'singleEmail' || $reminderId !== $contributionReceiptReminderId) {
      return;
    }

    // Hack: Retrieve the most recent "Material Contribution" activity for this contact.
    $activities = Activity::get(TRUE)
      ->addSelect('*', 'contact.display_name', 'Material_Contribution.Delivered_By', 'Material_Contribution.Delivered_By_Contact')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addJoin('Contact AS contact', 'LEFT')
      ->addWhere('source_contact_id', '=', $params['contactId'])
      ->addWhere('activity_type_id:name', '=', 'Material Contribution')
      ->addWhere('activity_contact.record_type_id', '=', self::ACTIVITY_SOURCE_RECORD_TYPE_ID)
      ->addOrderBy('created_date', 'DESC')
      ->setLimit(1)
      ->execute();

    $contribution = $activities->first();

    $contactData = civicrm_api4('Contact', 'get', [
      'select' => [
        'email_primary.email',
        'phone_primary.phone',
      ],
      'where' => [
              ['id', '=', $params['contactId']],
      ],
      'limit' => 1,
    ]);

    $activityData = civicrm_api4('Activity', 'get', [
      'select' => [
        'Material_Contribution.Collection_Camp',
      ],
      'where' => [
              ['id', '=', $contribution['id']],
      ],
      'limit' => 1,
    ]);

    $activity = $activityData[0] ?? [];

    $collectionCampData = civicrm_api4('Eck_Collection_Camp', 'get', [
      'select' => [
        'Collection_Camp_Intent_Details.Location_Area_of_camp',
      ],
      'where' => [
              ['id', '=', $activity['Material_Contribution.Collection_Camp']],
      ],
      'limit' => 1,
    ]);

    $collectionCamp = $collectionCampData[0] ?? [];

    $locationAreaOfCamp = $collectionCamp['Collection_Camp_Intent_Details.Location_Area_of_camp'] ?? 'N/A';

    $contactDataArray = $contactData[0] ?? [];
    $email = $contactDataArray['email_primary.email'] ?? 'N/A';
    $phone = $contactDataArray['phone_primary.phone'] ?? 'N/A';

    if (!$contribution) {
      return;
    }

    $html = self::generateContributionReceiptHtml($contribution, $email, $phone, $locationAreaOfCamp);
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
  private static function generateContributionReceiptHtml($activity, $email, $phone, $locationAreaOfCamp) {
    $activityDate = date("F j, Y", strtotime($activity['activity_date_time']));

    $baseDir = plugin_dir_path(__FILE__) . '../../../themes/goonj-crm/';
    $deliveredBy = empty($activity['Material_Contribution.Delivered_By']) ? $activity['contact.display_name'] : $activity['Material_Contribution.Delivered_By'];

    $deliveredByContact = empty($activity['Material_Contribution.Delivered_By_Contact']) ? $phone : $activity['Material_Contribution.Delivered_By_Contact'];

    $paths = [
      'logo' => $baseDir . 'images/goonj-logo.png',
      'qrCode' => $baseDir . 'images/qr-code.png',
      'callIcon' => $baseDir . 'Icon/call.png',
      'domainIcon' => $baseDir . 'Icon/domain.png',
      'emailIcon' => $baseDir . 'Icon/email.png',
      'facebookIcon' => $baseDir . 'Icon/facebook.webp',
      'instagramIcon' => $baseDir . 'Icon/instagram.png',
      'twitterIcon' => $baseDir . 'Icon/twitter.webp',
      'youtubeIcon' => $baseDir . 'Icon/youtube.webp',
    ];

    $imageData = array_map(fn ($path) => base64_encode(file_get_contents($path)), $paths);

    $html = <<<HTML
    <html>
      <body style="font-family: Arial, sans-serif;">
        <div style="text-align: center; margin-bottom: 16px;">
          <img src="data:image/png;base64,{$imageData['logo']}" alt="Goonj Logo" style="width: 95px; height: 80px;">
        </div>
        
        <div style="width: 100%; font-size: 14px;">
          <div style="float: left; text-align: left;">
            Material Acknowledgment# {$activity['id']}
          </div>
          <div style="float: right; text-align: right;">
            Goonj, C-544, Pocket C, Sarita Vihar, Delhi, India
          </div>
        </div>
        <br><br>
        <div style="font-weight: bold; font-style: italic; margin-top: 6px; margin-bottom: 6px;">
          "We appreciate your contribution of pre-used/new material. Goonj makes sure that the material reaches people with dignity and care."
        </div>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
          <style>
            .table-header {
              text-align: left;
              font-weight: bold;
            }
          </style>
          <!-- Table rows for each item -->
          <tr>
            <td class="table-header">Description of Material *</td>
            <td style="text-align: center;">{$activity['subject']}</td>
          </tr>
          <tr>
            <td class="table-header">Received On</td>
            <td style="text-align: center;">{$activityDate}</td>
          </tr>
          <tr>
            <td class="table-header">From</td>
            <td style="text-align: center;">{$activity['contact.display_name']}</td>
          </tr>
          <tr>
            <td class="table-header">City</td>
            <td style="text-align: center;">{$locationAreaOfCamp}</td>
          </tr>
          <tr>
            <td class="table-header">Email</td>
            <td style="text-align: center;">{$email}</td>
          </tr>
          <tr>
            <td class="table-header">Phone</td>
            <td style="text-align: center;">{$phone}</td>
          </tr>
          <tr>
            <td class="table-header">Delivered by (Name & contact no.)</td>
            <td style="text-align: center;">
            {$deliveredBy}<br>
            {$deliveredByContact}
          </td>
        </tr>

        </table>
        <div style="text-align: right; font-size: 14px;">
          Team Goonj
        </div>
        <div style="width: 100%; margin-top: 16px;">
        <div style="float: left; width: 60%; font-size: 14px;">
        <p>Join us, by encouraging your friends, relatives, colleagues, and neighbours to join the journey as all of us have a lot to give.</p>
        <p style="margin-top: 8px;">
        <strong>With Material Money Matters</strong> Your monetary contribution is needed too for sorting, packing, transportation to implementation. (Financial contributions are tax-exempted u/s 80G of IT Act)
      </p>
      <p style="margin-top: 10px; font-size: 12px; float: left">* Received material has 'No Commercial Value' for Goonj.</p>
    </div>
    <div style="float: right; width: 40%; text-align: right; font-size: 12px; font-style: italic;">
    <p>To contribute, please scan the code.</p>
    <img src="data:image/png;base64,{$imageData['qrCode']}" alt="QR Code" style="width: 80px; height: 70px; margin-top: 2px"></div>
        </div>
        <div style="clear: both; margin-top: 20px;"></div>
        <div style="width: 100%; margin-top: 15px; background-color: #f2f2f2; padding: 16px; font-weight: 300; color: #000000">
          <div style="font-size: 14px; margin-bottom: 20px;">
            <div style="position: relative; height: 24px;">
              <div style="font-size: 14px; float: left; color:">
                Goonj, C-544, 1st Floor, C-Pocket, Sarita Vihar,<br>
                New Delhi-110076
              </div>
              <div style="font-size: 14px; float: right;">
                <img src="data:image/png;base64,{$imageData['callIcon']}" alt="Phone" style="width: 16px; height: 16px; margin-right: 5px;">
                011-26972351/41401216
              </div>
            </div>
          </div>
    
          <div style="text-align: center; width: 100%; font-size: 14px; margin-bottom: 20px;">
              <div style="font-size: 14px;">
                <img src="data:image/png;base64,{$imageData['emailIcon']}" alt="Email" style="width: 16px; height: 16px; display: inline;">
                <span style="display: inline; margin-left: 0;">mail@goonj.org</span>
                <img src="data:image/png;base64,{$imageData['domainIcon']}" alt="Website" style="width: 16px; height: 16px; margin-right: 5px;">
                <span style="display: inline; margin-left: 0;">www.goonj.org</span>
              </div>
          </div>
    
          <!-- Social Media Icons -->
          <div style="text-align: center; width: 100%; margin-top: 28px;">
            <a href="https://www.facebook.com/goonj.org" target="_blank"><img src="data:image/webp;base64,{$imageData['facebookIcon']}" alt="Facebook" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.instagram.com/goonj/" target="_blank"><img src="data:image/webp;base64,{$imageData['instagramIcon']}" alt="Instagram" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://x.com/goonj" target="_blank"><img src="data:image/webp;base64,{$imageData['twitterIcon']}" alt="Twitter" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.youtube.com/channel/UCCq8iYlmjT7rrgPI1VHzIHg" target="_blank"><img src="data:image/webp;base64,{$imageData['youtubeIcon']}" alt="YouTube" style="width: 24px; height: 24px; margin-right: 10px;"></a>
          </div>
        </div>
        <p style="margin-bottom: 2px; text-align: center; font-size: 12px;">* This is a computer generated receipt, signature is not required.</p>
      </body>
    </html>
    HTML;

    return $html;
  }

}
