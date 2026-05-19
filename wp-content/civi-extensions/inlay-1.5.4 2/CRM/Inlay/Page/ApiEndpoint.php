<?php
use CRM_Inlay_ExtensionUtil as E;
use \Civi\Inlay\ApiException;
use \Civi\Inlay\ApiRequest;
use \Civi\Inlay\Type as InlayType;

/**
 * Handles routes to civicrm/inlay-api
 */
class CRM_Inlay_Page_ApiEndpoint extends CRM_Core_Page {

  /**
   * Handle all Inlay API requests.
   */
  public function run() {
    try {
      $this->corsChecks($_SERVER['HTTP_ORIGIN'] ?? NULL);
      $r = $this->parseRequest();
      $response = $r->getInlay()->processRequest($r);
    }
    catch (ApiException $e) {
      $response = $e->responseObject;
      http_response_code($e->statusCode);

      // 5xx errors logged as errors, anything else logged as notice.
      $level = (substr($e->statusCode, 0, 1) === '5') ? 'error' : 'notice';
      $msg = $e->getInternalError();
      Civi::log()->$level("Inlay exception: $msg", ['exception' => $e, 'request' => empty($r) ? NULL : $r->export()]);
    }
    catch (\Exception $e) {
      $response = ['error' => 'Unknown server error'];
      http_response_code(500);
      $msg = 'Unexpected Exception handling inlay: ' . $e->getMessage();
      $vars = ['exception' => $e];
      if (isset($r)) {
        $vars['request'] = $r->export();
      }
      Civi::log()->error($msg, $vars);
    }

    // Output response.
    $response = ($response !== NULL) ? json_encode($response) : '{"error": "Unknown error (no response generated)"}';
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    echo $response;

    exit;
  }

  /**
   * Check the origin is allowed and issue CORS headers.
   *
   * @var NULL|String The scheme and domain of the origin, e.g. https://example.org
   */
  public function corsChecks($httpOrigin) {

    $isOptionsRequest = ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') === 'OPTIONS';

    if ($httpOrigin === NULL) {
      // The browser will not send a CORS request if it's the same site.
      if ($isOptionsRequest) {
        Civi::log()->notice("Inlay corsChecks: OPTIONS Request has no Origin. This should not happen so returning 405");
        throw new ApiException(405, ['error' => 'Invalid OPTIONS reqeust']);
      }
      else {
        return;
      }
    }

    $allowedOrigins = \Civi\Api4\OptionValue::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', 'inlay_cors_origins')
      ->addWhere('is_active', '=', TRUE)
      ->execute()->indexBy('value');

    if (!isset($allowedOrigins[(string) $httpOrigin])) {
      // Not allowed.
      Civi::log()->notice("Inlay corsChecks: disallowing origin: " . json_encode($httpOrigin) . " as it is not found in configured options.");
      throw new ApiException(405, ['error' => 'Sorry, the request is not allowed from this website; it may be misconfigured (CORS Origin mismatch).']);
    }

    // CORS headers.
    header('Access-Control-Allow-Origin: ' . $httpOrigin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // If an OPTIONS request, we're done here.
    if ($isOptionsRequest) {
      exit;
    }

  }

  /**
   * Extract request data, lookup the Inlay.
   *
   * @return \Civi\Inlay\ApiRequest
   */
  public function parseRequest() {
    $r = new ApiRequest();

    if ($r->getMethod() === 'POST') {
      // Normally, the body comes from the payload body of the HTTP request.
      // A valid request will always be an array.
      $inputDataParsed = json_decode(file_get_contents('php://input'), TRUE);
      if (!is_array($inputDataParsed)) {

      }
    }
    else {
      // ...But for GET requests, we look to find it in the inlayJSON query value.
      $inputData = $_GET['inlayJSON'] ?? '';
      if (!$inputData) {
        // Some requests don't need to send anything.
        $inputDataParsed = [];
      }
      else {
        $inputDataParsed = json_decode($inputData, TRUE);
      }
    }

    if (!is_array($inputDataParsed)) {
      throw new ApiException(400,
        ['error' => 'Invalid request. Code ID' . $r->getMethod()],
        "Input data not an [empty] array"
      );
    }

    $r->setMethod($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN')
      ->setBody(
        (
          ($r->getMethod() === 'POST')
          ? json_decode(file_get_contents('php://input'), TRUE)
          : json_decode($_GET['inlayJSON'] ?? 'null', TRUE)
        ) ?? []
      );

    if (!$r->getPublicID()) {
      throw new ApiException(400, ['error' => 'Missing Inlay ID' ]);
    }

    // Load the Inlay.
    try {
      $inlay = InlayType::fromPublicID($r->getPublicID());
      if ($inlay->getStatus() !== 'on') {
        throw new ApiException(400, ['error' => 'Service unavailable. Code: INO'], 'Inlay status is not "on" (is ' . $inlay->getStatus() . ')');
      }
      $r->setInlay($inlay);
    }
    catch (\InvalidArgumentException $e) {
      // Not found.
      throw new ApiException(400, ['error' => $e->getMessage()], 'Error looking up inlay');
    }
    catch (\RuntimeException $e) {
      // The only time this is thrown is if the class is invalid. Which is probably our fault, so use 500.
      throw new ApiException(500, ['error' => 'Sorry, a configuration problem has occurred. Code: IWT'], $e->getMessage());
    }

    return $r;
  }

}
