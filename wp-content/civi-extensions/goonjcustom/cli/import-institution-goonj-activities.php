<?php

/**
 * @file
 * CLI Script to import individual goonj activities.
 */

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;
use Civi\Api4\Relationship;
use Civi\Api4\EckEntity;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 * Retrieves the office ID based on the office name.
 */
function get_office_id($office_name) {
  $office_id = '';
  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();
  if ($contacts) {
    $office_id = $contacts['id'];
  }
  return $office_id;
}

/**
 * Retrieves the initiator ID based on the mobile number.
 */
function get_initiator_id($initiator_mobile_number) {
  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('phone_primary.phone', '=', $initiator_mobile_number)
    ->execute()
    ->first();

  return $contacts['id'] ?? '';
}

/**
 * Retrieves the state ID based on the state name.
 */
function get_state_id($state_name) {
  $stateProvinces = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()->first();

  return $stateProvinces['id'] ?? '';
}

/**
 *
 */
function getFallbackCoordinator($relationshipTypeName) {
  $fallbackOffice = getFallbackOffice();
  if (!$fallbackOffice) {
    \CRM_Core_Error::debug_log_message('No fallback office found.');
    return FALSE;
  }

  // Retrieve fallback coordinators associated with the fallback office and relationship type.
  $fallbackCoordinators = Relationship::get(FALSE)
    ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
    ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
    ->addWhere('is_current', '=', TRUE)
    ->execute();

  // If no coordinators found, return false.
  if ($fallbackCoordinators->count() === 0) {
    \CRM_Core_Error::debug_log_message('No fallback coordinators found.');
    return FALSE;
  }

  // Randomly select a fallback coordinator if more than one is found.
  $randomIndex = rand(0, $fallbackCoordinators->count() - 1);
  return $fallbackCoordinators->itemAt($randomIndex);
}

/**
 *
 */
function assignCoordinatorByRelationshipType($poc_email, $state_name, $type) {
  if (empty($poc_email)) {
    $relationshipTypeMap = [
      'Corporate' => 'Corporate Coordinator of',
      'School' => 'School Coordinator of',
      'College/University' => 'College Coordinator of',
      'Association' => 'Associations Coordinator of',
      'Other' => 'Default Coordinator of',
    ];

    $registrationCategorySelection = $type;

    $registrationCategorySelection = trim($registrationCategorySelection);

    if (array_key_exists($registrationCategorySelection, $relationshipTypeMap)) {
      $relationshipTypeName = $relationshipTypeMap[$registrationCategorySelection];
    }
    else {
      $relationshipTypeName = 'Other Entities Coordinator of';
    }

    // // Retrieve the coordinators for the selected relationship type.
    $stateOfficeId = get_office_id($state_name);

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinator = getCoordinator($stateOfficeId, $relationshipTypeName, $coordinators);
    if (!$coordinator) {
      \CRM_Core_Error::debug_log_message('No coordinator available to assign.');
      return FALSE;
    }

    return $coordinator['contact_id_a'] ?? '';
  }

  $contacts = Contact::get(FALSE)
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $poc_email)
    ->execute()->first();
  return $contacts['id'] ?? '';
}

/**
 *
 */
function getCoordinator($stateOfficeId, $relationshipTypeName, $existingCoordinators = NULL) {
  if (!$existingCoordinators) {
    $existingCoordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();
  }

  if ($existingCoordinators->count() === 0) {
    return getFallbackCoordinator($relationshipTypeName);
  }

  $coordinatorCount = $existingCoordinators->count();
  return $existingCoordinators->count() > 1
      ? $existingCoordinators->itemAt(rand(0, $coordinatorCount - 1))
      : $existingCoordinators->first();
}

/**
 *
 */
function getFallbackOffice() {
  $fallbackOffices = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('organization_name', 'CONTAINS', 'Delhi')
    ->execute();

  return $fallbackOffices->first();
}

/**
 * Retrieves the coordinating POC ID based on the email.
 */
