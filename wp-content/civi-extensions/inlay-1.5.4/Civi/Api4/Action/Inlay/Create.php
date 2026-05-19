<?php
namespace Civi\Api4\Action\Inlay;

use Civi\Api4\Generic\DAOCreateAction;
use Civi\Api4\Generic\Result;

class Create extends DAOCreateAction {
  use WriteTrait;

  /**
   * @inheritDoc
   */
  public function _run(Result $result) {
    parent::_run($result);
    $this->updateBundle($result);
  }

  /**
   */
  protected function validateValues() {
    $records = [&$this->values];
    $this->ensurePublicID($records);
    // Return parent but I think it returns void anyway.
    return parent::validateValues();
  }
}
