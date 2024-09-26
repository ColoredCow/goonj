<?php

/**
 *
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Token\AbstractTokenSubscriber;
use Civi\Token\TokenRow;

/**
 *
 */
class CRM_Goonjcustom_Token_CollectionCamp extends AbstractTokenSubscriber {

  const ACTIVITY_TARGET_RECORD_TYPE_ID = 3;

  public function __construct() {
    parent::__construct('collection_camp', [
      'venue' => \CRM_Goonjcustom_ExtensionUtil::ts('Venue'),
      'date' => \CRM_Goonjcustom_ExtensionUtil::ts('Date'),
      'time' => \CRM_Goonjcustom_ExtensionUtil::ts('Time'),
      'volunteers' => \CRM_Goonjcustom_ExtensionUtil::ts('Volunteers'),
      'goonj_team' => \CRM_Goonjcustom_ExtensionUtil::ts('Goonj Team'),
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

    $collectionSource = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('title', 'Collection_Camp_Intent_Details.*', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $row->context['collectionSourceId'])
      ->execute()->single();

    switch ($field) {
      case 'venue':
        $value = $this->formatVenue($collectionSource);
        break;

      case 'date':
      case 'time':
        $start = new DateTime($collectionSource['Collection_Camp_Intent_Details.Start_Date']);
        $end = new DateTime($collectionSource['Collection_Camp_Intent_Details.End_Date']);
        $value = $field === 'date' ? $this->formatDate($start, $end) : $this->formatTime($start, $end);
        break;

      case 'volunteers':
        $value = $this->formatVolunteers($collectionSource);
        break;

      case 'goonj_team':
        $value = $this->formatGoonjTeam($collectionSource);
        break;

      default:
        $value = '';

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
      ->addWhere('activity_type_id:name', '=', 'Volunteering')
      ->addWhere('Volunteering_Activity.Collection_Camp', '=', $collectionSource['id'])
      ->addWhere('activity_contact.record_type_id', '=', self::ACTIVITY_TARGET_RECORD_TYPE_ID)
      ->execute();

    $volunteerIds = array_merge([$initiatorId], $volunteeringActivities->column('activity_contact.contact_id'));

    $volunteers = Contact::get(FALSE)
      ->addSelect('phone.phone', 'display_name')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('phone.is_primary', '=', FALSE)
      ->addWhere('id', 'IN', $volunteerIds)
      ->execute();

    $volunteersWithPhone = array_map(
        fn ($volunteer) => sprintf('%1$s (%2$s)', $volunteer['display_name'], $volunteer['phone.phone']), $volunteers->jsonSerialize()
    );

    return join(',', $volunteersWithPhone);
  }

  /**
   *
   */
  private function formatVenue($collectionSource) {
    $addressParts = [
      $collectionSource['Collection_Camp_Intent_Details.Location_Area_of_camp'],
      $collectionSource['Collection_Camp_Intent_Details.District'],
      $collectionSource['Collection_Camp_Intent_Details.City'],
      CRM_Core_PseudoConstant::stateProvince($collectionSource['Collection_Camp_Intent_Details.State']),
      $collectionSource['Collection_Camp_Intent_Details.Pin_Code'],
    ];

    return join(', ', array_filter($addressParts));

  }

  /**
   *
   */
  private function formatGoonjTeam($collectionSource) {
    $urbanPocId = $collectionSource['Collection_Camp_Intent_Details.Coordinating_Urban_POC'];

    $urbanPocPhones = Contact::get(FALSE)
      ->addSelect('phone.phone', 'display_name')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $urbanPocId)
      ->execute();

    $first = $urbanPocPhones->first();

    if (!$first) {
      return '';
    }

    $displayName = $first['display_name'];

    $phoneNumbers = $urbanPocPhones->column('phone.phone');

    return sprintf('%1$s (%2$s)', $displayName, join(',', $phoneNumbers));
  }

}
