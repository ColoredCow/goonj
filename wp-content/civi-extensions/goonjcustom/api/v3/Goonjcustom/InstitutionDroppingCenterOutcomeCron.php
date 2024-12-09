<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\EckEntity;

/**
 * Cron job to update Dropping Center outcomes.
 */
function civicrm_api3_goonjcustom_institution_dropping_center_outcome_cron($params) {
  $returnValues = [];

  $cashContributionById = [];
  $productSaleAmountById = [];

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
    ->addSelect('Camp_Vehicle_Dispatch.Institution_Collection_Camp')
    ->addWhere('Camp_Vehicle_Dispatch.Institution_Collection_Camp', 'IS NOT NULL')
    ->execute();

  $vehicleDispatchArray = $vehicleDispatches->getIterator()->getArrayCopy();

  $vehicleDispatchCount = array_count_values(array_column($vehicleDispatchArray, 'Camp_Vehicle_Dispatch.Institution_Collection_Camp'));

  $activities = Activity::get(TRUE)
    ->addSelect('Material_Contribution.Institution_Dropping_Center')
    ->addWhere('activity_type_id:name', '=', 'Material Contribution')
    ->addWhere('Material_Contribution.Institution_Dropping_Center', 'IS NOT NULL')
    ->execute();

  $activitiesArray = $activities->getIterator()->getArrayCopy();

  $totalFootfall = array_count_values(array_column($activitiesArray, 'Material_Contribution.Dropping_Center'));

  $bagData = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'Camp_Vehicle_Dispatch.Institution_Collection_Camp')
    ->addWhere('Camp_Vehicle_Dispatch.Institution_Collection_Camp', 'IS NOT NULL')
    ->execute();

  $bagsReceivedCount = [];
  foreach ($bagData as $record) {
    $dispatchId = $record['Camp_Vehicle_Dispatch.Institution_Collection_Camp'];
    $bagsReceivedCount[$dispatchId] = ($bagsReceivedCount[$dispatchId] ?? 0) + (int) ($record['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? 0);
  }

  /**
   *
   */
  function updateDroppingCenterMetric($metricName, $data) {
    foreach ($data as $id => $value) {
      EckEntity::update('Collection_Camp', TRUE)
        ->addValue("Dropping_Center_Outcome.$metricName", max($value, 0))
        ->addWhere('id', '=', $id)
        ->addWhere('subtype:name', '=', 'Institution_Dropping_Center')
        ->execute();
    }
  }

  updateDroppingCenterMetric('Cash_Contribution', $cashContributionById);
  updateDroppingCenterMetric('Product_Sale_Amount_GBG_', $productSaleAmountById);
  updateDroppingCenterMetric('Total_no_of_vehicle_material_collected', $vehicleDispatchCount);
  updateDroppingCenterMetric('Footfall_at_the_center', $totalFootfall);
  updateDroppingCenterMetric('Total_no_of_bags_received_from_center', $bagsReceivedCount);

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'institution_dropping_center_outcome_cron');
}
