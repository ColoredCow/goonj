<?php

namespace CRM\Civiglific;

use GuzzleHttp\Client;

/**
 *
 */
if (!class_exists('CRM\Civiglific\GlificHelper')) {

  /**
   *
   */
  class GlificHelper {

    /**
     * Get the Glific API access token using phone and password.
     *
     * @return string|false Access token or FALSE on failure.
     */
    public static function getToken() {
      $apiBaseUrl = \Civi::settings()->get('civiglific_api_base_url');
      $phone = \Civi::settings()->get('civiglific_phone');
      $password = \Civi::settings()->get('civiglific_password');

      $url = rtrim($apiBaseUrl, '/') . '/api/v1/session';

      $data = [
        'user' => [
          'phone' => $phone,
          'password' => $password,
        ],
      ];

      try {
        $client = new Client();
        $response = $client->post($url, [
          'headers' => ['Content-Type' => 'application/json'],
          'json' => $data,
          'http_errors' => FALSE,
        ]);

        $json = json_decode($response->getBody(), TRUE);

        if (!empty($json['data']['access_token'])) {
          return $json['data']['access_token'];
        }
        else {
          \Civi::log()->error('Glific token error:', [
            'error' => 'Invalid response from Glific API',
            'response' => json_encode($json),
          ]);
          return FALSE;
        }
      }
      catch (\Exception $e) {
        \Civi::log()->error('Glific token exception:', [
          'error' => 'Glific token exception',
          'exception' => $e->getMessage(),
        ]);
        return FALSE;
      }
    }

  }

}
