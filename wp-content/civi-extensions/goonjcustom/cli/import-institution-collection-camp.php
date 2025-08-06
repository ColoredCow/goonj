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

      $values = [
        'title' => $data['Camp Code'],
        'Type_of_institution' => $data['Institution Type'],
        'created_date' => $data['Created Date (MM/DD/YY)'] ?? '',
        'Institution_Collection_Camp_Intent.District_City' => $data['City'],
        'Institution_Collection_Camp_Intent.State' => get_state_id($data['State']),
        'Collection_Camp_Core_Details.Status' => 'authorized',
        'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_' => $data['Start Date'],
        'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_' => $data['End Date'],
        'Institution_collection_camp_Review.Coordinating_POC' => assignCoordinatorByRelationshipType($data['Coordinating Urban Poc'], $data['Goonj Office'], $data['Type Of Institution']),
        'Institution_collection_camp_Review.Goonj_Office' => get_office_id($data['Goonj Office']),
        'Institution_Collection_Camp_Intent.Collection_Camp_Address' => $data['Event Venue'],
        'Institution_collection_camp_Review.Campaign' => $data['Campaign Name'] ?? '',
        'Institution_collection_camp_Review.Is_the_camp_IHC_PCC_' => $data['Type of Camp'] ?? '',
        'Core_Contribution_Details.Total_online_monetary_contributions' => $data['Total Monetary Contributed'] ?? '',
        'Camp_Outcome.Product_Sale_Amount' => $data['Total Product Sale'] ?? '',
        'Institution_Collection_Camp_Logistics.Camp_to_be_attended_by' => $data['Attended By'] ?? '',
        'Institution_Collection_Camp_Logistics.Support_person_details' => $data['Support person details'] ?? '',
        'Camp_Outcome.Any_unique_efforts_made_by_Volunteer' => $data['Any unique efforts made by organizers'] ?? '',
        'Camp_Outcome.Any_Difficulty_challenge_faced' => $data['Difficulty/challenge faced by organizers'] ?? '',
        'Collection_Camp_Core_Details.Status' => 'authorized',
        'eck_collection_source_vehicle_dispatch.Camp_Vehicle_Dispatch.Material_weight_In_KGs_' => $data['Total Weight of Material Collected (Kg)'] ?? '',
        'eck_collection_source_vehicle_dispatch.Camp_Vehicle_Dispatch.Vehicle_Category' => $data['Vehicle Category of material collected'] ?? '',
        'Camp_Vehicle_Dispatch.Remarks' => $data['Remarks'] ?? '',
        'Institution_Collection_Camp_Intent.Institution_POC' => get_poc_id($data['Poc Phone'], $data['Poc Email']),
        'Institution_Collection_Camp_Intent.Organization_Name' => get_organization_id($data['Organization Name']),
      ];

      try {
        $i++;
        $results = EckEntity::create('Collection_Camp', TRUE)
          ->addValue('title', $values['title'])
          ->addValue('created_date', $values['created_date'])
          ->addValue('Institution_Collection_Camp_Intent.District_City', $values['Institution_Collection_Camp_Intent.District_City'])
          ->addValue('Institution_Collection_Camp_Intent.State', $values['Institution_Collection_Camp_Intent.State'])
          ->addValue('Collection_Camp_Core_Details.Status', $values['Collection_Camp_Core_Details.Status'])
          ->addValue('Institution_Collection_Camp_Intent.District_City', $values['Institution_Collection_Camp_Intent.District_City'])
          ->addValue('Institution_Collection_Camp_Intent.State', $values['Institution_Collection_Camp_Intent.Statee'])
          ->addValue('Institution_Collection_Camp_Intent.Collections_will_start_on_Date_', $values['Institution_Collection_Camp_Intent.Collections_will_start_on_Date_'])
          ->addValue('Institution_Collection_Camp_Intent.Collections_will_end_on_Date_', $values['Institution_Collection_Camp_Intent.Collections_will_end_on_Date_'])
          ->addValue('Institution_collection_camp_Review.Coordinating_POC', $values['Institution_collection_camp_Review.Coordinating_POC'])
          ->addValue('Institution_collection_camp_Review.Goonj_Office', $values['Institution_collection_camp_Review.Goonj_Office'])
          ->addValue('Institution_collection_camp_Review.Campaign', $values['Institution_collection_camp_Review.Campaign'])
          ->addValue('Institution_collection_camp_Review.Is_the_camp_IHC_PCC_', $values['Institution_collection_camp_Review.Is_the_camp_IHC_PCC_'])
          ->addValue('Institution_collection_camp_Review.Campaign', $values['Institution_collection_camp_Review.Campaign'])
          ->addValue('Institution_collection_camp_Review.Is_the_camp_IHC_PCC_', $values['Institution_collection_camp_Review.Is_the_camp_IHC_PCC_'])
          ->addValue('Logistics_Coordination.Support_person_details', $values['Institution_Collection_Camp_Logistics.Support_person_details'])
          ->addValue('Institution_Collection_Camp_Intent.Institution_POC', $values['Institution_Collection_Camp_Intent.Institution_POC'])
          ->addValue('Institution_Collection_Camp_Intent.Organization_Name', $values['Institution_Collection_Camp_Intent.Organization_Name'])
          ->addValue('Camp_Outcome.Product_Sale_Amount', $values['Camp_Outcome.Product_Sale_Amount'])
          ->addValue('Camp_Vehicle_Dispatch.Remarks', $values['Camp_Vehicle_Dispatch.Remarks'])
          ->addValue('eck_collection_source_vehicle_dispatch.Camp_Vehicle_Dispatch.Vehicle_Category', $values['eck_collection_source_vehicle_dispatch.Camp_Vehicle_Dispatch.Vehicle_Category'])
          ->addValue('eck_collection_source_vehicle_dispatch.Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $values['eck_collection_source_vehicle_dispatch.Camp_Vehicle_Dispatch.Material_weight_In_KGs_'])
          ->addValue('subtype:name', 'Institution_Collection_Camp')
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
