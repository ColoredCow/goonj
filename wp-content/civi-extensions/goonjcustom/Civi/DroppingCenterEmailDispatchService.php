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
    $afformName = $objectRef->afform_name;

    if ($afformName === 'afformSendDispatchEmail' && ($op === 'create' || $op === 'edit')) {

      $jsonData = $objectRef->data;

      $dataArray = json_decode($jsonData, TRUE);
      
      $droppingCenterId = $dataArray['Eck_Collection_Camp1'][0]['fields']['id'];

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
    error_log("email: " . print_r($email, TRUE));
    error_log("name: " . print_r($name, TRUE));
    error_log("droppingCenterId: " . print_r($droppingCenterId, TRUE));
    error_log("droppingCenterGoonjOffice: " . print_r($droppingCenterGoonjOffice, TRUE));
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $droppingCenterId . '&Camp_Vehicle_Dispatch.Filled_by=' . $filledBy . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . '&Eck_Collection_Camp1=' . $droppingCenterId;
    $campOutcomeFormUrl = $homeUrl . '/camp-outcome-form/#?Eck_Collection_Camp1=' . $droppingCenterId . '&Camp_Outcome.Filled_By=' . $contactId;

    $emailHtml = "
    <html>
    <body>
    <p>Dear {$name},</p>
    <p>Thank you so much for your invaluable efforts in running the Goonj Dropping Center. 
    Your dedication plays a crucial role in our work, and we deeply appreciate your continued support.</p>
    <p>Please fill out this Dispatch Form – <a href='{$campVehicleDispatchFormUrl}'>[link]</a> once the vehicle is loaded and ready to head to Goonj’s processing center. 
    This will help us to verify and acknowledge the materials as soon as they arrive.</p>
    <p>We truly appreciate your cooperation and continued commitment to our cause.</p>
    <p>Warm Regards,<br>Team Goonj..</p>
    </body>
    </html>
    ";
    $mailParams = [
      'subject' => 'Kindly fill the Dispatch Form for Material Pickup',
      'from' => 'urban.ops@goonj.org',
      'toEmail' => $email,
      'html' => $emailHtml,
    ];

    \CRM_Utils_Mail::send($mailParams);
  }

}
