<?php
namespace Civi\Api4\Action\Inlay;

use Civi\Api4\Generic\DAOUpdateAction;
use Civi\Api4\Generic\Result;

class Update extends DAOUpdateAction {
  use WriteTrait;

  /**
   * @inheritDoc
   */
  public function _run(Result $result) {
    parent::_run($result);
    $this->updateBundle($result);
  }

}

