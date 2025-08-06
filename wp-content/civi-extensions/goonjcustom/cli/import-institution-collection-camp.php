<?php

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;
use Civi\Api4\Relationship;
use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

function get_office_id($office_name) {
  $contact = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();
  return $contact['id'] ?? '';
}

function get_state_id($state_name) {
  $state = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first();
  return $state['id'] ?? '';
}

function get_poc_id($phone, $email) {
  $query = Contact::get(FALSE)->addSelect('id');
  if (!empty($phone)) {
    $query->addWhere('phone_primary.phone', '=', $phone);
  }
  if (!empty($email)) {
    $query->addWhere('email_primary.email', '=', $email);
  }
  return $query->execute()->first()['id'] ?? '';
}

function get_organization_id($name) {
  return Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('organization_name', '=', $name)
    ->execute()
    ->first()['id'] ?? '';
}

function assignCoordinatorByRelationshipType($poc_email, $state_name, $type) {
  if (!empty($poc_email)) {
    return Contact::get(FALSE)
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('email.email', '=', $poc_email)
      ->execute()
      ->first()['id'] ?? '';
  }

  $relationshipTypeMap = [
    'Corporate' => 'Corporate Coordinator of',
    'School' => 'School Coordinator of',
    'College/University' => 'College Coordinator of',
    'Association' => 'Associations Coordinator of',
    'Other' => 'Default Coordinator of',
  ];

  $relationshipTypeName = $relationshipTypeMap[$type] ?? 'Other Entities Coordinator of';
  $stateOfficeId = get_office_id($state_name);

  $coordinators = Relationship::get(FALSE)
    ->addWhere('contact_id_b', '=', $stateOfficeId)
    ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
    ->addWhere('is_current', '=', TRUE)
    ->execute();

  if ($coordinators->count() === 0) {
    return '';
  }

  return $coordinators->itemAt(rand(0, $coordinators->count() - 1))['contact_id_a'] ?? '';
}

function main() {
  $csvFilePath = '/Users/nishantkumar/Downloads/institutionactivities.csv';
  echo "📄 CSV File: $csvFilePath\n";

  if (!file_exists($csvFilePath)) {
    exit("❌ File not found.\n");
  }

  if (($handle = fopen($csvFilePath, 'r')) === FALSE) {
    exit("❌ Unable to open CSV file.\n");
  }

  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) {
    exit("❌ Unable to read header row.\n");
  }

  $rowNum = 1;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "⚠️ Row $rowNum: column mismatch.\n";
      continue;
    }

    $data = array_combine($header, $row);
    $campCode = trim($data['Camp Code'] ?? '');

    if (empty($campCode)) {
      echo "⚠️ Row $rowNum: Camp Code missing.\n";
      continue;
    }

    $values = [
      'title' => $campCode,
      'Type_of_institution' => $data['Institution Type'],
      'created_date' => $data['Created Date (MM/DD/YY)'] ?? '',
      'Institution_Collection_Camp_Intent.District_City' => $data['City'],
      'Institution_Collection_Camp_Intent.State' => get_state_id($data['State']),
      'Collection_Camp_Core_Details.Status' => 'authorized',
      'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_' => $data['Start Date'],
      'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_' => $data['End Date'],
      'Institution_collection_camp_Review.Coordinating_POC' => assignCoordinatorByRelationshipType($data['Coordinating Urban Poc'], $data['Goonj Office'], $data['Institution Type']),
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
      'Institution_Collection_Camp_Intent.Institution_POC' => get_poc_id($data['Poc Phone'] ?? '', $data['Poc Email'] ?? ''),
      'Institution_Collection_Camp_Intent.Organization_Name' => get_organization_id($data['Organization Name'] ?? ''),
    ];

    try {
      // Step 1: Create Institution Collection Camp
      $camp = EckEntity::create('Collection_Camp', FALSE)
        ->addValue('subtype:label', 'Institution Collection Camp');

      foreach ($values as $field => $value) {
        if (!empty($value)) {
          $camp->addValue($field, $value);
        }
      }

      $campResult = $camp->execute();
      $campId = $campResult[0]['id'] ?? null;
      if (!$campId) {
        echo "❌ Failed to create camp for Camp Code: $campCode\n";
        continue;
      }

      echo "✅ Camp created with ID: $campId (Camp Code: $campCode)\n";

      // Step 2: Create Vehicle Dispatch
      EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addValue('title', $campCode)
        ->addValue('subtype:label', 'Vehicle Dispatch')
        ->addValue('Camp_Vehicle_Dispatch.Institution_Collection_Camp', $campId)
        ->addValue('Camp_Vehicle_Dispatch.Remarks', $data['Remarks'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $data['Total Weight of Material Collected (Kg)'] ?? '')
        ->addValue('Camp_Vehicle_Dispatch.Vehicle_Category:label', $data['Vehicle Category of material collected'] ?? '')
        ->execute();

      echo "✅ Dispatch added for Camp Code: $campCode\n";

      // Step 3: Create Activity
      Activity::create(FALSE)
        ->addValue('subject', $campCode)
        ->addValue('activity_type_id:name', 'Organize Institution Collection Camp')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', $data['Created Date  (DD-MM-YYYY)'])
        ->addValue('source_contact_id', get_initiator_id($data))
        ->addValue('target_contact_id', get_initiator_id($data))
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
        ->execute();

      echo "✅ Activity created for Camp Code: $campCode\n";

    } catch (\Throwable $e) {
      echo "❌ Error for Camp Code $campCode: " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();