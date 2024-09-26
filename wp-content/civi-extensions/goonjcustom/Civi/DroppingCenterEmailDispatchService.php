<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class DroppingCenterEmailDispatchService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => 'postProcess',
    ];
  }

  /**
   *
   */
  public static function postProcess(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName === 'Eck_Collection_Camp' && ($op === 'create' || $op === 'edit')) {

      $jsonData = $objectname->data;

      $dataArray = json_decode($jsonData, TRUE);

      $id = $dataArray['Eck_Collection_Camp1'][0]['fields']['id'];
      $droppingCenterId = $objectRef->id;

      $droppingCenterData = civicrm_api4('Eck_Collection_Camp', 'get', [
        'select' => [
          'Collection_Camp_Core_Details.Contact_Id',
        ],
        'where' => [
        ['id', '=', $droppingCenterId],
        ],
      ]);

      $contactId = NULL;
      if (!empty($droppingCenterData)) {
        $droppingCenter = $droppingCenterData[0] ?? [];
        $contactId = $droppingCenter['Collection_Camp_Core_Details.Contact_Id'] ?? NULL;
        $droppingCenterGoonjOffice = $droppingCenter['Collection_Camp_Intent_Details.Goonj_Office'] ?? NULL;
        ;
      }

      $contactData = civicrm_api4('Contact', 'get', [
        'select' => [
          'email_primary.email',
          'phone_primary.phone',
          'display_name',
        ],
        'where' => [
          ['id', '=', $contactId],
        ],
        'limit' => 1,
      ]);

      $contactDataArray = $contactData[0] ?? [];
      $email = $contactDataArray['email_primary.email'] ?? 'N/A';
      $phone = $contactDataArray['phone_primary.phone'] ?? 'N/A';
      $name = $contactDataArray['display_name'] ?? 'N/A';

      self::sendCampEmail($email, $name, $droppingCenterId, $contactId, $droppingCenterGoonjOffice);
    }
  }

  /**
   *
   */
  public static function sendCampEmail($email, $name, $droppingCenterId, $contactId, $droppingCenterGoonjOffice) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp_Intent_Id=' . $droppingCenterId;
    $campOutcomeFormUrl = $homeUrl . '/camp-outcome-form/#?Eck_Collection_Camp1=' . $droppingCenterId . '&Camp_Outcome.Filled_By=' . $contactId;

    // Note: The content will need to be updated once the final email template is available.
    $emailHtml = "
      <html>
      <body>
        <p>Dear {$name},</p>
        <p>Thank you for your involvement in the collection camp (ID: {$droppingCenterId}).</p>
        <p>Here is the <a href='{$campVehicleDispatchFormUrl}'>Vehicle Dispatch Form</a> for your reference.</p>
        <ul>
        <li><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a></li>
      </ul>
        <p>Best regards,<br>Goonj Team</p>
      </body>
      </html>
    ";

    $mailParams = [
      'subject' => 'Dropping Center Notification',
      'from' => 'urban.ops@goonj.org',
      'toEmail' => $email,
      'html' => $emailHtml,
    ];

    \CRM_Utils_Mail::send($mailParams);
  }

}
