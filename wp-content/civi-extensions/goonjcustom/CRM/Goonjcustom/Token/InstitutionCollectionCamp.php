<?php

/**
 *
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Phone;
use Civi\Api4\Relationship;
use Civi\Token\AbstractTokenSubscriber;
use Civi\Token\TokenRow;

/**
 *
 */
class CRM_Goonjcustom_Token_InstitutionCollectionCamp extends AbstractTokenSubscriber {

  const ACTIVITY_TARGET_RECORD_TYPE_ID = 3;

  public function __construct() {
    parent::__construct('institution_collection_camp', [
      'venue' => \CRM_Goonjcustom_ExtensionUtil::ts('Venue'),
      'date' => \CRM_Goonjcustom_ExtensionUtil::ts('Date'),
      'time' => \CRM_Goonjcustom_ExtensionUtil::ts('Time'),
      'volunteers' => \CRM_Goonjcustom_ExtensionUtil::ts('Volunteers'),
      'coordinator' => \CRM_Goonjcustom_ExtensionUtil::ts('Coordinator (Goonj)'),
      'remarks' => \CRM_Goonjcustom_ExtensionUtil::ts('Remarks'),
      'type' => \CRM_Goonjcustom_ExtensionUtil::ts('Type (Camp/Drive)'),
      'address_city' => \CRM_Goonjcustom_ExtensionUtil::ts('City'),
    ]);
  }

  /**
   *
   */
  public function evaluateToken(
    TokenRow $row,
    $entity,
    $field,
    $prefetch = NULL,
  ) {

    if (empty($row->context['collectionSourceId'])) {
      \Civi::log()->debug(__CLASS__ . '::' . __METHOD__ . ' There is no collectionSourceId in the context, you can\'t use collection_camp tokens.');
      $row->format('text/plain')->tokens($entity, $field, '');
      return;
    }

    $newCustomData = $row->context['collectionSourceCustomData'];

    $currentCustomData = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('custom.*')
      ->addWhere('id', '=', $row->context['collectionSourceId'])
      ->execute()->single();

    $collectionSource = array_merge($currentCustomData, $newCustomData);
    error_log("collectionSource: " . print_r($collectionSource, TRUE));
    switch ($field) {
      case 'venue':
        $value = $this->formatVenue($collectionSource);
        break;

      case 'date':
      case 'time':
      case 'type':
        $start = new DateTime($collectionSource['Collections_will_start_on_Date_']);
        $end = new DateTime($collectionSource['Collections_will_end_on_Date_']);

        if ($field === 'type') {
          $value = $start->format('Y-m-d') === $end->format('Y-m-d') ? 'Camp' : 'Drive';
        }
        elseif ($field === 'date') {
          $value = $this->formatDate($start, $end);
        }
        else {
          $value = $this->formatTime($start, $end);
        }
        break;

      case 'volunteers':
        $value = $this->formatVolunteers($collectionSource);
        break;

      case 'coordinator':
        $value = $this->formatCoordinator($collectionSource);
        break;

      case 'address_city':
        $value = $collectionSource['Institution_Collection_Camp_Intent.District_City'];
        break;

      default:
        $value = '';
        break;

    }

    $row->format('text/html')->tokens($entity, $field, $value);
    $row->format('text/plain')->tokens($entity, $field, $value);
    return;

  }

  /**
   *
   */
  private function formatDate($start, $end) {

    $startFormatted = $start->format('M jS, Y (l)');
    $endFormatted = $end->format('M jS, Y (l)');

    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
      return $startFormatted;
    }

    return "$startFormatted - $endFormatted";
  }

  /**
   *
   */
  private function formatTime($start, $end) {

    $startTime = $start->format('h:i A');
    $endTime = $end->format('h:i A');

    return "$startTime to $endTime";
  }

  /**
   *
   */
  private function formatVolunteers($collectionSource) {
    $organizationId = $collectionSource['Institution_Collection_Camp_Intent.Organization_Name'];

    $relationships = Relationship::get(FALSE)
      ->addWhere('contact_id_a', '=', $organizationId)
      ->addWhere('relationship_type_id:name', '=', 'Primary Institution POC of')
      ->execute();

    // If no relationships found for 'Primary Institution POC of', check for 'Secondary Institution POC of'.
    if (empty($relationships)) {
      $relationships = Relationship::get(FALSE)
        ->addWhere('contact_id_a', '=', $organizationId)
        ->addWhere('relationship_type_id:name', '=', 'Secondary Institution POC of')
        ->execute();
    }

    // Return contact_id_b as initiator if found.
    $initiatorId = NULL;
    if (!empty($relationships) && isset($relationships[0]['contact_id_b'])) {
      $initiatorId = $relationships[0]['contact_id_b'];
    }

    if (!$initiatorId) {
      return '';
    }

    $volunteers = Contact::get(FALSE)
      ->addSelect('phone.phone', 'phone.is_primary', 'display_name', 'id')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $initiatorId)
      ->execute();

    $volunteersArray = $volunteers->jsonSerialize();
    $volunteersDetails = [];

    $primaryVolunteer = array_filter($volunteersArray, function ($volunteer) {
        return $volunteer['phone.is_primary'];
    });

    if (!empty($primaryVolunteer)) {
      $volunteersDetails[] = reset($primaryVolunteer);
    }
    else {
      $volunteersDetails[] = reset($volunteersArray);
    }

    $volunteersWithPhone = array_map(
        fn($volunteer) => isset($volunteer['phone.phone']) && !empty($volunteer['phone.phone'])
            ? sprintf('%1$s (%2$s)', $volunteer['display_name'], $volunteer['phone.phone'])
            : $volunteer['display_name'],
        $volunteersDetails
    );

    return join(', ', $volunteersWithPhone);
  }

  /**
   *
   */
  private function formatVenue($collectionSource) {
    $addressParts = [
      $collectionSource['Institution_Collection_Camp_Intent.Collection_Camp_Address'],
      $collectionSource['Institution_Collection_Camp_Intent.District_City'],
    ];

    return join(', ', array_filter($addressParts));

  }

  /**
   *
   */
  private function formatCoordinator($collectionSource) {
    $officeId = $collectionSource['Institution_collection_camp_Review.Goonj_Office'];

    $officePhones = Phone::get(FALSE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $officeId)
      ->execute();

    $phoneNumbers = $officePhones->column('phone');

    return sprintf('%1$s (%2$s)', \CRM_Goonjcustom_ExtensionUtil::ts('Team Goonj'), join(', ', $phoneNumbers));
  }

}
