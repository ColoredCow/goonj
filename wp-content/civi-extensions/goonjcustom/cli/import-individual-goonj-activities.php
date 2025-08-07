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

function get_initiator_id($mobile) {
  return Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('phone_primary.phone', '=', $mobile)
    ->execute()
    ->first()['id'] ?? '';
}

function get_state_id($state_name) {
  return StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first()['id'] ?? '';
}

function get_coordinating_poc_id($email, $state = '') {
  if (empty($email)) {
    $stateId = get_state_id($state);
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateId)
      ->addWhere('relationship_type_id:name', '=', 'Goonj Activities Coordinator of')
      ->addWhere('is_active', '=', TRUE)
      ->execute();

    return $coordinators->first()['contact_id_a'] ?? '';
  }

  return Contact::get(FALSE)
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $email)
    ->execute()
    ->first()['id'] ?? '';
}

// === Main Execution ===

function main() {
  $csvFilePath = CSV_FILE_PATH;

  if (!file_exists($csvFilePath)) {
    exit("❌ Error: CSV file not found at $csvFilePath\n");
  }

  echo "📂 Reading: $csvFilePath\n";

  if (($handle = fopen($csvFilePath, 'r')) === FALSE) {
    exit("❌ Error: Cannot open CSV file.\n");
  }

  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) {
    fclose($handle);
    exit("❌ Error: Could not read CSV header.\n");
  }

  $rowNum = 0;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "⚠️  Row $rowNum skipped: Column count mismatch.\n";
      continue;
    }

    $data = array_combine($header, $row);
    $campTitle = trim($data['Title'] ?? '');
    if (!$campTitle) {
      echo "⚠️  Row $rowNum skipped: Title missing.\n";
      continue;
    }

    $initiatorId = get_initiator_id($data['Phone']);
    $engagementTypes = array_map('trim', explode(',', $data['How do you want to engage with Goonj?']));

    try {
      // Step 1: Create EckEntity
      $result = EckEntity::create('Collection_Camp', FALSE)
        ->addValue('title', $campTitle)
        ->addValue('subtype:name', 'Goonj_Activities')
        ->addValue('Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:label', $engagementTypes)
        ->addValue('Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', $data['Where do you wish to organise the activity?'] ?? '')
        ->addValue('Goonj_Activities.City', $data['City'] ?? '')
        ->addValue('Goonj_Activities.State', get_state_id($data['State'] ?? ''))
        ->addValue('Goonj_Activities.Start_Date', $data['Start Date'] ?? '')
        ->addValue('Goonj_Activities.End_Date', $data['End Date'] ?? '')
        ->addValue('Goonj_Activities.Name', $data['Name'] ?? '')
        ->addValue('Goonj_Activities.Contact_Number', $data['Phone'] ?? '')
        ->addValue('Goonj_Activities.Coordinating_Urban_Poc', get_coordinating_poc_id($data['Coordinating Urban Poc'] ?? '', $data['State'] ?? ''))
        ->addValue('Goonj_Activities.Goonj_Office', get_office_id($data['Goonj Office'] ?? ''))
        ->addValue('Goonj_Activities_Outcome.Cash_Contribution', $data['Cash Contribution'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Product_Sale_Amount', $data['Product Sale Amount'] ?? '')
        ->addValue('Goonj_Activities_Outcome.No_of_Attendees', $data['No. of Attendees'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers', $data['Any unique efforts made by organizers'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Did_you_face_any_challenges_', $data['Do you faced any difficulty/challenges while organising or on activity day?'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Remarks', $data['Remarks'] ?? '')
        ->addValue('Goonj_Activities_Outcome.No_of_Sessions', $data['No. of Sessions'] ?? '')
        ->addValue('Core_Contribution_Details.Total_online_monetary_contributions', $data['Total Monetary Contributed'] ?? '')
        ->addValue('Goonj_Activities_Outcome.Rate_the_activity', $data['Rate the camp'] ?? '')
        ->addValue('Collection_Camp_Core_Details.Status', 'authorized')
        ->addValue('Collection_Camp_Core_Details.Contact_Id', $initiatorId)
        ->execute();

      $campId = $result[0]['id'] ?? null;

      if ($campId) {
        echo "✅ Created camp ID $campId: $campTitle\n";

        // Step 2: Create linked Activity
        Activity::create(FALSE)
          ->addValue('subject', $campTitle)
           ->addValue('activity_type_id:name', 'Organize Goonj Activities')
          ->addValue('status_id:name', 'Authorized')
          ->addValue('activity_date_time', date('Y-m-d H:i:s'))
          ->addValue('source_contact_id', $initiatorId)
          ->addValue('target_contact_id', $initiatorId)
          ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
          ->execute();

        echo "📝 Activity linked to camp: $campTitle\n";
      } else {
        echo "❌ Failed to create camp for row $rowNum: $campTitle\n";
      }
    } catch (Throwable $e) {
      echo "❌ Error at row $rowNum: " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();