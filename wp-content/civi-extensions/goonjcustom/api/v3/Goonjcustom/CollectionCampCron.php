<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\CollectionCampService;

/**
 * Goonjcustom.CollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_collection_camp_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.CollectionCampCron API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_collection_camp_cron($params) {
  $returnValues = [];

  $collectionCamps = civicrm_api4('Eck_Collection_Camp', 'get', [
      'select' => [
          'Donation_Box_Register_Tracking.Cash_Contribution',
          'Dropping_Centre.Donation_Tracking_Id',
          'Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_',
      ],
      'where' => [
          ['Dropping_Centre.Donation_Tracking_Id', 'IS NOT NULL'],
      ],
      'checkPermissions' => FALSE,
  ]);

  $cashContributionByTrackingId = [];
  $productSaleAmountByTrackingId = [];
  $footfallByTrackingId = [];
  $vehicleByTrackingId = [];
  $testing = [];

  $test = civicrm_api4('Eck_Collection_Source_Vehicle_Dispatch', 'get', [
    'select' => [
      'Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office',
    ],
    'where' => [
      ['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', '=',  $trackingId],
    ],
    'checkPermissions' => TRUE,
  ]);


  foreach ($collectionCamps as $camp) {
      $trackingId = $camp['Dropping_Centre.Donation_Tracking_Id'];

      if (!isset($cashContributionByTrackingId[$trackingId])) {
          $cashContributionByTrackingId[$trackingId] = 0;
      }
      if (!isset($productSaleAmountByTrackingId[$trackingId])) {
          $productSaleAmountByTrackingId[$trackingId] = 0;
      }

      if (isset($camp['Donation_Box_Register_Tracking.Cash_Contribution']) && $camp['Donation_Box_Register_Tracking.Cash_Contribution'] !== null) {
          $cashContributionByTrackingId[$trackingId] += (int) $camp['Donation_Box_Register_Tracking.Cash_Contribution'];
      }

      if (isset($camp['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_']) && $camp['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_'] !== null) {
          $productSaleAmountByTrackingId[$trackingId] += (float) $camp['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_'];
          error_log("productSaleAmountByTrackingId : $productSaleAmountByTrackingId, Result: " . print_r($productSaleAmountByTrackingId, TRUE));
      }

      $activities = civicrm_api4('Activity', 'get', [
          'select' => ['subject'],
          'where' => [
              ['activity_type_id:name', '=', 'Material Contribution'],
              ['Material_Contribution.Dropping_Center', '=', $trackingId],
          ],
          'checkPermissions' => TRUE,
      ]);
      $footfallByTrackingId[$trackingId] = count($activities);

      $collectionSourceVehicleDispatches = civicrm_api4('Eck_Collection_Source_Vehicle_Dispatch', 'get', [
          'select' => [
            'Camp_Vehicle_Dispatch.Collection_Camp',
          ],
          'where' => [
            ['Camp_Vehicle_Dispatch.Collection_Camp', '=',  $trackingId],
          ],
          'checkPermissions' => TRUE,
        ]);

      $vehicleByTrackingId[$trackingId] = count($collectionSourceVehicleDispatches);

      
  }

  $returnValues['cashContributionByTrackingId'] = $cashContributionByTrackingId;
  $returnValues['productSaleAmountByTrackingId'] = $productSaleAmountByTrackingId;
  $returnValues['footfallByTrackingId'] = $footfallByTrackingId;

  // Update Cash Contributions
  foreach ($cashContributionByTrackingId as $trackingId => $cashContributionSum) {
      $results = civicrm_api4('Eck_Collection_Camp', 'update', [
          'values' => [
              'Dropping_Center_Outcome.Cash_Contribution' => max($cashContributionSum, 0),
          ],
          'where' => [
              ['id', '=', $trackingId],
          ],
          'checkPermissions' => FALSE,
      ]);

  }

  // Update Product Sale Amount
  foreach ($productSaleAmountByTrackingId as $trackingId => $productSaleSum) {
      $results = civicrm_api4('Eck_Collection_Camp', 'update', [
          'values' => [
              'Dropping_Center_Outcome.Product_Sale_Amount_GBG_' => max($productSaleSum, 0),
          ],
          'where' => [
              ['id', '=', $trackingId],
          ],
          'checkPermissions' => FALSE,
      ]);

  }

  // Update Footfall Count based on Material Contribution activities
  foreach ($footfallByTrackingId as $trackingId => $footfallCount) {
      $results = civicrm_api4('Eck_Collection_Camp', 'update', [
          'values' => [
              'Dropping_Center_Outcome.Footfall_at_the_center' => max($footfallCount, 0),
          ],
          'where' => [
              ['id', '=', $trackingId],
          ],
          'checkPermissions' => TRUE,
      ]);

  }

  // Update Vehicle Count based on Material dispatch
  foreach ($vehicleByTrackingId as $trackingId => $vehicleCount) {
      $results = civicrm_api4('Eck_Collection_Camp', 'update', [
          'values' => [
              'Dropping_Center_Outcome.Total_no_of_vehicle_material_collected' => max($vehicleCount, 0),
          ],
          'where' => [
              ['id', '=', $trackingId],
          ],
          'checkPermissions' => TRUE,
      ]);

      // Update Vehicle Count based on Material dispatch
  foreach ($testing as $trackingId => $count) {
    $results = civicrm_api4('Eck_Collection_Camp', 'update', [
        'values' => [
            'Dropping_Center_Outcome.Total_no_of_bags_received_from_center' => max($count, 0),
        ],
        'where' => [
            ['id', '=', $trackingId],
        ],
        'checkPermissions' => TRUE,
    ]);

  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_cron');
}

}