function get_coordinating_poc_id($poc_email, $stateOffice = '') {
  if (empty($poc_email)) {
    $stateOfficeId = get_state_id($stateOffice);
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', 'Goonj Activities Coordinator of')
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    $coordinatorCount = $coordinators->count();
    if ($coordinatorCount === 0) {
      $coordinator = getFallbackCoordinator();
    }
    elseif ($coordinatorCount > 1) {
      $randomIndex = rand(0, $coordinatorCount - 1);
      $coordinator = $coordinators->itemAt($randomIndex);
    }
    else {
      $coordinator = $coordinators->first();
    }
    return $coordinator['contact_id_a'] ?? '';
  }

  $contacts = Contact::get(FALSE)
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $poc_email)
    ->execute()->first();

  return $contacts['id'] ?? '';
}

/**
 *
 */
function get_organization_id($organization_name) {
  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('organization_name', '=', $organization_name)
    ->execute()
    ->first();

  return $contacts['id'] ?? '';
}

/**
 *
 */
function get_poc_id($poc_phone, $poc_email) {
  // If both phone and email are empty, return an empty string.
  if (empty($poc_phone) && empty($poc_email)) {
    return '';
  }

  $contacts = Contact::get(FALSE)->addSelect('id');

  // Add conditions based on available inputs.
  if (!empty($poc_phone) && !empty($poc_email)) {
    $contacts->addWhere('phone_primary.phone', '=', $poc_phone)
      ->addWhere('email_primary.email', '=', $poc_email);
  }
  elseif (!empty($poc_phone)) {
    $contacts->addWhere('phone_primary.phone', '=', $poc_phone);
  }
  elseif (!empty($poc_email)) {
    $contacts->addWhere('email_primary.email', '=', $poc_email);
  }

  return $contacts->execute()->first()['id'] ?? '';
}

/**
 * Main function to process the CSV file.
 */
