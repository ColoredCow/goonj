<?php

/**
 *
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Phone;
use Civi\Token\AbstractTokenSubscriber;
use Civi\Token\TokenRow;

/**
 *
 */
class CRM_Goonjcustom_Token_DroppingCenter extends AbstractTokenSubscriber {

  const ACTIVITY_TARGET_RECORD_TYPE_ID = 3;

  public function __construct() {
    parent::__construct('dropping_center', [
      'venue' => \CRM_Goonjcustom_ExtensionUtil::ts('Venue'),
      'date' => \CRM_Goonjcustom_ExtensionUtil::ts('Date'),
      'time' => \CRM_Goonjcustom_ExtensionUtil::ts('Timing'),
      'volunteers' => \CRM_Goonjcustom_ExtensionUtil::ts('Volunteers'),
      'coordinator' => \CRM_Goonjcustom_ExtensionUtil::ts('Coordinator (Goonj)'),
      'remarks' => \CRM_Goonjcustom_ExtensionUtil::ts('Remarks'),
      'address_city' => \CRM_Goonjcustom_ExtensionUtil::ts('City'),
      'landmark' => \CRM_Goonjcustom_ExtensionUtil::ts('Landmark or Nearby Area'),
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
      \Civi::log()->debug(__CLASS__ . '::' . __METHOD__ . ' There is no collectionSourceId in the context, you can\'t use dropping_center tokens.');
      $row->format('text/plain')->tokens($entity, $field, '');
      return;
    }

    $newCustomData = $row->context['collectionSourceCustomData'];

    $currentCustomData = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('custom.*')
      ->addWhere('id', '=', $row->context['collectionSourceId'])
      ->execute()->single();

    $collectionSource = array_merge($currentCustomData, $newCustomData);

    switch ($field) {
      case 'venue':
        $value = $this->formatVenue($collectionSource);
        break;

      case 'date':

      case 'time':
        $value = $collectionSource['Dropping_Centre.Timing'];
        break;

      case 'volunteers':
        $value = $this->formatVolunteers($collectionSource);
        break;

      case 'remarks':
        $value = $collectionSource['Collection_Camp_Core_Details.Remarks'];
        break;

      case 'coordinator':
        $value = $this->formatCoordinator($collectionSource);
        break;

      case 'address_city':
        $value = $collectionSource['Dropping_Centre.District_City'];
        break;

      case 'landmark':
        $landmarkOrNearbyArea = $collectionSource['Dropping_Centre.Landmark_or_Near_by_area'];

        if (!empty($landmarkOrNearbyArea)) {
          $value = "(" . $landmarkOrNearbyArea . ")";
        }
        else {
          $value = '';
        }
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
    $initiatorId = $collectionSource['Collection_Camp_Core_Details.Contact_Id'];

    $volunteeringActivities = Activity::get(FALSE)
      ->addSelect('activity_contact.contact_id')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addWhere('activity_type_id:name', '=', 'Volunteer Coordinator')
      ->addWhere('Coordinator.Dropping_Center', '=', $collectionSource['id'])
      ->addWhere('activity_contact.record_type_id', '=', self::ACTIVITY_TARGET_RECORD_TYPE_ID)
      ->execute();

    $volunteerIds = array_merge([$initiatorId], $volunteeringActivities->column('activity_contact.contact_id'));

    $volunteers = Contact::get(FALSE)
      ->addSelect('phone.phone', 'phone.is_primary', 'display_name')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', 'IN', $volunteerIds)
      ->addOrderBy('created_date', 'ASC')
      ->execute();

    $volunteersArray = $volunteers->jsonSerialize();
    $volunteersDetails = [];

    foreach ($volunteerIds as $volunteerId) {
      $primaryVolunteers = array_filter($volunteersArray, function ($volunteer) use ($volunteerId) {
        return $volunteer['id'] == $volunteerId && $volunteer['phone.is_primary'];
      });

      if (!empty($primaryVolunteer)) {
        $volunteersDetails[] = reset($primaryVolunteers);
      }
      else {
        $volunteer = array_filter($volunteersArray, function ($volunteer) use ($volunteerId) {
          return $volunteer['id'] == $volunteerId;
        });

        if (!empty($volunteer)) {
          $volunteersDetails[] = reset($volunteer);
        }
      }

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
      $collectionSource['Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_'] ?? '',
      $collectionSource['Dropping_Centre.District_City'] ?? '',
        // Uncomment if needed
        // CRM_Core_PseudoConstant::stateProvince($collectionSource['Dropping_Centre.State']) ?? '',
        // $collectionSource['Dropping_Centre.Postal_Code'] ?? '',.
    ];

    return join(', ', array_filter($addressParts));

  }

  /**
   *
   */
  private function formatCoordinator($collectionSource) {
    $officeId = $collectionSource['Dropping_Centre.Goonj_Office'];

    $officePhones = Phone::get(FALSE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $officeId)
      ->execute();

    $phoneNumbers = $officePhones->column('phone');

    return sprintf('%1$s (%2$s)', \CRM_Goonjcustom_ExtensionUtil::ts('Team Goonj'), join(', ', $phoneNumbers));
  }

}
