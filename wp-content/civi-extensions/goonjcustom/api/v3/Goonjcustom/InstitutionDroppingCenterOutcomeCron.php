<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\Contribution;
use Civi\Api4\EckEntity;

/**
 * Cron job to update Dropping Center outcomes.
 */
function civicrm_api3_goonjcustom_institution_dropping_center_outcome_cron($params) {
  $returnValues = [];

  $cashContributionById = [];
  $productSaleAmountById = [];
  $onlineMonetaryContributionById = [];

  $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('title', 'id')
    ->addWhere('subtype:name', '=', 'Institution_Dropping_Center')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->execute();
  foreach ($collectionCamps as $camp) {
    $id = $camp['id'];
    $contributions = Contribution::get(TRUE)
      ->addSelect('total_amount')
      ->addWhere('Contribution_Details.Source', '=', $id)
      ->addWhere('contribution_status_id:label', '=', 'Completed')
      ->execute();

    $contributionsArray = $contributions->getIterator()->getArrayCopy();

    $totalAmount = 0;

    if (!empty($contributionsArray)) {
      $totalAmount = array_sum(array_column($contributionsArray, 'total_amount'));
    }

    $onlineMonetaryContributionById[$id] = $totalAmount;

  }

  $droppingCenterMetas = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Donation.Cash_Contribution', 'Donation.Product_Sale_Amount_GBG_', 'Dropping_Center_Meta.Institution_Dropping_Center')
    ->addClause('OR', ['Donation.Cash_Contribution', 'IS NOT EMPTY'], ['Donation.Product_Sale_Amount_GBG_', 'IS NOT EMPTY'])
    ->execute();

  foreach ($droppingCenterMetas as $center) {
    $id = $center['Dropping_Center_Meta.Institution_Dropping_Center'];
    $cashContributionById[$id] = ($cashContributionById[$id] ?? 0) + (float) ($center['Donation.Cash_Contribution'] ?? 0);
    $productSaleAmountById[$id] = ($productSaleAmountById[$id] ?? 0) + (float) ($center['Donation.Product_Sale_Amount_GBG_'] ?? 0);
  }

  $vehicleDispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Camp_Vehicle_Dispatch.Institution_Dropping_Center')
    ->addWhere('Camp_Vehicle_Dispatch.Institution_Dropping_Center', 'IS NOT NULL')
    ->execute();

  $vehicleDispatchArray = $vehicleDispatches->getIterator()->getArrayCopy();

  $vehicleDispatchCount = array_count_values(array_column($vehicleDispatchArray, 'Camp_Vehicle_Dispatch.Institution_Dropping_Center'));

  $activities = Activity::get(TRUE)
    ->addSelect('Material_Contribution.Institution_Dropping_Center')
    ->addWhere('activity_type_id:name', '=', 'Material Contribution')
    ->addWhere('Material_Contribution.Institution_Dropping_Center', 'IS NOT NULL')
    ->execute();

  $activitiesArray = $activities->getIterator()->getArrayCopy();

  $totalFootfall = array_count_values(array_column($activitiesArray, 'Material_Contribution.Institution_Dropping_Center'));

  $bagData = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'Camp_Vehicle_Dispatch.Institution_Dropping_Center')
    ->addWhere('Camp_Vehicle_Dispatch.Institution_Dropping_Center', 'IS NOT NULL')
    ->execute();

  $bagsReceivedCount = [];
  foreach ($bagData as $record) {
    $dispatchId = $record['Camp_Vehicle_Dispatch.Institution_Dropping_Center'];
    $bagsReceivedCount[$dispatchId] = ($bagsReceivedCount[$dispatchId] ?? 0) + (int) ($record['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? 0);
  }

  /**
   *
   */
  function updateInstitutionDroppingCenterMetric($metricName, $data) {
    foreach ($data as $id => $value) {
      EckEntity::update('Collection_Camp', TRUE)
        ->addValue("Dropping_Center_Outcome.$metricName", max($value, 0))
        ->addWhere('id', '=', $id)
        ->addWhere('subtype:name', '=', 'Institution_Dropping_Center')
        ->execute();
    }
  }

  updateInstitutionDroppingCenterMetric('Cash_Contribution', $cashContributionById);
  updateInstitutionDroppingCenterMetric('Product_Sale_Amount_GBG_', $productSaleAmountById);
  updateInstitutionDroppingCenterMetric('Total_no_of_vehicle_material_collected', $vehicleDispatchCount);
  updateInstitutionDroppingCenterMetric('Footfall_at_the_center', $totalFootfall);
  updateInstitutionDroppingCenterMetric('Total_no_of_bags_received_from_center', $bagsReceivedCount);
  updateInstitutionDroppingCenterMetric('Online_Monetary_Contribution', $onlineMonetaryContributionById);

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'institution_dropping_center_outcome_cron');
}
