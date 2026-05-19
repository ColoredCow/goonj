<?php
namespace Civi\Api4\Action\InlayAsset;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Inlay\Asset;

class Delete extends AbstractAction {

  /**
   * Unique identifier.
   *
   * @var string
   */
  protected string $identifier;

  public function _run(Result $result) {
    $manager = Asset::singleton();
    $result['deleted'] = $manager->deleteAsset($this->identifier);
  }

}
