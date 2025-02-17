<?php

/**
 * @file
 * CLI Script to import individual goonj activities.
 */

use Civi\Api4\EckEntity;
use Civi\Api4\Contact;
use Civi\Api4\StateProvince;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 *
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
 *
 */
function get_initiator_id($intiator_mobile_number) {

  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('phone_primary.phone', '=', $intiator_mobile_number)
    ->execute()
    ->first();

  if ($contacts) {
    $contact_id = $contacts['id'];
  }
  return $contact_id ?? '';
}

/**
 *
 */
function get_state_id($state_name) {
  $stateProvinces = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()->first();
  if ($stateProvinces) {
    $stateProvinces_id = $stateProvinces['id'];
  }
  return $stateProvinces_id ?? '';
}

/**
 *
 */
function get_coordinating_poc_id($poc_email) {
  $contacts = Contact::get(FALSE)
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $poc_email)
    ->execute()->first();

  $poc_id = '';
  if ($contacts) {
    $poc_id = $contacts['id'];
  }
  return $poc_id ?? '';
}

/**
 *
 */
function main() {
  // Fetch the CSV file path from the constants defined in wp-config.php.
  $csvFilePath = CSV_FILE_PATH;

  // Check if the required constant is set.
  if (!$csvFilePath) {
    exit("Error: CSV_FILE_PATH constant must be set.\n");
  }

  echo "CSV File: $csvFilePath\n";
  if (($handle = fopen($csvFilePath, 'r')) !== FALSE) {
    // Read the header row.
    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if ($header === FALSE) {
      echo "Error: Unable to read header row from CSV file.\n";
      exit;
    }

    $i = 0;
    // Process each row in the CSV file.
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
      $i++;
      if (count($row) != count($header)) {
        echo "Error: Row $i has an incorrect number of columns.\n";
        continue;
      }

      // Combine the header and row into an associative array.
      $data = array_combine($header, $row);
      // // Open the CSV file for reading.
      // if (($handle = fopen($csvFilePath, 'r')) !== FALSE) {
      //     // Read the header row.
      //     $header = fgetcsv($handle);
      //     $i = 0;
      //     // Process each row in the CSV file.
      //     while (($row = fgetcsv($handle)) !== FALSE) {
      //         // Combine the header and row into an associative array.
      //         $data = array_combine($header, $row);
      // Prepare the values for the EckEntity create query.
      $values = [
        'title' => $data['Title'],
        'Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name' => [$data['How do you want to engage with Goonj?']],
        'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_' => $data['Where do you wish to organise the activity?'],
        'Goonj_Activities.City' => $data['City'],
      // Assuming Postal Code is not in the CSV.
        'Goonj_Activities.Postal_Code' => '',
        'Collection_Camp_Intent_Details.Name' => $data['Name'],
        'Collection_Camp_Core_Details.Status' => 'authorized',
        'Collection_Camp_Intent_Details.Contact_Number' => $data['Phone'],
        'Goonj_Activities.Start_Date' => $data['Start Date'],
        'Goonj_Activities.End_Date' => $data['End Date'],
        'Goonj_Activities.Goonj_Office' => get_office_id($data['Goonj Office']),
        'Goonj_Activities.Coordinating_Urban_Poc' => get_coordinating_poc_id($data['Coordinating Urban Poc']),
            // 'Goonj_Activities_Outcome.Product_Sale_Amount' => $data['Product Sale Amount'],
        'Goonj_Activities.State' => get_state_id($data['State']),
      // Assuming Contact_Id is not in the CSV.
        'Collection_Camp_Core_Details.Contact_Id' => get_initiator_id($data['Phone']),
      ];

      // Uncomment below to create the EckEntity entry.
      try {
        $i++;
        \Civi::log()->info('values', ['values' => $values, $i]);
        $results = EckEntity::create('Collection_Camp', FALSE)
          ->addValue('title', $values['title'])
          ->addValue('Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name', $values['Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:name'])
          ->addValue('Goonj_Activities.City', $values['Goonj_Activities.City'])
          ->addValue('Goonj_Activities.Postal_Code', $values['Goonj_Activities.Postal_Code'])
          ->addValue('Collection_Camp_Intent_Details.Name', $values['Collection_Camp_Intent_Details.Name'])
          ->addValue('Collection_Camp_Core_Details.Status', $values['Collection_Camp_Core_Details.Status'])
          ->addValue('Collection_Camp_Intent_Details.Contact_Number', $values['Collection_Camp_Intent_Details.Contact_Number'])
          ->addValue('Goonj_Activities.Start_Date', $values['Goonj_Activities.Start_Date'])
          ->addValue('Goonj_Activities.End_Date', $values['Goonj_Activities.End_Date'])
          ->addValue('Goonj_Activities.Goonj_Office', $values['Goonj_Activities.Goonj_Office'])
          ->addValue('Goonj_Activities.State', $values['Goonj_Activities.State'])
          ->addValue('Goonj_Activities.Coordinating_Urban_Poc', $values['Goonj_Activities.Coordinating_Urban_Poc'])
                // ->addValue('Goonj_Activities_Outcome.Product_Sale_Amount', $values['Goonj_Activities_Outcome.Product_Sale_Amount'])
          ->addValue('Collection_Camp_Core_Details.Contact_Id', $values['Collection_Camp_Core_Details.Contact_Id'])
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
