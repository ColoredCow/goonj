<?php

/**
 * @file
 */

// Include CRM_Core_Error directly for API v3.
require_once 'CRM/Core/Error.php';
use Civi\Api4\Phone;
use Civi\Api4\Participant;
use CRM\Civiglific\GlificClient;

/**
 * Define API spec.
 */
function _civicrm_api3_civicrm_glific_civicrm_glific_send_whatsapp_qr_cron_spec(&$spec) {}

/**
 * Cron job to trigger a WhatsApp Flow for Bangalore Chaupal.
 */
function civicrm_api3_civiglific_civicrm_glific_send_whatsapp_qr_cron($params) {
  error_log('testing: Entered civicrm_api3_civiglific_civicrm_glific_send_whatsapp_qr_cron');
  $eventId = $params['event_id'] ?? 35;
  $returnValues = [];

  try {
    error_log('testing: Fetching participants for event ID: ' . $eventId);
    $participants = Participant::get(FALSE)
      ->addSelect('id', 'contact_id')
      ->addWhere('event_id', '=', $eventId)
      ->addWhere('status_id:label', '=', 'Registered')
      ->setLimit(0)
      ->execute();

    $glificClient = new GlificClient();
    error_log('testing: Initialized GlificClient');

    foreach ($participants as $participant) {
      $contactId = $participant['contact_id'];

      $participant_id = qrcodecheckin_participant_id_for_contact_id($contactId, $eventId);

      if ($participant_id) {
        $code = qrcodecheckin_get_code($participant_id);
        error_log('code: ' . print_r($code, TRUE));

        // First ensure the image file is created.
        qrcodecheckin_create_image($code, $participant_id);

        // Get the absolute link to the image that will display the QR code.
        $query = NULL;
        $absolute = TRUE;
        $link = qrcodecheckin_get_image_url($code);
        error_log('link: ' . print_r($link, TRUE));

        $values[$contact_id]['qrcodecheckin.qrcode_url_' . $event_id] = $link;
        $values[$contact_id]['qrcodecheckin.qrcode_html_' . $event_id] = E::ts('<div><img alt="QR Code with link to checkin page" src="%1"></div><div>You should see a QR code above which will be used to quickly check you into the event. If you do not see a code display above, please enable the display of images in your email program or try accessing it <a href="%1">directly</a>. You may want to take a screen grab of your QR Code in case you need to display it when you do not have Internet access.</div>', [
          1 => $link,
        ]);
      }

      error_log('testing: Processing participant with contactId: ' . $contactId);

      $glificContactId = getGlificContactId($contactId);
      error_log('testing: Retrieved glificContactId: ' . $glificContactId);

      if (empty($glificContactId)) {
        CRM_Core_Error::debug_log_message("No Glific contact ID found for CiviCRM contact ID: $contactId");
        error_log('testing: Skipping due to empty glificContactId for contactId: ' . $contactId);
        continue;
      }

      // Trigger the Flow instead of sending a direct message.
      // Replace with your Flow name.
      $flowName = "TestFlow";
      $result = triggerFlow($glificClient, $glificContactId, $flowName);
      error_log('testing: Trigger flow result: ' . print_r($result, TRUE));

      if ($result['success']) {
        $returnValues[] = "Flow triggered for contact ID: $contactId";
      }
      else {
        CRM_Core_Error::debug_log_message("Failed to trigger flow for contact ID: $contactId - " . print_r($result, FALSE));
        error_log('testing: Failed to trigger flow for contactId: ' . $contactId);
      }
    }

    error_log('testing: Returning success with returnValues: ' . print_r($returnValues, TRUE));
    return civicrm_api3_create_success($returnValues, $params, 'Civiglific', 'civicrm_glific_send_whatsapp_qr_cron');
  }
  catch (Exception $e) {
    CRM_Core_Error::debug_log_message("Error in trigger_flow job: " . $e->getMessage());
    error_log('testing: Caught exception: ' . $e->getMessage());
    return civicrm_api3_create_error("An error occurred: " . $e->getMessage());
  }
}

// /**
//  * Fetch participant_id from contact_id.
//  */
// function qrcodecheckin_participant_id_for_contact_id($contact_id, $event_id) {

//   $sql = "SELECT p.id FROM civicrm_contact c JOIN civicrm_participant p
//     ON c.id = p.contact_id WHERE c.is_deleted = 0 AND c.id = %0 AND p.event_id = %1";
//   $params = [
//     0 => [$contact_id, 'Integer'],
//     1 => [$event_id, 'Integer'],
//   ];
//   $dao = CRM_Core_DAO::executeQuery($sql, $params);
//   if ($dao->N == 0) {
//     return NULL;
//   }
//   $dao->fetch();
//   return $dao->id;
// }

/**
 * Create a hash based on the participant id.
 */
