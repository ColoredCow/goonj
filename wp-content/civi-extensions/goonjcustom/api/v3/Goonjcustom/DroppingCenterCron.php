<?php

function civicrm_api3_goonjcustom_dropping_center_cron($params) {
    $returnValues = [];

    // Fetch Collection Camps
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

    // Arrays to store sums based on Donation_Tracking_Id
    $cashContributionByTrackingId = [];
    $productSaleAmountByTrackingId = [];
    $footfallByTrackingId = [];

    // Loop through the result set
    foreach ($collectionCamps as $camp) {
        $trackingId = $camp['Dropping_Centre.Donation_Tracking_Id'];

        // Initialize the sums for this tracking ID if not already set
        if (!isset($cashContributionByTrackingId[$trackingId])) {
            $cashContributionByTrackingId[$trackingId] = 0;
        }
        if (!isset($productSaleAmountByTrackingId[$trackingId])) {
            $productSaleAmountByTrackingId[$trackingId] = 0;
        }

        // Add to Cash_Contribution sum for this tracking ID
        if (isset($camp['Donation_Box_Register_Tracking.Cash_Contribution']) && $camp['Donation_Box_Register_Tracking.Cash_Contribution'] !== null) {
            $cashContributionByTrackingId[$trackingId] += (int) $camp['Donation_Box_Register_Tracking.Cash_Contribution'];
        }

        // Add to Product_Sale_Amount_GBG_ sum for this tracking ID
        if (isset($camp['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_']) && $camp['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_'] !== null) {
            $productSaleAmountByTrackingId[$trackingId] += (float) $camp['Donation_Box_Register_Tracking.Product_Sale_Amount_GBG_'];
        }

        // Get Material Contribution activities count based on tracking ID
        $activities = civicrm_api4('Activity', 'get', [
            'select' => ['subject'],
            'where' => [
                ['activity_type_id:name', '=', 'Material Contribution'],
                ['Material_Contribution.Collection_Camp', '=', $trackingId],
            ],
            'checkPermissions' => TRUE,
        ]);

        $footfallByTrackingId[$trackingId] = count($activities);
    }

    $returnValues['cashContributionByTrackingId'] = $cashContributionByTrackingId;
    $returnValues['productSaleAmountByTrackingId'] = $productSaleAmountByTrackingId;
    $returnValues['footfallByTrackingId'] = $footfallByTrackingId;

    // Update Cash Contributions
    foreach ($cashContributionByTrackingId as $trackingId => $cashContributionSum) {
        $results = civicrm_api4('Eck_Collection_Camp', 'update', [
            'values' => [
                'Dropping_Center_Outcome.Cash_Contribution' => $cashContributionSum,
            ],
            'where' => [
                ['id', '=', $trackingId],
            ],
            'checkPermissions' => FALSE,
        ]);

        error_log("Updated Cash Contribution for Tracking ID: $trackingId, Result: " . print_r($results, TRUE));
    }

    foreach ($productSaleAmountByTrackingId as $trackingId => $productSaleSum) {
        $results = civicrm_api4('Eck_Collection_Camp', 'update', [
            'values' => [
                'Dropping_Center_Outcome.Product_Sale_Amount_GBG_Total' => $productSaleSum,
            ],
            'where' => [
                ['Dropping_Centre.Donation_Tracking_Id', '=', $trackingId],
            ],
            'checkPermissions' => TRUE,
        ]);

        error_log("Updated Product Sale Amount for Tracking ID: $trackingId, Result: " . print_r($results, TRUE));
    }

    // Update Footfall Count based on Material Contribution activities
    foreach ($footfallByTrackingId as $trackingId => $footfallCount) {
        $results = civicrm_api4('Eck_Collection_Camp', 'update', [
            'values' => [
                'Dropping_Center_Outcome.Footfall_at_the_center' => $footfallCount,
            ],
            'where' => [
                ['id', '=', $trackingId],
            ],
            'checkPermissions' => TRUE,
        ]);

        // Log results for debugging
        error_log("Updated Footfall for Tracking ID: $trackingId, Result: " . print_r($results, TRUE));
    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'dropping_center_cron');
}