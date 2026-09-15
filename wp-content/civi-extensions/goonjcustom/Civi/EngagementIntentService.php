<?php

namespace Civi;

use Civi\Api4\Address;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Core\Service\AutoSubscriber;

/**
 * Handles the public Goonj Engagement Intent (JSM) form.
 *
 * The form captures an Eck_Engagement_Intent record alongside the Individual
 * who filled it in. The state and city live on the intent record, but chapter
 * mapping is driven off the contact, so both are copied onto the contact's
 * address and the contact is added to the chapter covering that state.
 */
class EngagementIntentService extends AutoSubscriber {

  const ENTITY_NAME = 'Eck_Engagement_Intent';
  const AFFORM_ENTITY_NAME = 'Eck_Engagement_Intent1';
  const CUSTOM_GROUP_NAME = 'Engagement_Intent_Form';
  const AFFORM_NAME = 'afformGoonjEngagementIntentForm';

  /**
   * India.
   */
  const DEFAULT_COUNTRY_ID = 1101;

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['processEngagementIntentSubmission'],
      ],
    ];
  }

  /**
   * Copies the intent location onto the contact and maps them to a chapter.
   *
   * The submission row is written first and then updated with the ids of the
   * entities that were saved, so the contact id is only available on the edit
   * pass.
   */
  public static function processEngagementIntentSubmission(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
      return;
    }

    $afformName = $objectRef['afform_name'] ?? NULL;

    if ($afformName !== NULL && $afformName !== self::AFFORM_NAME) {
      return;
    }

    $data = $objectRef['data'] ?? [];

    if (empty($data[self::AFFORM_ENTITY_NAME]) || empty($data['Individual1'])) {
      return;
    }

    $fields = $data[self::AFFORM_ENTITY_NAME][0]['fields'] ?? [];
    $stateProvinceId = $fields[self::CUSTOM_GROUP_NAME . '.State'] ?? NULL;
    $city = $fields[self::CUSTOM_GROUP_NAME . '.City'] ?? NULL;
    $countryId = $fields[self::CUSTOM_GROUP_NAME . '.Country'] ?: self::DEFAULT_COUNTRY_ID;

    if (!$stateProvinceId) {
      \Civi::log()->info('Engagement intent: submission has no state, skipping chapter mapping.');
      return;
    }

    $groupId = self::getChapterGroupForState($stateProvinceId);

    if (!$groupId) {
      \Civi::log()->warning("Engagement intent: no chapter group resolved for state $stateProvinceId.");
    }

    foreach ($data['Individual1'] as $individual) {
      $contactId = $individual['id'] ?? NULL;

      if (!$contactId) {
        continue;
      }

      self::setContactAddress($contactId, $stateProvinceId, $city, $countryId);

      if ($groupId) {
        self::addContactToGroup($contactId, $groupId);
      }
    }
  }

  /**
   * Writes the intent state/city onto the contact.
   *
   * An existing contact keeps its primary address and only has the location
   * refreshed; a contact without one gets a home address created.
   */
  private static function setContactAddress($contactId, $stateProvinceId, $city, $countryId) {
    try {
      $address = Address::get(FALSE)
        ->addSelect('id')
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('is_primary', '=', TRUE)
        ->execute()->first();

      if ($address) {
        Address::update(FALSE)
          ->addValue('state_province_id', $stateProvinceId)
          ->addValue('country_id', $countryId)
          ->addValue('city', $city)
          ->addWhere('id', '=', $address['id'])
          ->execute();

        return;
      }

      Address::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('state_province_id', $stateProvinceId)
        ->addValue('country_id', $countryId)
        ->addValue('city', $city)
        ->addValue('location_type_id', 1)
        ->addValue('is_primary', TRUE)
        ->execute();
    }
    catch (\Exception $e) {
      \Civi::log()->error("Engagement intent: error saving address for contact $contactId. " . $e->getMessage());
    }
  }

  /**
   * Resolves the chapter group whose catchment covers the given state.
   *
   * Falls back to the chapter flagged as the fallback (Delhi) when no chapter
   * claims the state.
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroup = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute()->first();

    if (!$stateContactGroup) {
      $stateContactGroup = Group::get(FALSE)
        ->addSelect('id')
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute()->first();
    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

  /**
   * Adds a contact to a group unless they are already in it.
   */
  private static function addContactToGroup($contactId, $groupId) {
    $groupContact = GroupContact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('group_id', '=', $groupId)
      ->execute()->first();

    if (!empty($groupContact)) {
      return;
    }

    try {
      GroupContact::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('group_id', $groupId)
        ->addValue('status', 'Added')
        ->execute();

      \Civi::log()->info("Engagement intent: contact $contactId added to group $groupId.");
    }
    catch (\Exception $e) {
      \Civi::log()->error("Engagement intent: error adding contact $contactId to group $groupId. " . $e->getMessage());
    }
  }

}
