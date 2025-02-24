<?php

/**
 * @file
 * CLI Script to import individual goonj activities.
 */

use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 * Main function to process the CSV file.
 */
function main() {
  $csvFilePath = '/Users/nishantkumar/Downloads/individualgoonjactivities.csv';

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
      ];

      try {
        $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
          ->addSelect('title', 'Goonj_Activities.Start_Date', 'Collection_Camp_Core_Details.Contact_Id')
          ->addWhere('title', '=', $values['title'])
          ->execute()->first();

        if (!empty($collectionCamps['Collection_Camp_Core_Details.Contact_Id'])) {
          $createActivityForOrganization = Activity::create(FALSE)
            ->addValue('subject', $collectionCamps['title'])
            ->addValue('activity_type_id:name', 'Organize Goonj Activities')
            ->addValue('status_id:name', 'Authorized')
            ->addValue('activity_date_time', $collectionCamps['Goonj_Activities.Start_Date'])
            ->addValue('source_contact_id', $collectionCamps['Collection_Camp_Core_Details.Contact_Id'])
            ->addValue('target_contact_id', $collectionCamps['Collection_Camp_Core_Details.Contact_Id'])
            ->addValue('Collection_Camp_Data.Collection_Camp_ID', $collectionCamps['id'])
            ->execute();
        }
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
