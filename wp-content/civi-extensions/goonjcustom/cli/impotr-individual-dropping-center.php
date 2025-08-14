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


function get_attended_id($email) {
  return Contact::get(FALSE)
    ->addSelect('id')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $email)
    ->execute()
    ->first()['id'] ?? '';
}

/**
 *
 */
function main() {
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - conatct test (3).csv';

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
    $campCode = trim($data['Dropping Center Code'] ?? '');

    if (empty($campCode)) {
      echo "⚠️  Skipping row $rowNum — Camp Code missing.\n";
      continue;
    }

    $values = [
      'title' => $campCode,
      'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_' => $data['Address'] ?? '',
      'Collection_Camp_Intent_Details.Camp_Status:label' => $data['Camp Status'] ?? '',
      'Collection_Camp_Core_Details.Contact_Id' => get_initiator_id($data),
      'Dropping_Centre.Current_Status' => 3,
      '"Dropping_Centre.District_City' => $data['City'] ?? '',
      'Collection_Camp_Core_Details.Status' => 'authorized',
      'Dropping_Centre.State:name' => get_state_id($data['State'] ?? ''),
      'Dropping_Centre.Timing' => $data['Timing'] ?? '',
      'Dropping_Centre.When_do_you_wish_to_open_center_Date_' => $data['When do you wish to open center (Date) (DD/MM/YY)'] ?? '',
      'Dropping_Centre.Goonj_Office' => get_office_id($data['Coordinating Goonj Office'] ?? ''),
      'Core_Contribution_Details.Total_online_monetary_contributions' => $data['Total Online Monetary Contribution'] ?? '',
      'Camp_Outcome.Product_Sale_Amount' => $data['Total Product Sale'] ?? '',
    ];

    try {
      $camp = EckEntity::create('Collection_Camp', FALSE)
        ->addValue('subtype:label', 'Dropping Center');

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

      // // ✅ Step 3: Create Dispatch entity
      // EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
      //   ->addValue('title', $campCode ?: 'Camp Vehicle Dispatch')
      //   ->addValue('subtype:label', 'Vehicle Dispatch')
      //   ->addValue('Camp_Vehicle_Dispatch.Dropping_Center', $campId)
      //   ->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $data['Total Weight of Material Collected (Kg)'] ?? '')
      //   ->addValue('Camp_Vehicle_Dispatch.Vehicle_Category:label', $data['Vehicle Category of material collected'] ?? '')
      //   ->addValue('Camp_Vehicle_Dispatch.Other_Vehicle_category', $data['other Vehicle Category of material collected'] ?? '')
      //   ->execute();

      // echo "✅ Dispatch added for Camp Code: $campCode\n";

      $results = EckEntity::create('Dropping_Center_Meta', FALSE)
        ->addValue('Status.Closing_Date', $data['Closing Date'] ?? '')
        ->addValue('Status.Status', 3)
        ->addValue('Status.Reason_for_Closing_center', $data['Reason For Closing Center'] ?? '')
        ->addValue('Dropping_Center_Meta.Dropping_Center', $campId)
        ->addValue('subtype:label', 'Status')
        ->addValue('title', 'Status')
        ->execute();

      $results = EckEntity::create('Dropping_Center_Meta', FALSE)
        ->addValue('subtype:label', 'Visit')
        ->addValue('Visit.Date_of_Visit', $data['Date of Visit'] ?? '')
        ->addValue('Visit.Feedback_Remark_of_visit', $data['Feedback/ Remark of visit'] ?? '')
        ->addValue('Visit.Visited_by', get_attended_id($data['Visited By'] ?? ''))
        ->addValue('Dropping_Center_Meta.Dropping_Center', $campId)
        ->addValue('title', 'Visit')
        ->execute();

      // ✅ Step 4: Create Activity
      // Activity::create(FALSE)
      //   ->addValue('subject', $campCode)
      //   ->addValue('activity_type_id:name', 'Organize Dropping Center')
      //   ->addValue('status_id:name', 'Authorized')
      //   ->addValue('activity_date_time', $data['Created Date  (DD-MM-YYYY)'])
      //   ->addValue('source_contact_id', get_initiator_id($data))
      //   ->addValue('target_contact_id', get_initiator_id($data))
      //   ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
      //   ->execute();

    }
    catch (\Throwable $e) {
      echo "❌ Error for Camp Code $campCode: " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();
