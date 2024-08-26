<?php

/**
 *
 */

use Civi\Api4\Activity;
use Civi\Api4\Address;
use Civi\Api4\Event;
use Civi\Api4\LocBlock;
use Civi\Api4\StateProvince;

/**
 *
 */
class CRM_Goonjcustom_CivirulesAction_CreateEventForContact extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action.
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    $originalData = $triggerData->getOriginalData();
    $activityId = $originalData['activity_id'] ?? NULL;

    // Fetch the updated data.
    $updatedActivityApi = Activity::get(TRUE)
      ->addSelect('*', 'custom.*')
      ->addWhere('id', '=', $activityId)
      ->setLimit(1)
      ->execute();

    $updatedActivity = $updatedActivityApi->first();

    // Extract fields.
    $startDate = $updatedActivity['Collection_Camp_Intent.Start_Date'] ?? NULL;
    $endDate = $updatedActivity['Collection_Camp_Intent.End_Date'] ?? NULL;
    $contactId = $originalData['contact_id'] ?? NULL;
    $location = $updatedActivity['Collection_Camp_Intent.Location_Area_of_camp'] ?? NULL;
    $state = $updatedActivity['Collection_Camp_Intent.State'] ?? NULL;
    $postalCode = $updatedActivity['Collection_Camp_Intent.Pin_Code'] ?? NULL;
    $city = $updatedActivity['Collection_Camp_Intent.City'] ?? NULL;
    $createdDate = $updatedActivity['created_date'] ?? NULL;

    // Save an address for the contact.
    try {
      $addressResult = Address::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('street_address', $location)
        ->addValue('city', $city)
        ->addValue('state_province_id', $state)
        ->addValue('postal_code', $postalCode)
        ->setFixAddress(FALSE)
        ->execute();

      $addressId = $addressResult->first()['id'] ?? NULL;
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    // Create a location block with the address ID.
    try {
      $locBlockResult = LocBlock::create(FALSE)
        ->addValue('address_id', $addressId)
        ->execute();

      // Fetch the location block ID from the result.
      $locBlockData = $locBlockResult->first();
      $locBlockId = $locBlockData['id'] ?? NULL;

    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    $eventParams = [
      'title' => $this->getEventCode($createdDate, $addressId),
      'event_type_id' => 7,
      'start_date' => $startDate,
      'end_date' => $endDate,
      'is_active' => 1,
      'is_public' => 1,
      'default_role_id' => 1,
      'created_id' => $contactId,
      'custom_68' => $activityId,
      'loc_block_id' => $locBlockId,
    ];

    try {
      // Create the event.
      $eventResult = civicrm_api3('Event', 'create', $eventParams);
      $newEventId = $eventResult['id'];
      // Add the participant to the newly created event.
      $participantParams = [
        'event_id' => $newEventId,
        'contact_id' => $contactId,
        'status_id' => 1,
      ];

      civicrm_api3('Participant', 'create', $participantParams);
    }
    catch (CiviCRM_API3_Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  /**
   *
   */
  private function getEventCode($createdDate, $addressId) {
    $date = new DateTime($createdDate);
    $createdYear = $date->format('Y');

    // Fetch the state_province_id from the address.
    try {
      $addresses = Address::get(FALSE)
        ->addSelect('state_province_id')
        ->addWhere('id', '=', $addressId)
        ->setLimit(1)
        ->execute();

      $addressData = $addresses->first();
      $stateProvinceId = $addressData['state_province_id'] ?? NULL;
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    // Fetch the state abbreviation.
    try {
      $stateResult = StateProvince::get(FALSE)
        ->addSelect('abbreviation')
        ->addWhere('id', '=', $stateProvinceId)
        ->setLimit(1)
        ->execute();

      $stateData = $stateResult->first();
      $stateAbbreviation = $stateData['abbreviation'] ?? NULL;
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    // Fetch the goonj specific state code.
    $goonjStateCodePath = ABSPATH . 'wp-content/civi-extensions/goonjcustom/config/constants.php';
    $goonjStateCode = include $goonjStateCodePath;

    // Find the state code from the config.
    $stateCode = $goonjStateCode[$stateAbbreviation] ?? 'UNKNOWN';

    // Fetch existing collection camps for the state.
    try {
      $existingCamps = Event::get(FALSE)
        ->addSelect('title')
        ->addWhere('title', 'LIKE', "$createdYear/$stateCode/Coll/%")
        ->execute();

      $serialNumber = sprintf('%03d', $existingCamps->count() + 1);
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    return "$createdYear/$stateCode/Coll/$serialNumber";
  }

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   *
   * @return bool
   *
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
  }

}
