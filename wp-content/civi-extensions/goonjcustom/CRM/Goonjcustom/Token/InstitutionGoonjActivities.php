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
class CRM_Goonjcustom_Token_InstitutionGoonjActivities extends AbstractTokenSubscriber {

  const ACTIVITY_TARGET_RECORD_TYPE_ID = 3;

  public function __construct() {
    parent::__construct('institution_goonj_activitites', [
      'venue' => \CRM_Goonjcustom_ExtensionUtil::ts('Venue'),
      'date' => \CRM_Goonjcustom_ExtensionUtil::ts('Date'),
      'time' => \CRM_Goonjcustom_ExtensionUtil::ts('Time'),
      'contact' => \CRM_Goonjcustom_ExtensionUtil::ts('Contacts'),
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

    switch ($field) {
      case 'venue':
        $value = $this->formatVenue($collectionSource);
        break;

      case 'date':
      case 'time':
      case 'type':
        $start = new DateTime($collectionSource['Institution_Goonj_Activities.Start_Date']);
        $end = new DateTime($collectionSource['Institution_Goonj_Activities.End_Date']);

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
        $value = $collectionSource['Institution_Goonj_Activities.City'];
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
    $initiatorId = $collectionSource['Institution_Goonj_Activities.Organization_Name'];

    $volunteeringActivities = Activity::get(FALSE)
      ->addSelect('activity_contact.contact_id')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addWhere('activity_type_id:name', '=', 'Volunteering')
      ->addWhere('Volunteering_Activity.Institution_Goonj_Activities', '=', $collectionSource['id'])
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
      $collectionSource['Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'],
      $collectionSource['Institution_Goonj_Activities.City'],
    ];

    return join(', ', array_filter($addressParts));

  }

  /**
   *
   */
  private function formatCoordinator($collectionSource) {
    $officeId = $collectionSource['Institution_Goonj_Activities.Goonj_Office'];

    $officePhones = Phone::get(FALSE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $officeId)
      ->execute();

    $phoneNumbers = $officePhones->column('phone');

    return sprintf('%1$s (%2$s)', \CRM_Goonjcustom_ExtensionUtil::ts('Team Goonj'), join(', ', $phoneNumbers));
  }

}
