<?php

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;
use Civi\Api4\Activity;

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
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - testing (6).csv';

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

    $initiatorId = get_initiator_id($data);

    function clean_float($value) {
    return isset($value) && $value !== ''
        ? (float) str_replace(',', '', $value)
        : null;
}

    $values = [
      'title' => $campCode,
      'Collection_Camp_Intent_Details.Location_Area_of_camp' => $data['Event Venue'] ?? '',
      'Collection_Camp_Intent_Details.Camp_Status:label' => $data['Camp Status'] ?? '',
      'created_date' => $data['Created Date (DD-MM-YYYY)'] ?? '',      
      'Collection_Camp_Intent_Details.City' => $data['City'] ?? '',
      'Collection_Camp_Intent_Details.State:name' => get_state_id($data['State'] ?? ''),
      'Collection_Camp_Intent_Details.Start_Date' => $data['Start Date (DD-MM-YYYY)'] ?? '',
      'Collection_Camp_Intent_Details.End_Date' => $data['End Date (DD-MM-YYYY)'] ?? '',
      'Logistics_Coordination.Support_person_details' => $data['Support person details'] ?? '',
      'Logistics_Coordination.Camp_to_be_attended_by' => get_attended_id($data['Attended By'] ?? ''),
      'Logistics_Coordination.Pickup_vehicle_info' => $data['Driver name and Pick-up info.'] ?? '',
      'Collection_Camp_Intent_Details.Goonj_Office' => get_office_id($data['Coordinating Goonj Office'] ?? ''),
      'Core_Contribution_Details.Total_online_monetary_contributions' => clean_float($data['Total Monetary Contributed'] ?? ''),
      'Camp_Outcome.Product_Sale_Amount' => clean_float($data['Total Product Sale'] ?? ''),
      'Camp_Outcome.Any_other_remarks_and_suggestions_for_Urban_Relation_Team' => $data['Any remarks for internal use'] ?? '',
      'Camp_Outcome.Any_unique_efforts_made_by_Volunteer' => $data['Any unique efforts made by organizers'] ?? '',
      'Camp_Outcome.Any_Difficulty_challenge_faced' => $data['Difficulty/challenge faced by organizers'] ?? '',
      'Collection_Camp_Core_Details.Status' => 'authorized',
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

  \Civi\Api4\EckEntity::update('Collection_Camp', FALSE)
  ->addWhere('id', '=', $campId)
  ->addValue('Collection_Camp_Intent_Details.Camp_Type', $data['Type of Camp'])
  ->addValue('Collection_Camp_Core_Details.Contact_Id', $initiatorId)
  ->addWhere('Camp_Outcome.Last_Reminder_Sent', '=', '2025-08-09')
  ->addWhere('Volunteer_Camp_Feedback.Last_Reminder_Sent', '=', '2025-08-09')
  ->addWhere('Camp_Outcome.Final_Reminder_Sent', '=', '2025-08-11')
  ->addWhere('Logistics_Coordination.Email_Sent', '=', TRUE)
  ->execute();

      echo "✅ Camp created with ID: $campId (Camp Code: $campCode)\n";

      \Civi\Api4\EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addValue('title', $campCode ?: 'Camp Vehicle Dispatch')
        ->addValue('subtype:label', 'Vehicle Dispatch')
        ->addValue('Camp_Vehicle_Dispatch.Collection_Camp', $campId)
        ->addValue('Camp_Vehicle_Dispatch.Remarks', $data['Remark'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $data['Total Weight of Material Collected (Kg)'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Vehicle_Category:label', $data['Vehicle Category of material collected'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Other_Vehicle_category', $data['other Vehicle Category of material collected'] ?? '')
        ->execute();

      echo "✅ Dispatch added for Camp Code: $campCode\n";

      // Parse activity date
      $rawDate = $data['Created Date  (DD-MM-YYYY)'] ?? '';
      $parsedDate = \DateTime::createFromFormat('d-m-Y', $rawDate);
      if (!$parsedDate) {
        Civi::log()->warning("⚠️ Invalid date format for $campCode — using current date. Raw: $rawDate");
        $parsedDate = new \DateTime(); // fallback to now
      }

      // Create activity
      Civi::log()->info("📝 Creating Activity for Camp Code $campCode — Initiator ID: $initiatorId");

      Activity::create(FALSE)
        ->addValue('subject', $campCode)
        ->addValue('activity_type_id:name', 'Organize Collection Camp')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', $parsedDate->format('Y-m-d'))
        ->addValue('source_contact_id', $initiatorId)
        ->addValue('target_contact_id', $initiatorId)
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
        ->execute();

      echo "✅ Activity created for Camp Code: $campCode\n";
      Civi::log()->info("✅ Activity created for Camp Code: $campCode");

    } catch (\Throwable $e) {
      echo "❌ Error for Camp Code $campCode: " . $e->getMessage() . "\n";
      Civi::log()->error("❌ Error for Camp Code $campCode: " . $e->getMessage());
    }
  }

  fclose($handle);
}

main();