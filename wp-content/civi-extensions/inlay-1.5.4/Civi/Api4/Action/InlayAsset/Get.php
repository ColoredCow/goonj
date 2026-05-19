<?php
namespace Civi\Api4\Action\InlayAsset;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Inlay\Asset;

class Get extends AbstractAction {

  /**
   * Unique identifier.
   *
   * @var string
   */
  protected string $identifier;

  public function _run(Result $result) {
    $manager = Asset::singleton();
    $result['url'] = $manager->getAssetUrl($this->identifier);
    $result['path'] = $manager->getAssetPath($this->identifier);
  }

}
