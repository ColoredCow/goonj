<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\StateProvince;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 *
 */
function get_state_id($state_name) {
  $state = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first();
  return $state['id'] ?? '';
}

/**
 *
 */
function get_attended_id($email) {
  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $email)
    ->execute()
    ->first();

  return $contacts['id'] ?? '';
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
function get_initiator_id($data) {
  $firstName = trim($data['First Name'] ?? '');
  $email = trim($data['Email'] ?? '');
  $mobile = trim($data['Mobile'] ?? '');

  // 1️⃣ Try: First Name + Email
  if (!empty($firstName) && !empty($email)) {
    $contact = Contact::get(FALSE)
      ->addJoin('Email AS email', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      Civi::log()->info("Matched contact by First Name + Email: {$firstName} / {$email} (ID: {$contact['id']})");
      return $contact['id'];
    }
  }

  if (!empty($firstName) && !empty($mobile)) {
    $contact = Contact::get(FALSE)
      ->addJoin('Phone AS phone', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $mobile)
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      Civi::log()->info("Matched contact by First Name + Mobile: {$firstName} / {$mobile} (ID: {$contact['id']})");
      return $contact['id'];
    }
  }
}

/**
 *
 */
function main() {
  $csvFilePath = '/Users/shubhambelwal/Sites/goonj/wp-content/civi-extensions/goonjcustom/cli/testing data - Institution collection camp (3).csv';

  echo "CSV File: $csvFilePath\n";
  if (!file_exists($csvFilePath)) {
    exit("Error: File not found.\n");
  }

  if (($handle = fopen($csvFilePath, 'r')) === FALSE) {
    echo "Error: Unable to open CSV file.\n";
    return;
  }

  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) {
    echo "Error: Unable to read header row from CSV file.\n";
    fclose($handle);
    return;
  }

  $rowNum = 1;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "Error: Row $rowNum column mismatch.\n";
      continue;
    }

    $data = array_combine($header, $row);
    $campCode = trim($data['Camp Code'] ?? '');

    if (empty($campCode)) {
      echo "⚠️  Skipping row $rowNum — Camp Code missing.\n";
      continue;
    }

    $values = [
      'title' => $campCode,
      'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_' => $data['Venue'] ?? '',
      'Collection_Camp_Intent_Details.Camp_Status:label' => $data['Camp Status'] ?? '',
      'created_date' => $data['Created Date  (DD-MM-YYYY)'] ?? '',
      'Collection_Camp_Core_Details.Contact_Id' => get_initiator_id($data),
      '"Dropping_Centre.District_City' => $data['City'] ?? '',
      'Collection_Camp_Core_Details.Status' => 'authorized',
      'Collection_Camp_Intent_Details.Camp_Type:name' => $data['Type of Camp'] ?? '',
      'Dropping_Centre.State:name' => get_state_id($data['State'] ?? ''),
      'Dropping_Centre.When_do_you_wish_to_open_center_Date_' => $data['Start Date (DD-MM-YYYY)'] ?? '',
      'Logistics_Coordination.Support_person_details' => $data['Support person details'] ?? '',
      'Logistics_Coordination.Camp_to_be_attended_by' => get_attended_id($data['Attended By'] ?? ''),
      'Dropping_Centre.Goonj_Office' => get_office_id($data['Coordinating Goonj Office'] ?? ''),
      'Core_Contribution_Details.Total_online_monetary_contributions' => $data['Total Monetary Contributed'] ?? '',
      'Camp_Outcome.Product_Sale_Amount' => $data['Total Product Sale'] ?? '',
    ];

    try {
      $camp = EckEntity::create('Collection_Camp', FALSE)
        ->addValue('subtype:label', 'Collection Camp');

      foreach ($values as $field => $value) {
        if (!empty($value)) {
          $camp->addValue($field, $value);
        }
      }

      $result = $camp->execute();
      $campId = $result[0]['id'] ?? NULL;
      if (!$campId) {
        echo "❌ Failed to get camp ID for Camp Code: $campCode\n";
        continue;
      }

      echo "✅ Camp created with ID: $campId (Camp Code: $campCode)\n";

      // ✅ Step 3: Create Dispatch entity
      EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addValue('title', $campCode ?: 'Camp Vehicle Dispatch')
        ->addValue('subtype:label', 'Vehicle Dispatch')
        ->addValue('Camp_Vehicle_Dispatch.Dropping_Center', $campId)
        ->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $data['Total Weight of Material Collected (Kg)'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Vehicle_Category:label', $data['Vehicle Category of material collected'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Other_Vehicle_category', $data['other Vehicle Category of material collected'] ?? '')
        ->execute();

      echo "✅ Dispatch added for Camp Code: $campCode\n";

      $results = EckEntity::create('Dropping_Center_Meta', TRUE)
        ->addValue('Status.Closing_Date', $data['Closing date'] ?? '')
        ->addValue('Status.Status:name', $data['Status'] ?? '')
        ->addValue('Status.Reason_for_Closing_center', $data['Reason For Closing'] ?? '')
        ->addValue('Dropping_Center_Meta.Dropping_Center', $campCode)
        ->addValue('subtype:label', 'Status')
        ->addValue('title', 'Status')
        ->execute();

      $results = EckEntity::create('Dropping_Center_Meta', TRUE)
        ->addValue('subtype:label', 'Visit')
        ->addValue('Visit.Date_of_Visit', $data['Date of visit'] ?? '')
        ->addValue('Visit.Feedback_Remark_of_visit', $data['Visit Remark'] ?? '')
        ->addValue('Visit.Visited_by', $data['visited by'] ?? '')
        ->addValue('Dropping_Center_Meta.Dropping_Center', $campCode)
        ->addValue('title', 'Visit')
        ->execute();

      // ✅ Step 4: Create Activity
      Activity::create(FALSE)
        ->addValue('subject', $campCode)
        ->addValue('activity_type_id:name', 'Organize Dropping Center')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', $data['Created Date  (DD-MM-YYYY)'])
        ->addValue('source_contact_id', get_initiator_id($data))
        ->addValue('target_contact_id', get_initiator_id($data))
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
        ->execute();

    }
    catch (\Throwable $e) {
      echo "❌ Error for Camp Code $campCode: " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();
