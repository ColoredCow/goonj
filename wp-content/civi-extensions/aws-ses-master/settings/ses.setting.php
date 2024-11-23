<?php
return [
  'aws_ses_secret' => [
    'name' => 'aws_ses_secret',
    'type' => 'String',
    'html_type' => 'text',
    'title' => 'SNS secret',
    'description' => "'secret' query parameter added to the AWS SNS endpoint",
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['aws-ses' => ['weight' => 10]],
  ],
];
