<?php

namespace Civi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * HTTP client for the CashFree PAN verification API.
 * Responsible only for making the API call and returning a standardized result.
 * Contains no CiviCRM business logic.
 */
class PanApiClient {

  /**
   * Verify a PAN number via the CashFree API.
   *
   * Returns a standardized result array:
   *   - verified (bool): true if PAN is valid
   *   - registered_name (string|null): name on PAN card, if returned by API
   *   - message (string): human-readable result or error message
   *   - raw_response (array): full API response for audit logging
   */
  public static function verify(string $pan): array {
    $pan = strtoupper(trim($pan));

    try {
      $client = new Client();

      $response = $client->post(CASHFREE_PAN_API_BASE_URL, [
        'headers' => [
          'Content-Type'    => 'application/json',
          'x-client-id'     => CASHFREE_PAN_CLIENT_ID,
          'x-client-secret' => CASHFREE_PAN_CLIENT_SECRET,
        ],
        'json' => [
          'pan' => $pan,
        ],
        'timeout' => 10,
      ]);

      $body = json_decode((string) $response->getBody(), TRUE) ?? [];

      // API-level validation error (e.g. pan_length_short)
      if (!empty($body['type']) && $body['type'] === 'validation_error') {
        \Civi::log()->warning('PAN API validation error', [
          'pan'  => $pan,
          'body' => $body,
        ]);

        return [
          'verified'         => FALSE,
          'registered_name'  => NULL,
          'message'          => $body['message'] ?? 'Validation error',
          'raw_response'     => $body,
        ];
      }

      $verified = !empty($body['valid']) && $body['valid'] === TRUE;

      \Civi::log()->info('PAN API response', [
        'pan'      => $pan,
        'verified' => $verified,
        'body'     => $body,
      ]);

      return [
        'verified'         => $verified,
        'registered_name'  => $body['registered_name'] ?? NULL,
        'message'          => $body['message'] ?? '',
        'raw_response'     => $body,
      ];
    }
    catch (RequestException $e) {
      \Civi::log()->error('PAN API request failed', [
        'pan'   => $pan,
        'error' => $e->getMessage(),
      ]);

      return [
        'verified'         => FALSE,
        'api_error'        => TRUE,
        'registered_name'  => NULL,
        'message'          => 'API request failed: ' . $e->getMessage(),
        'raw_response'     => [],
      ];
    }
    catch (\Exception $e) {
      \Civi::log()->error('PAN API unexpected error', [
        'pan'   => $pan,
        'error' => $e->getMessage(),
      ]);

      return [
        'verified'         => FALSE,
        'api_error'        => TRUE,
        'registered_name'  => NULL,
        'message'          => 'Unexpected error: ' . $e->getMessage(),
        'raw_response'     => [],
      ];
    }
  }

}
