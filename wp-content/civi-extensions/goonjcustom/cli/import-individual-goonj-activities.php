<?php

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;
use Civi\Api4\Relationship;
use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

// === Utility Functions ===

function get_office_id($office_name) {
  return Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first()['id'] ?? '';
}

function get_state_id($state_name) {
  return StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first()['id'] ?? '';
}

function get_attended_id($email) {
  return Contact::get(FALSE)
    ->addSelect('id')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $email)
    ->execute()
    ->first()['id'] ?? '';
}

function get_initiator_id($data) {
  $firstName = trim($data['First Name'] ?? '');
  $email = trim($data['Email'] ?? '');
  $mobile = trim($data['Mobile'] ?? '');

  if (!empty($firstName) && !empty($email)) {
    $contact = Contact::get(FALSE)
      ->addSelect('id')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();
    if (!empty($contact['id'])) {
      return $contact['id'];
    }
  }

  if (!empty($firstName) && !empty($mobile)) {
    $contact = Contact::get(FALSE)
      ->addSelect('id')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $mobile)
      ->execute()
      ->first();
    if (!empty($contact['id'])) {
      return $contact['id'];
    }
  }

  return '';
}

// === Main Execution ===

function main() {
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - testing institution (5).csv'; // Replace with real path

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
      echo "❌ Row $rowNum column mismatch.\n";
      continue;
    }

    $data = array_combine($header, $row);
    $campCode = trim($data['Goonj Activity Code'] ?? '');
    if (empty($campCode)) {
      echo "⚠️  Skipping row $rowNum — Camp Code missing.\n";
      continue;
    }

    $initiatorId = get_initiator_id($data);


    try {
      $createResult = EckEntity::create('Collection_Camp', FALSE)
        ->addValue('title', $campCode)
        ->addValue('subtype:name', 'Goonj_Activities')
        ->addValue('Goonj_Activities.created_date', $data['Created Date (DD-MM-YYYY)'] ?? '')
        ->addValue('Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:label', $data['Goonj Activity Type'] ?? '')
        ->addValue('Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', $data['Venue'] ?? '')
        ->addValue('Goonj_Activities.City', $data['City'] ?? '')
        ->addValue('Goonj_Activities.State', get_state_id($data['State'] ?? ''))
        ->addValue('Goonj_Activities.Start_Date', $data['Start Date (DD-MM-YYYY)'] ?? '')
        ->addValue('Goonj_Activities.End_Date', $data['End Date (DD-MM-YYYY)'] ?? '')
        ->addValue('Logistics_Coordination.Support_person_details', $data['Support person details'] ?? '')
        ->addValue('Logistics_Coordination.Camp_to_be_attended_by', get_attended_id($data['Attended By'] ?? ''))
        ->addValue('Goonj_Activities.Goonj_Office', get_office_id($data['Coordinating Goonj Office'] ?? ''))
        ->addValue('Goonj_Activities_Outcome.Cash_Contribution', $data['Cash Contribution'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Product_Sale_Amount', $data['Total Product Sale'] ?? '')
        ->addValue('Goonj_Activities_Outcome.No_of_Attendees', $data['No. of Attendees'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers', $data['Any unique efforts made by organizers'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Did_you_face_any_challenges_', $data['Do you faced any difficulty/challenges while organising or on activity day?'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Remarks', $data['Any remarks for internal use'] ?? '')
        ->addValue('Goonj_Activities_Outcome.No_of_Sessions', $data['No. of Sessions'] ?? '')
        ->addValue('Core_Contribution_Details.Total_online_monetary_contributions', $data['Total Monetary Contributed'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Rate_the_activity', $data['Rate the activity'] ?? '')
        ->addValue('Collection_Camp_Core_Details.Status', 'authorized')
        ->execute();

      $campId = $createResult[0]['id'] ?? null;

    $results = EckEntity::create('Collection_Camp_Activity', FALSE)
            ->addValue('title',$data['Goonj Activity Type'] ?? '')
            ->addValue('subtype:name', 'Goonj_Activities')
            ->addValue('Collection_Camp_Activity.Collection_Camp_Id', $campId)
            ->addValue('Collection_Camp_Activity.Start_Date', $data['Start Date (DD-MM-YYYY)'] ?? '')
            ->addValue('Collection_Camp_Activity.End_Date', $data['End Date (DD-MM-YYYY)'] ?? '')
            ->addValue('Collection_Camp_Activity.Organizing_Person', $initiatorId)
            ->addValue('Collection_Camp_Activity.Activity_Status', 'completed')
            ->addValue('Collection_Camp_Activity.Attending_Goonj_PoC', get_attended_id($data['Attended By'] ?? ''))
            ->execute();

      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $campId)
        ->addValue('Collection_Camp_Core_Details.Contact_Id', $initiatorId)
        ->execute();

      echo "✅ Created camp ID $campId for $campCode\n";

      Activity::create(FALSE)
        ->addValue('subject', $campCode)
        ->addValue('activity_type_id:name', 'Organize Goonj Activities')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $initiatorId)
        ->addValue('target_contact_id', $initiatorId)
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
        ->execute();

      echo "📝 Activity linked for: $campCode\n";
    } catch (Throwable $e) {
      echo "❌ Error at row $rowNum ($campCode): " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();
