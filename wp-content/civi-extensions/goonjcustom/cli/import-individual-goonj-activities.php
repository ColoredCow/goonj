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
function getFallbackCoordinator() {
  $fallbackOffice = getFallbackOffice();
  $fallbackCoordinators = Relationship::get(FALSE)
    ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
    ->addWhere('relationship_type_id:name', '=', 'Goonj Activities Coordinator of')
    ->addWhere('is_current', '=', TRUE)
    ->execute();

  $coordinatorCount = $fallbackCoordinators->count();

  $randomIndex = rand(0, $coordinatorCount - 1);
  $coordinator = $fallbackCoordinators->itemAt($randomIndex);

  return $coordinator;
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
 * Main function to process the CSV file.
 */
function main() {
  $csvFilePath = CSV_FILE_PATH;

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
        'Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name' => $engagementTypes,
        'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_' => $data['Where do you wish to organise the activity?'],
        'Goonj_Activities.City' => $data['City'],
        'Goonj_Activities.State' => get_state_id($data['State']),
        'Goonj_Activities.Start_Date' => $data['Start Date'],
        'Goonj_Activities.End_Date' => $data['End Date'],
        'Goonj_Activities.Name' => $data['Name'],
        'Goonj_Activities.Contact_Number' => $data['Phone'],
        'Goonj_Activities.Coordinating_Urban_Poc' => get_coordinating_poc_id($data['Coordinating Urban Poc'], $data['State']),
        'Goonj_Activities.Goonj_Office' => get_office_id($data['Goonj Office']),
        'Goonj_Activities_Outcome.Cash_Contribution' => $data['Cash Contribution'],
        'Goonj_Activities_Outcome.Product_Sale_Amount' => $data['Product Sale Amount'],
        'Goonj_Activities_Outcome.No_of_Attendees' => $data['No. of Attendees'],
        'Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers' => $data['Any unique efforts made by organizers'],
        'Goonj_Activities_Outcome.Did_you_face_any_challenges_' => $data['Do you faced any difficulty/challenges while organising or on activity day?'],
        'Goonj_Activities_Outcome.Remarks' => $data['Remarks'],
        'Collection_Camp_Core_Details.Status' => 'authorized',
        'Collection_Camp_Core_Details.Contact_Id' => get_initiator_id($data['Phone']),
        'Goonj_Activities_Outcome.No_of_Sessions' => $data['No. of Sessions'],
        'Goonj_Activities_Outcome.Rate_the_activity' => $data['Rate the camp'],
      ];

      try {
        $i++;

        $results = EckEntity::create('Collection_Camp', FALSE)
          ->addValue('title', $values['title'])
          ->addValue('Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:label', $values['Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name'])
          ->addValue('Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', $values['Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'])
          ->addValue('Goonj_Activities.City', $values['Goonj_Activities.City'])
          ->addValue('Goonj_Activities.State', $values['Goonj_Activities.State'])
          ->addValue('Goonj_Activities.Start_Date', $values['Goonj_Activities.Start_Date'])
          ->addValue('Goonj_Activities.End_Date', $values['Goonj_Activities.End_Date'])
          ->addValue('Goonj_Activities.Name', $values['Goonj_Activities.Name'])
          ->addValue('Goonj_Activities.Contact_Number', $values['Goonj_Activities.Contact_Number'])
          ->addValue('Goonj_Activities.Coordinating_Urban_Poc', $values['Goonj_Activities.Coordinating_Urban_Poc'])
          ->addValue('Goonj_Activities.Goonj_Office', $values['Goonj_Activities.Goonj_Office'])
          ->addValue('Goonj_Activities_Outcome.Cash_Contribution', $values['Goonj_Activities_Outcome.Cash_Contribution'])
          ->addValue('Goonj_Activities_Outcome.Product_Sale_Amount', $values['Goonj_Activities_Outcome.Product_Sale_Amount'])
          ->addValue('Goonj_Activities_Outcome.No_of_Attendees', $values['Goonj_Activities_Outcome.No_of_Attendees'])
          ->addValue('Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers', $values['Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers'])
          ->addValue('Goonj_Activities_Outcome.Did_you_face_any_challenges_', $values['Goonj_Activities_Outcome.Did_you_face_any_challenges_'])
          ->addValue('Goonj_Activities_Outcome.Remarks', $values['Goonj_Activities_Outcome.Remarks'])
          ->addValue('Collection_Camp_Core_Details.Status', $values['Collection_Camp_Core_Details.Status'])
          ->addValue('Collection_Camp_Core_Details.Contact_Id', $values['Collection_Camp_Core_Details.Contact_Id'])
          ->addValue('Goonj_Activities_Outcome.No_of_Sessions', $values['Goonj_Activities_Outcome.No_of_Sessions'])
          ->addValue('Goonj_Activities_Outcome.Rate_the_activity', $values['Goonj_Activities_Outcome.Rate_the_activity'])
          ->addValue('subtype', 12)
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
