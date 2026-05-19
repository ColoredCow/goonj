<?php
namespace Civi\Inlay;

/**
 * Inlay Exception thrown as part of processing an Inlay API request.
 *
 * The status code is the HTTP status code number.
 *
 * The responseObject is the JSON response, and should therefore be suitable
 * for public consumption. Typically we would expect an error message to output
 * { error: "public safe message" }
 * But your application may need something different.
 *
 * The internalError is gets logged.
 */
class ApiException extends \Exception {
  public $statusCode;
  public $responseObject;
  public $internalError;

  public function __construct($statusCode, $responseObject = NULL, ?string $internalError = NULL) {
    $this->statusCode = $statusCode;
    $this->responseObject = $responseObject;
    $this->internalError = $internalError;
    // Provide public errors as the message.
    parent::__construct("$this->statusCode response: " . json_encode($this->responseObject));
  }

  /**
   * Get a string error
   */
  public function getInternalError() :string {
    return $this->internalError ?? json_encode($this->responseObject);
  }
}
