<?php
namespace Civi\Api4\Action\InlayAsset;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Inlay\Asset;

class Put extends AbstractAction {

  /**
   * Unique identifier.
   *
   * @var string
   */
  protected string $identifier;

  /**
   * A data: URI base64 encoded blob of file data.
   *
   * @var string
   */
  protected string $data;

  /**
   * Filename
   *
   * @var string
   */
  protected string $filename;

  public function _run(Result $result) {

    $m = [];
    if (!preg_match('/\.([^.]+)$/', $this->filename, $m)) {
      throw new \CRM_Core_Exception("Invalid/missing filename. Could not extract extension. " . json_encode($this->filename));
    }
    $ext = $m[1];
    if (preg_match('@^data:[^;]+;base64@', $this->data ?? '', $m)) {
      $binary = base64_decode(substr($this->data, strlen($m[0])));
    }
    if (empty($binary)) {
      throw new \CRM_Core_Exception("Invalid/missing data.");
    }
    $manager = Asset::singleton();
    $result['path'] = $manager->saveAssetFromData($this->identifier, $ext, $binary);
    $result['url'] = $manager->getAssetUrl($this->identifier);
  }

}
