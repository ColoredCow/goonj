<?php

namespace Civi;

use Civi\Api4\Afform;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class OfficeVisitService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&civicrm_postProcess' => 'addOfficeIdQueryParam',
    ];
  }

  /**
   *
   */
  public static function addOfficeIdQueryParam($formName, &$form) {
    // $formName === "afformOfficeVisitIndividualRegistration"
    \Civi::log()->debug('addOfficeIdQueryParam', [
      'formName' => $formName,
      'form' => $form,
    ]);

    // This function is called from many place.
    // We want to add a condition based on $formName and $form
    // so that the code returns if it is not the office visit form.
    if (1) {
      return FALSE;
    }

    $params = self::retrieveParams();

    $afforms = Afform::get(TRUE)
      ->addWhere('name', '=', 'afformOfficeVisitIndividualRegistration')
      ->execute();

    $formSettings = $afforms->first();

    // "/processing-center/office-visit/details/"
    $baseRedirectUrl = $formSettings['redirect'];

    $redirectUrl = sprintf(
        '%1$s%2$s&Office_Visit.Goonj_Processing_Center=%3$s',
        $baseRedirectUrl,
        "?#id=" . $params['source_contact_id'],
        $params['Office_Visit.Goonj_Processing_Center']
    );

    CRM_Utils_System::redirect($redirectUrl);

  }

  /**
   *
   */
  private static function retrieveParams($formSubmission) {
    // This will be removed once the logic is ready.
    $params = [
      'source_contact_id' => 0,
      'Office_Visit.Goonj_Processing_Center' => 0,
    ]

    // Using the $form object, can we retrieve the value of "source_contact_id" & "Individual_fields.Source_Processing_Center"
    // If we are able retrieve this value, then we can do the following:
    return $params;
  }

}
