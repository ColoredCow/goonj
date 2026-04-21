<?php

namespace Civi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * HTTP client for the SurePass PAN verification API.
 * Responsible only for making the API call and returning a standardized result.
 * Contains no CiviCRM business logic.
 */
class PanApiClient {

  /**
   * Verify a PAN number via the SurePass API.
   *
   * Returns a standardized result array:
   *   - verified (bool): true if PAN is valid and matched by SurePass
   *   - api_error (bool): true if the API call itself failed (auth, network, 5xx)
   *   - registered_name (string|null): full name on PAN card, if returned by API
   *   - message (string): human-readable result or error message
   *   - raw_response (array): full API response for audit logging
   */
  public static function verify(string $pan): array {
    $pan = strtoupper(trim($pan));

    try {
      $client = new Client();

      $response = $client->post(SUREPASS_PAN_API_BASE_URL, [
        'headers' => [
          'Content-Type'  => 'application/json',
          'Authorization' => 'Bearer ' . SUREPASS_PAN_API_TOKEN,
        ],
        'json' => [
          'id_number' => $pan,
        ],
        'timeout' => 10,
      ]);

      $body     = json_decode((string) $response->getBody(), TRUE) ?? [];
      $verified = !empty($body['success']) && $body['success'] === TRUE;

      \Civi::log()->info('PAN API response', [
        'pan'      => $pan,
        'verified' => $verified,
        'body'     => $body,
      ]);

      return [
        'verified'         => $verified,
        'registered_name'  => $body['data']['full_name'] ?? NULL,
        'message'          => $body['message'] ?? '',
        'raw_response'     => $body,
      ];
    }
    catch (RequestException $e) {
      // Inspect the response to distinguish a legitimate "invalid PAN" (422)
      // from an actual API error (auth/network/server).
      if ($e->hasResponse()) {
        $response   = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body       = json_decode((string) $response->getBody(), TRUE) ?? [];

        // 422 = PAN is valid format but not found / not verifiable — legit "not verified" response.
        if ($statusCode === 422) {
          \Civi::log()->info('PAN API returned invalid PAN', [
            'pan'  => $pan,
            'body' => $body,
          ]);

          return [
            'verified'         => FALSE,
            'registered_name'  => $body['data']['full_name'] ?? NULL,
            'message'          => $body['message'] ?? 'Invalid PAN',
            'raw_response'     => $body,
          ];
        }
      }

      // 401 / 403 / 500 / network timeout — treat as api_error and bypass.
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
