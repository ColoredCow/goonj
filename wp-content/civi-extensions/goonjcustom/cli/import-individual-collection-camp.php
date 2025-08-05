<?php

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

function get_state_id($state_name) {
  $state = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first();
  return $state['id'] ?? '';
}

function get_attended_id($email) {
  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $email)
    ->execute()
    ->first();

  return $contacts['id'] ?? '';
}

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


function get_initiator_id($data) {
  $firstName = trim($data['First Name'] ?? '');
  $email = trim($data['Email'] ?? '');
  $mobile = trim($data['Mobile'] ?? '');

  // 1️⃣ Try: First Name + Email
  if (!empty($firstName) && !empty($email)) {
    $contact = \Civi\Api4\Contact::get(FALSE)
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
    $contact = \Civi\Api4\Contact::get(FALSE)
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
      'Collection_Camp_Intent_Details.Location_Area_of_camp' => $data['Event Venue'] ?? '',
      'Collection_Camp_Intent_Details.Camp_Status:label' => $data['Camp Status'] ?? '',
      'created_date' => $data['Created Date  (DD-MM-YYYY)'] ?? '',
      'Collection_Camp_Core_Details.Contact_Id' => get_initiator_id($data),
      'Collection_Camp_Intent_Details.City' => $data['City'] ?? '',
      'Collection_Camp_Core_Details.Status' => 'authorized',
      'Collection_Camp_Intent_Details.Camp_Type:name' => $data['Type of Camp'] ?? '',
      'Collection_Camp_Intent_Details.State:name' => get_state_id($data['State'] ?? ''),
      'Collection_Camp_Intent_Details.Start_Date' => $data['Start Date (DD-MM-YYYY)'] ?? '',
      'Collection_Camp_Intent_Details.End_Date' => $data['End Date (DD-MM-YYYY)'] ?? '',
      'Logistics_Coordination.Support_person_details' => $data['Support person details'] ?? '',
      'Logistics_Coordination.Camp_to_be_attended_by' => get_attended_id($data['Attended By'] ?? ''),     
      'Collection_Camp_Intent_Details.Goonj_Office' => get_office_id($data['Coordinating Goonj Office'] ?? ''), 
      'Core_Contribution_Details.Total_online_monetary_contributions' => $data['Total Monetary Contributed'] ?? '',
      'Camp_Outcome.Product_Sale_Amount' => $data['Total Product Sale'] ?? '',
      'Camp_Outcome.Rate_the_camp' => $data['Rate the camp'] ?? '',
      'Camp_Outcome.Any_other_remarks_and_suggestions_for_Urban_Relation_Team' => $data['Any remarks for internal use'] ?? '',
      'Camp_Outcome.Any_unique_efforts_made_by_Volunteer' => $data['Any unique efforts made by organizers'] ?? '',
      'Camp_Outcome.Any_Difficulty_challenge_faced' => $data['Difficulty/challenge faced by organizers'] ?? '',
    ];

    try {
      $camp = \Civi\Api4\EckEntity::create('Collection_Camp', FALSE)
        ->addValue('subtype:label', 'Collection Camp');

      foreach ($values as $field => $value) {
        if (!empty($value)) {
          $camp->addValue($field, $value);
        }
      }

      $result = $camp->execute();
      $campId = $result[0]['id'] ?? null;
      if (!$campId) {
        echo "❌ Failed to get camp ID for Camp Code: $campCode\n";
        continue;
      }

      echo "✅ Camp created with ID: $campId (Camp Code: $campCode)\n";


      // ✅ Step 3: Create Dispatch entity
      $dispatch = \Civi\Api4\EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addValue('title', $campCode ?: 'Camp Vehicle Dispatch')
        ->addValue('subtype:label', 'Vehicle Dispatch')
        ->addValue('Camp_Vehicle_Dispatch.Collection_Camp', $campId)
        ->addValue('Camp_Vehicle_Dispatch.Remarks', $data['Remark'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $data['Total Weight of Material Collected (Kg)'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Vehicle_Category:label', $data['Vehicle Category of material collected'])
        ->addValue('Camp_Vehicle_Dispatch.Other_Vehicle_category', $data['other Vehicle Category of material collected'])
        ->execute();
      echo "✅ Dispatch added for Camp Code: $campCode\n";

    } catch (\Throwable $e) {
      echo "❌ Error for Camp Code $campCode: " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();