<?php
namespace Civi\Inlay;

/**
 * Holds a request.
 */
class ApiRequest {

  /** @var Array body (decoded from JSON as an array) */
  protected $body;

  /** @var String request method (uppercase, GET, POST...) */
  protected $method;

  /** @var String  */
  protected $pubilcID;

  /** @var \Civi\Inlay\Type subclass loaded with the config. */
  protected $inlay;


  /**
   * Setter for request body.
   *
   * @param array as decoded from input JSON.
   */
  public function setBody(Array $body) {
    $this->body = $body;
    return $this;
  }
  /**
   * @return Array
   */
  public function getBody() {
    return $this->body;
  }

  public function setMethod($method) {
    $this->method = strtoupper($method);
    return $this;
  }
  /**
   *
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   *
   */
  public function getPublicID() {
    return $this->body['publicID'] ?? NULL;
  }

  public function setInlay(\Civi\Inlay\Type $inlay) {
    $this->inlay = $inlay;
    return $this;
  }
  /**
   *
   */
  public function getInlay() {
    return $this->inlay;
  }
  /**
   * Used for logging.
   */
  public function export() {
    return [
      'inlayID'   => $this->inlay->getID(),
      'inlayType' => $this->inlay->getTypeName(),
      'inlayName' => $this->inlay->getName(),
      'publicID'  => $this->inlay->getPublicID(),
      'method'    => $this->method,
      'body'      => $this->body,
    ];
  }
}