function main() {
  $csvFilePath = '/Users/nishantkumar/Downloads/institutionactivities.csv';

  if (!$csvFilePath) {
    exit("Error: CSV_FILE_PATH constant must be set.\n");
  }

  echo "CSV File: $csvFilePath\n";
  if (($handle = fopen($csvFilePath, 'r')) !== FALSE) {
    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if ($header === FALSE) {
      echo "Error: Unable to read header row from CSV file.\n";
      exit;
    }

    $i = 0;
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
      $i++;
      if (count($row) != count($header)) {
        echo "Error: Row $i has an incorrect number of columns.\n";
        continue;
      }

      $data = array_combine($header, $row);

      // Split engagement types into an array if multiple values exist.
      $engagementTypes = array_map('trim', explode(',', $data['How do you want to engage with Goonj?']));

      $values = [
        'title' => $data['Title'],
        'Type_of_institution' => $data['Type Of Institution'],
        'Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name' => $engagementTypes,
        'Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_' => $data['Where do you wish to organise the activity?'],
        'Institution_Goonj_Activities.City' => $data['City'],
        'Institution_Goonj_Activities.State' => get_state_id($data['State']),
        'Institution_Goonj_Activities.Start_Date' => $data['Start Date'],
        'Institution_Goonj_Activities.End_Date' => $data['End Date'],
        'Institution_Goonj_Activities.Coordinating_Urban_Poc' => assignCoordinatorByRelationshipType($data['Coordinating Urban Poc'], $data['Goonj Office'], $data['Type Of Institution']),
        'Institution_Goonj_Activities.Goonj_Office' => get_office_id($data['Goonj Office']),
        'Institution_Goonj_Activities_Outcome.Cash_Contribution' => $data['Cash Contribution'],
        'Institution_Goonj_Activities_Outcome.Product_Sale_Amount' => $data['Product Sale Amount'],
        'Institution_Goonj_Activities_Outcome.No_of_Attendees' => $data['No. of Attendees'],
        'Institution_Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers' => $data['Any unique efforts made by organizers'],
        'Institution_Goonj_Activities_Outcome.Did_you_face_any_challenges_' => $data['Do you faced any difficulty/challenges while organising or on activity day?'],
        'Institution_Goonj_Activities_Outcome.Remarks' => $data['Remarks'],
        'Collection_Camp_Core_Details.Status' => 'authorized',
        'Institution_Goonj_Activities_Outcome.No_of_Sessions' => $data['No. of Sessions'],
        'Institution_Goonj_Activities_Outcome.Rate_the_activity' => $data['Rate the camp'],
        'Institution_Goonj_Activities.Support_person_details' => $data['Support person details'],
        'Institution_Goonj_Activities.Institution_POC' => get_poc_id($data['Poc Phone'], $data['Poc Email']),
        'Institution_Goonj_Activities.Organization_name' => get_organization_id($data['Organization Name']),
      ];

      try {
        $i++;
        // \Civi::log()->info('values', ['values'=>$values, $i]);
        $results = EckEntity::create('Collection_Camp', TRUE)
          ->addValue('title', $values['title'])
          ->addValue('Institution_Goonj_Activities.You_wish_to_register_as:label', $values['Type_of_institution'])
          ->addValue('Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:label', $values['Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name'])
          ->addValue('Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', $values['Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'])
          ->addValue('Institution_Goonj_Activities.City', $values['Institution_Goonj_Activities.City'])
          ->addValue('Institution_Goonj_Activities.State', $values['Institution_Goonj_Activities.State'])
          ->addValue('Institution_Goonj_Activities.Start_Date', $values['Institution_Goonj_Activities.Start_Date'])
          ->addValue('Institution_Goonj_Activities.End_Date', $values['Institution_Goonj_Activities.End_Date'])
          ->addValue('Institution_Goonj_Activities.Coordinating_Urban_Poc', $values['Institution_Goonj_Activities.Coordinating_Urban_Poc'])
          ->addValue('Institution_Goonj_Activities.Goonj_Office', $values['Institution_Goonj_Activities.Goonj_Office'])
          ->addValue('Institution_Goonj_Activities_Outcome.Cash_Contribution', $values['Institution_Goonj_Activities_Outcome.Cash_Contribution'])
          ->addValue('Institution_Goonj_Activities_Outcome.Product_Sale_Amount', $values['Institution_Goonj_Activities_Outcome.Product_Sale_Amount'])
          ->addValue('Institution_Goonj_Activities_Outcome.No_of_Attendees', $values['Institution_Goonj_Activities_Outcome.No_of_Attendees'])
          ->addValue('Institution_Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers', $values['Institution_Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers'])
          ->addValue('Institution_Goonj_Activities_Outcome.Did_you_face_any_challenges_', $values['Institution_Goonj_Activities_Outcome.Did_you_face_any_challenges_'])
          ->addValue('Institution_Goonj_Activities_Outcome.Remarks', $values['Institution_Goonj_Activities_Outcome.Remarks'])
          ->addValue('Collection_Camp_Core_Details.Status', 'authorized')
          ->addValue('Institution_Goonj_Activities_Outcome.No_of_Sessions', $values['Institution_Goonj_Activities_Outcome.No_of_Sessions'])
          ->addValue('Institution_Goonj_Activities_Outcome.Rate_the_activity', $values['Institution_Goonj_Activities_Outcome.Rate_the_activity'])
          ->addValue('Logistics_Coordination.Support_person_details', $values['Institution_Goonj_Activities.Support_person_details'])
          ->addValue('Institution_Goonj_Activities.Institution_POC', $values['Institution_Goonj_Activities.Institution_POC'])
          ->addValue('Institution_Goonj_Activities.Organization_Name', $values['Institution_Goonj_Activities.Organization_name'])
          ->addValue('subtype:name', 'Institution_Goonj_Activities')
          ->execute();
        echo "Created entry for: " . $data['Title'] . "\n";
      }
      catch (Exception $e) {
        echo "Error creating entry for: " . $data['Title'] . " - " . $e->getMessage() . "\n";
      }
    }
    fclose($handle);
  }
  else {
    echo "Error: Unable to open CSV file.\n";
  }
}

main();
