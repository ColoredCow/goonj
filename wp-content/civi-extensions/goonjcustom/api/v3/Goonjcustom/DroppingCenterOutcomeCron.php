<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\EckEntity;

/**
 * Cron job to update Dropping Center outcomes.
 */
function civicrm_api3_goonjcustom_dropping_center_outcome_cron($params) {
  $returnValues = [];

  $cashContributionById = [];
  $productSaleAmountById = [];

  $droppingCenterMetas = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Donation.Cash_Contribution', 'Donation.Product_Sale_Amount_GBG_', 'Dropping_Center_Meta.Dropping_Center')
    ->addClause('OR', ['Donation.Cash_Contribution', 'IS NOT EMPTY'], ['Donation.Product_Sale_Amount_GBG_', 'IS NOT EMPTY'])
    ->execute();

  foreach ($droppingCenterMetas as $center) {
    $id = $center['Dropping_Center_Meta.Dropping_Center'];

    if (!isset($cashContributionById[$id])) {
      $cashContributionById[$id] = 0;
    }
    if (!isset($productSaleAmountById[$id])) {
      $productSaleAmountById[$id] = 0;
    }

    // Sum the Cash Contribution.
    $cashContributionById[$id] += (float) ($center['Donation.Cash_Contribution'] ?? 0);

    // Sum the Product Sale Amount.
    $productSaleAmountById[$id] += (float) ($center['Donation.Product_Sale_Amount_GBG_'] ?? 0);
  }

  $vehicleDispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Camp_Vehicle_Dispatch.Collection_Camp')
    ->addWhere('Camp_Vehicle_Dispatch.Collection_Camp', 'IS NOT NULL')
    ->execute();

  $vehicleDispatchCount = [];

  foreach ($vehicleDispatches as $dispatch) {
    $dispatchId = $dispatch['Camp_Vehicle_Dispatch.Collection_Camp'];

    if (!isset($vehicleDispatchCount[$dispatchId])) {
      $vehicleDispatchCount[$dispatchId] = 0;
    }

    $vehicleDispatchCount[$dispatchId] += 1;
  }

  $activities = Activity::get(TRUE)
    ->addSelect('id', 'Material_Contribution.Dropping_Center')
    ->addWhere('activity_type_id:name', '=', 'Material Contribution')
    ->addWhere('Material_Contribution.Dropping_Center', 'IS NOT NULL')
    ->execute();

  $totalFootfall = [];

  foreach ($activities as $activity) {
    $droppingCenterId = $activity['Material_Contribution.Dropping_Center'];

    if (!isset($totalFootfall[$droppingCenterId])) {
      $totalFootfall[$droppingCenterId] = 0;
    }

    $totalFootfall[$droppingCenterId] += 1;
  }

  $bagData = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'Camp_Vehicle_Dispatch.Collection_Camp')
    ->addWhere('Camp_Vehicle_Dispatch.Collection_Camp', 'IS NOT NULL')
    ->execute();

  $bagsReceivedCount = [];

  foreach ($bagData as $record) {
    $dispatchId = $record['Camp_Vehicle_Dispatch.Collection_Camp'];
    $bagsReceived = (int) ($record['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? 0);

    if (!isset($bagsReceivedCount[$dispatchId])) {
      $bagsReceivedCount[$dispatchId] = 0;
    }
    $bagsReceivedCount[$dispatchId] += $bagsReceived;
  }

  // Update Cash Contributions.
  foreach ($cashContributionById as $id => $cashContributionSum) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Cash_Contribution', max($cashContributionSum, 0))
      ->addWhere('id', '=', $id)
      ->execute();
  }

  // Update Product Sale Amount.
  foreach ($productSaleAmountById as $id => $productSaleSum) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Product_Sale_Amount_GBG_', max($productSaleSum, 0))
      ->addWhere('id', '=', $id)
      ->execute();
  }

  // Update Vehicle Count based on dispatches.
  foreach ($vehicleDispatchCount as $id => $vehicleCount) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Total_no_of_vehicle_material_collected', max($vehicleCount, 0))
      ->addWhere('id', '=', $id)
      ->execute();
  }

  // Update Footfall Count based on Material Contribution activities.
  foreach ($totalFootfall as $id => $footfallCount) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Footfall_at_the_center', max($footfallCount, 0))
      ->addWhere('id', '=', $id)
      ->execute();
  }

  // Update Bags Received.
  foreach ($bagsReceivedCount as $id => $bagsReceived) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Total_no_of_bags_received_from_center', max($bagsReceived, 0))
      ->addWhere('id', '=', $id)
      ->execute();
  }

  // Return success response.
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'dropping_center_outcome_cron');
}
