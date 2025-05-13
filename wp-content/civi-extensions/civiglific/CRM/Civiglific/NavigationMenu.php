<?php

/**
 * @file
 */

use GuzzleHttp\Client;

/**
 *
 */
function glific_get_token() {
  $url = rtrim(CIVICRM_GLIFIC_API_BASE_URL, '/') . '/api/v1/session';

  $data = [
    'user' => [
      'phone' => CIVICRM_GLIFIC_PHONE,
      'password' => CIVICRM_GLIFIC_PASSWORD,
    ],
  ];
  error_log("Glific API URL: $url");
  error_log("data: " . json_encode($data));

  try {
    $client = new Client();
    $response = $client->post($url, [
      'headers' => ['Content-Type' => 'application/json'],
      'json' => $data,
      'http_errors' => FALSE,
    ]);

    $status = $response->getStatusCode();
    $body = (string) $response->getBody();
    $json = json_decode($body, TRUE);

    echo "HTTP Status: $status\n";
    echo "Raw Body:\n$body\n";

    if (isset($json['data']['access_token'])) {
      return $json['data']['access_token'];
    }
    elseif (isset($json['error'])) {
      return 'Error: ' . ($json['error']['message'] ?? 'Unknown error');
    }
    else {
      return 'Error: Access token not found';
    }
  }
  catch (\Exception $e) {
    return 'Error: ' . $e->getMessage();
  }
}
