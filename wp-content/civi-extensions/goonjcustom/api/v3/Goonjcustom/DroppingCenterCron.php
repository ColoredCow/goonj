<?php

function civicrm_api3_goonjcustom_dropping_center_cron($params) {
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
                ['Material_Contribution.Collection_Camp', '=', $trackingId],
            ],
            'checkPermissions' => TRUE,
        ]);
        $footfallByTrackingId[$trackingId] = count($activities);

        $collectionSourceVehicleDispatches = civicrm_api4('Eck_Collection_Source_Vehicle_Dispatch', 'get', [
            'select' => [
              'Camp_Vehicle_Dispatch.Collection_Camp_Intent_Id',
            ],
            'where' => [
              ['Camp_Vehicle_Dispatch.Collection_Camp_Intent_Id', '=',  $trackingId],
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

    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'dropping_center_cron');
}
