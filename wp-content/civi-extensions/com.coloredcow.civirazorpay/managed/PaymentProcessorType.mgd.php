<?php

return [
    [
        'name' => 'Razorpay',
        'entity' => 'payment_processor_type',
        'params' => [
            'version' => 3,
            'title' => 'Razorpay',
            'name' => 'Razorpay',
            'description' => 'Razorpay Payment Processor for Contributions',
            'user_name_label' => 'API Key',
            'password_label' => 'Secret Key',
            'class_name' => 'Civirazorpay_Payment_Razorpay',
            'billing_mode' => 1, // 1 = Onsite
            'payment_type' => 1, // 1 = Credit Card
        ],
    ],
];
