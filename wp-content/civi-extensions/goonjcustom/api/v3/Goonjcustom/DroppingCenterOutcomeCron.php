<?php

/**
 * @file
 */

/**
 *
 */
function civicrm_api3_goonjcustom_dropping_center_outcome_cron($params) {
  $returnValues = [];

  $droppingCenters = civicrm_api4('Eck_Collection_Camp', 'get', [
    'select' => [
      'Donation_Box_Register_Tracking.Cash_Contribution',
      'Dropping_Centre.Tracking_Id',
      'Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_',
    ],
    'where' => [
        ['Dropping_Centre.Tracking_Id', 'IS NOT NULL'],
    ],
    'checkPermissions' => FALSE,
  ]);

  $cashContributionByTrackingId = [];
  $productSaleAmountByTrackingId = [];
  $footfallByTrackingId = [];
  $vehicleDispatchesByTrackingId = [];
  $bagsReceivedByTrackingId = [];

  foreach ($droppingCenters as $center) {
    $trackingId = $center['Dropping_Centre.Tracking_Id'];

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
  $collectionSourceVehicleDispatches = civicrm_api4('Eck_Collection_Source_Vehicle_Dispatch', 'get', [
    'select' => ['Camp_Vehicle_Dispatch.Collection_Camp'],
    'where' => [['Camp_Vehicle_Dispatch.Collection_Camp', 'IS NOT NULL']],
    'checkPermissions' => FALSE,
  ]);

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
  $bagData = civicrm_api4('Eck_Collection_Source_Vehicle_Dispatch', 'get', [
    'select' => [
      'Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office',
      'Camp_Vehicle_Dispatch.Collection_Camp',
    ],
    'where' => [
      ['Camp_Vehicle_Dispatch.Collection_Camp', 'IS NOT NULL'],
    ],
    'checkPermissions' => FALSE,
  ]);

  $bagsReceivedByTrackingId = [];

  foreach ($bagData as $record) {
    $dispatchId = $record['Camp_Vehicle_Dispatch.Collection_Camp'];
    // Default to 0 if empty.
    $bagsReceived = (int) ($record['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? 0);

    if (!isset($bagsReceivedByTrackingId[$dispatchId])) {
      $bagsReceivedByTrackingId[$dispatchId] = 0;
    }
    $bagsReceivedByTrackingId[$dispatchId] += $bagsReceived;
  }

  // Calculate footfall.
  $activities = civicrm_api4('Activity', 'get', [
    'select' => ['id', 'Material_Contribution.Dropping_Center'],
    'where' => [
      ['activity_type_id:name', '=', 'Material Contribution'],
      ['Material_Contribution.Dropping_Center', 'IS NOT NULL'],
    ],
    'checkPermissions' => FALSE,
  ]);

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
    civicrm_api4('Eck_Collection_Camp', 'update', [
      'values' => ['Dropping_Center_Outcome.Cash_Contribution' => max($cashContributionSum, 0)],
      'where' => [['id', '=', $trackingId]],
      'checkPermissions' => FALSE,
    ]);
  }

  // Update Product Sale Amount.
  foreach ($productSaleAmountByTrackingId as $trackingId => $productSaleSum) {
    civicrm_api4('Eck_Collection_Camp', 'update', [
      'values' => ['Dropping_Center_Outcome.Product_Sale_Amount_GBG_' => max($productSaleSum, 0)],
      'where' => [['id', '=', $trackingId]],
      'checkPermissions' => FALSE,
    ]);
  }

  // Update Footfall Count based on Material Contribution activities.
  foreach ($footfallByTrackingId as $trackingId => $footfallCount) {
    civicrm_api4('Eck_Collection_Camp', 'update', [
      'values' => ['Dropping_Center_Outcome.Footfall_at_the_center' => max($footfallCount, 0)],
      'where' => [['id', '=', $trackingId]],
      'checkPermissions' => FALSE,
    ]);
  }

  // Update Vehicle Count based on dispatches.
  foreach ($vehicleDispatchesByTrackingId as $trackingId => $vehicleCount) {
    civicrm_api4('Eck_Collection_Camp', 'update', [
      'values' => ['Dropping_Center_Outcome.Total_no_of_vehicle_material_collected' => max($vehicleCount, 0)],
      'where' => [['id', '=', $trackingId]],
      'checkPermissions' => FALSE,
    ]);
  }

  // Update Bags Received.
  foreach ($bagsReceivedByTrackingId as $trackingId => $bagsReceived) {
    civicrm_api4('Eck_Collection_Camp', 'update', [
      'values' => ['Dropping_Center_Outcome.Total_no_of_bags_received_from_center' => max($bagsReceived, 0)],
      'where' => [['id', '=', $trackingId]],
      'checkPermissions' => FALSE,
    ]);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'dropping_center_outcome_cron');
}
