<?php

/**
 * @file
 * CLI Script to import individual goonj activities.
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 * Main function to process the CSV file.
 */
function main() {
  $csvFilePath = '/Users/nishantkumar/Downloads/importedactivities.csv';

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
      $values = [
        'title' => $data['Title'],
      ];

      try {
        $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
          ->addSelect('Goonj_Activities.How_do_you_want_to_engage_with_Goonj_', 'Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_', 'Goonj_Activities.Start_Date', 'Institution_Goonj_Activities.Start_Date', 'Goonj_Activities.End_Date', 'Institution_Goonj_Activities.End_Date', 'Collection_Camp_Core_Details.Contact_Id', 'Institution_Goonj_Activities.Institution_POC', 'subtype:name')
          ->addWhere('title', '=', $values['title'])
          ->execute()->first();

        $individualActivities = $collectionCamps['Goonj_Activities.How_do_you_want_to_engage_with_Goonj_'];
        $individualStartDate = $collectionCamps['Goonj_Activities.Start_Date'];
        $individualEndDate = $collectionCamps['Goonj_Activities.End_Date'];
        $individualInitiator = $collectionCamps['Collection_Camp_Core_Details.Contact_Id'];

        $institutionActivities = $collectionCamps['Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_'];
        $institutionStartDate = $collectionCamps['Institution_Goonj_Activities.Start_Date'];
        $institutionEndDate = $collectionCamps['Institution_Goonj_Activities.End_Date'];
        $institutionInitiator = $collectionCamps['Institution_Goonj_Activities.Institution_POC'];

        $activities = $individualActivities ? $individualActivities : $institutionActivities;
        $campId = $collectionCamps['id'];
        $startDate = $individualStartDate ? $individualStartDate : $institutionStartDate;
        $endDate = $individualEndDate ? $individualEndDate : $institutionEndDate;
        $initiator = $individualInitiator ? $individualInitiator : $institutionInitiator;

        if (empty($activities)) {
          continue;
        }
        foreach ($activities as $activityName) {
          \Civi::log()->info('activities', ['activit'=>$activityName]);
          $optionValues = OptionValue::get(FALSE)
          ->addSelect('name', 'label')
          ->addWhere('option_group_id:name', '=', 'Institution_Goonj_Activities_How_do_you_want_to_engage_with')
          ->addWhere('value', '=', $activityName)
          ->execute()->single();
          $results = EckEntity::create('Collection_Camp_Activity', FALSE)
            ->addValue('title', $optionValues['label'])
            ->addValue('subtype:name', $collectionCamps['subtype:name'])
            ->addValue('Collection_Camp_Activity.Collection_Camp_Id', $collectionCamps['id'])
            ->addValue('Collection_Camp_Activity.Start_Date', $startDate)
            ->addValue('Collection_Camp_Activity.End_Date', $endDate)
            ->addValue('Collection_Camp_Activity.Organizing_Person', $initiator)
            ->addValue('Collection_Camp_Activity.Activity_Status', 'completed')
            ->execute();
        }
      }
      catch (Exception $e) {
        echo "Error creating entry for: " . $data['title'] . " - " . $e->getMessage() . "\n";
      }
    }
    fclose($handle);
  }
  else {
    echo "Error: Unable to open CSV file.\n";
  }
}

main();