// function qrcodecheckin_get_code($participant_id) {
//   $sql = "SELECT hash FROM civicrm_contact c JOIN civicrm_participant p ON c.id = p.contact_id
//    WHERE p.id = %0";
//   $dao = CRM_Core_DAO::executeQuery($sql, [0 => [$participant_id, 'Integer']]);
//   if ($dao->N == 0) {
//     return FALSE;
//   }
//   $dao->fetch();
//   $user_hash = $dao->hash;
//   return hash('sha256', $participant_id . $user_hash . CIVICRM_SITE_KEY);
// }

/**
 * Create the qr image file
 */
// function qrcodecheckin_create_image($code, $participant_id) {
//   $path = qrcodecheckin_get_path($code);
//   if (!file_exists($path)) {
//     // Since we are saving a file, we don't want base64 data.
//     $url = qrcodecheckin_get_url($code, $participant_id);
//     $base64 = FALSE;
//     $data = qrcodecheckin_get_image_data($url, $base64);
//     file_put_contents($path, $data);
//   }
// }

/**
 * Helper to return absolute URL to qrcode image file.
 *
 * This is the URL to the image file containing the QR code.
 */
// function qrcodecheckin_get_image_url($code) {
//   $civiConfig = CRM_Core_Config::singleton();
//   return $civiConfig->imageUploadURL . '/qrcodecheckin/' . $code . '.png';
// }


/**
 * Get QRCode image data.
 */
// function qrcodecheckin_get_image_data($url, $base64 = TRUE) {
//   require_once __DIR__ . '/vendor/autoload.php';
//   $options = new chillerlan\QRCode\QROptions(
//     [
//       'outputType' => chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
//       'imageBase64' => $base64,
//       'imageTransparent' => FALSE,
//     ]
//   );
//   return (new chillerlan\QRCode\QRCode($options))->render($url);
// }

/**
 * Get URL for checkin.
 *
 * This is the URL that the QR Code points to when it is
 * read. See qrcodecheckin_get_image_url for the URL of the image
 * file that displays the QR Code.
 */
// function qrcodecheckin_get_url($code, $participant_id) {
//   $query = NULL;
//   $absolute = TRUE;
//   $fragment = NULL;
//   $htmlize = FALSE;
//   $frontend = TRUE;
//   return CRM_Utils_System::url('civicrm/qrcodecheckin/' . $participant_id . '/' . $code, $query, $absolute, $fragment, $htmlize, $frontend);
// }


/**
 * Helper to return absolute file system path to qrcode image file.
 *
 * This is the path to the image file containing the QR code.
 */
// function qrcodecheckin_get_path($code) {
//   $civiConfig = CRM_Core_Config::singleton();
//   return $civiConfig->imageUploadDir . '/qrcodecheckin/' . $code . '.png';
// }



/**
 * Retrieve Glific contact ID dynamically using phone number.
 *
 * @param int $contactId
 *   CiviCRM contact ID.
 *
 * @return string|null
 *   Glific contact ID or null if not found.
 */
function getGlificContactId($contactId) {
  error_log('testing: Entering getGlificContactId for contactId: ' . $contactId);
  $phoneResult = Phone::get(FALSE)
    ->addSelect('phone')
    ->addWhere('contact_id', '=', $contactId)
    ->execute()
    ->first();
  error_log('testing: Phone result: ' . print_r($phoneResult, TRUE));

  if (empty($phoneResult['phone'])) {
    error_log('testing: No phone found for contactId: ' . $contactId);
    return NULL;
  }

  $phone = $phoneResult['phone'];
  $glificClient = new GlificClient();
  error_log('testing: Querying Glific for phone: ' . $phone);

  return $glificClient->getContactIdByPhone($phone);
}

/**
 * Trigger a Flow for a specific contact using Glific API.
 *
 * @param \CRM\Civiglific\GlificClient $client
 *   GlificClient instance.
 * @param string $receiverId
 *   Glific contact ID of the receiver.
 * @param string $flowName
 *   Name of the Flow to trigger.
 *
 * @return array
 *   Response with success status.
 */
function triggerFlow($client, $receiverId, $flowName) {
  error_log('testing: Entering triggerFlow with receiverId: ' . $receiverId . ' and flowName: ' . $flowName);
  $query = <<<'GQL'
    mutation TriggerContactFlow($input: TriggerContactFlowInput!) {
      triggerContactFlow(input: $input) {
        flow {
          id
          name
        }
        errors {
          message
        }
      }
    }
  GQL;

  $variables = [
    'input' => [
      'contactId' => $receiverId,
      'flowName' => $flowName,
    ],
  ];
  error_log('testing: Trigger flow variables: ' . print_r($variables, TRUE));

  $response = $client->query($query, $variables);
  error_log('testing: Trigger flow response: ' . print_r($response, TRUE));

  $result = $response['data']['triggerContactFlow'] ?? [];
  $success = empty($result['errors']);
  return [
    'success' => $success,
    'data' => $result,
  ];
}
