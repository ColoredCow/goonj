<?php

return [
    [
        'name' => 'Razorpay (Recurring)',
        'entity' => 'payment_processor_type',
        'params' => [
            'version' => 3,
            'title' => 'Razorpay (Recurring)',
            'name' => 'RazorpayRecurring',
            'description' => 'Razorpay Payment Processor for Contributions with Recurring support',
            'user_name_label' => 'API Key',
            'password_label' => 'Secret Key',
            'class_name' => 'Civirazorpay_Payment_Razorpay',
            'billing_mode' => 4, // Redirect
            'payment_type' => 1, // 1 = Credit Card
            'is_recur' => TRUE,
        ],
    ],
];
