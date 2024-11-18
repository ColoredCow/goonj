<?php

use CRM_Civirazorpay_ExtensionUtil as E;


return [
    'razorpay_subscription_plans' => [
        'name' => 'razorpay_subscription_plans',
        'type' => 'String',
        'serialize' => CRM_Core_DAO::SERIALIZE_JSON,
        'default' => [],
        'title' => E::ts('Razorpay Subscription Plans'),
        'description' => E::ts('Stores subscription plans for Razorpay recurring contributions.'),
        'is_domain' => 1,
        'is_contact' => 0,
        'html_type' => 'textarea',
        'settings_pages' => [
            'razorpay_settings' => ['weight' => 10],
        ],
    ],
];
