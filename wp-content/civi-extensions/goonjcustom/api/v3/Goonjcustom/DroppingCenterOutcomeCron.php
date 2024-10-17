<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\EckEntity;

/**
 *
 */
function civicrm_api3_goonjcustom_dropping_center_outcome_cron($params) {
  $returnValues = [];

  $droppingCenters = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Donation_Box_Register_Tracking.Cash_Contribution', 'Dropping_Centre.Dropping_Center_Tracking_Id', 'Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_')
    ->addWhere('Dropping_Centre.Dropping_Center_Tracking_Id', 'IS NOT NULL')
    ->execute();

  $cashContributionByTrackingId = [];
  $productSaleAmountByTrackingId = [];
  $footfallByTrackingId = [];
  $vehicleDispatchesByTrackingId = [];
  $bagsReceivedByTrackingId = [];

  foreach ($droppingCenters as $center) {
    $trackingId = $center['Dropping_Centre.Dropping_Center_Tracking_Id'];

    if (!isset($cashContributionByTrackingId[$trackingId])) {
      $cashContributionByTrackingId[$trackingId] = 0;
    }
    if (!isset($productSaleAmountByTrackingId[$trackingId])) {
      $productSaleAmountByTrackingId[$trackingId] = 0;
    }

    // Calculate cash contibution.
    $cashContributionByTrackingId[$trackingId] += (float) ($center['Donation_Box_Register_Tracking.Cash_Contribution'] ?? 0);

    // Calculate product sale amounts.
    $productSaleAmountByTrackingId[$trackingId] += (float) ($center['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_'] ?? 0);

  }

  // Calculate vehicle dispatch count.
  $collectionSourceVehicleDispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Camp_Vehicle_Dispatch.Collection_Camp')
    ->addWhere('Camp_Vehicle_Dispatch.Collection_Camp', 'IS NOT NULL')
    ->execute();

  $vehicleDispatchCount = [];
  foreach ($collectionSourceVehicleDispatches as $dispatch) {
    $dispatchId = $dispatch['Camp_Vehicle_Dispatch.Collection_Camp'];

    if (!isset($vehicleDispatchCount[$dispatchId])) {
      $vehicleDispatchCount[$dispatchId] = 0;
    }
    $vehicleDispatchCount[$dispatchId] += 1;
  }

  foreach ($vehicleDispatchCount as $dispatchId => $count) {
    $vehicleDispatchesByTrackingId[$dispatchId] = $count;
  }

  // Calculate the number of bags received.
  $bagData = EckEntity::get('Collection_Source_Vehicle_Dispatch', TRUE)
    ->addSelect('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'Camp_Vehicle_Dispatch.Collection_Camp')
    ->addWhere('Camp_Vehicle_Dispatch.Collection_Camp', 'IS NOT NULL')
    ->execute();

  $bagsReceivedByTrackingId = [];

  foreach ($bagData as $record) {
    $dispatchId = $record['Camp_Vehicle_Dispatch.Collection_Camp'];
    $bagsReceived = (int) ($record['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? 0);

    if (!isset($bagsReceivedByTrackingId[$dispatchId])) {
      $bagsReceivedByTrackingId[$dispatchId] = 0;
    }
    $bagsReceivedByTrackingId[$dispatchId] += $bagsReceived;
  }

  // Calculate footfall.
  $activities = Activity::get(TRUE)
    ->addSelect('id', 'Material_Contribution.Dropping_Center')
    ->addWhere('activity_type_id:name', '=', 'Material Contribution')
    ->addWhere('Material_Contribution.Dropping_Center', 'IS NOT NULL')
    ->execute();

  $totalFootfall = [];

  foreach ($activities as $activity) {
    $droppingCenterId = $activity['Material_Contribution.Dropping_Center'];
    if (!empty($droppingCenterId)) {
      if (!isset($totalFootfall[$droppingCenterId])) {
        $totalFootfall[$droppingCenterId] = 0;
      }
      $totalFootfall[$droppingCenterId] += 1;
    }
  }

  foreach ($totalFootfall as $droppingCenterId => $footfallCount) {
    $footfallByTrackingId[$droppingCenterId] = $footfallCount;
  }

  // Update Cash Contributions.
  foreach ($cashContributionByTrackingId as $trackingId => $cashContributionSum) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Cash_Contribution', max($cashContributionSum, 0))
      ->addWhere('id', '=', $trackingId)
      ->execute();
  }

  // Update Product Sale Amount.
  foreach ($productSaleAmountByTrackingId as $trackingId => $productSaleSum) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Product_Sale_Amount_GBG_', max($productSaleSum, 0))
      ->addWhere('id', '=', $trackingId)
      ->execute();
  }

  // Update Footfall Count based on Material Contribution activities.
  foreach ($footfallByTrackingId as $trackingId => $footfallCount) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Footfall_at_the_center', max($footfallCount, 0))
      ->addWhere('id', '=', $trackingId)
      ->execute();
  }

  // Update Vehicle Count based on dispatches.
  foreach ($vehicleDispatchesByTrackingId as $trackingId => $vehicleCount) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Total_no_of_vehicle_material_collected', max($vehicleCount, 0))
      ->addWhere('id', '=', $trackingId)
      ->execute();
  }

  // Update Bags Received.
  foreach ($bagsReceivedByTrackingId as $trackingId => $bagsReceived) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addValue('Dropping_Center_Outcome.Total_no_of_bags_received_from_center', max($bagsReceived, 0))
      ->addWhere('id', '=', $trackingId)
      ->execute();
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'dropping_center_outcome_cron');
}
