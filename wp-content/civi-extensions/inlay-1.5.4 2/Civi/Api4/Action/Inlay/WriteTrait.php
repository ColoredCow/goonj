<?php
namespace Civi\Api4\Action\Inlay;

use Civi\Api4\Generic\Result;

trait WriteTrait {

  /**
   * Whether to rebuild the bundle on save. Normally you want this,
   * but in upgrader scripts it can cause trouble.
   *
   * @var bool
   *
   * @default true
   */
  protected $updateBundle = TRUE;

  /**
   * Override the formatWriteValues. This is api input data only.
   *
   */
  protected function formatWriteValues(&$record, $entityName = NULL, $actionName = NULL) {
    // Stringify the json.
    if (is_array($record['config'])) {
      $record['config'] = json_encode($record['config']);
    }
    parent::formatWriteValues($record);
  }

  /**
   *
   */
  protected function ensurePublicID(&$records) {
    foreach ($records as &$record) {
      // Check we have a unique id
      if (empty($record['public_id']) || $record['public_id'] === 'new') {
        if (empty($record['id'])) {
          // Only do this if we're saving a new record.
          $record['public_id'] = substr(sha1(uniqid()), 0, 12);
        }
      }
      unset($record);
    }
  }

  /**
   * Rebuild the javascript bundle for each result.
   *
   * @param \Civi\Api4\Generic\Result $result
   */
  public function updateBundle(Result $result) {
    if ($this->updateBundle) {
      $updatedInlayIDs = $result->column('id');
      if ($updatedInlayIDs) {
        \Civi\Api4\Inlay::createBundle(FALSE)
          ->setCheckPermissions(FALSE)
          ->addWhere('id', 'IN', $updatedInlayIDs)
          ->execute();
      }
    }
  }

}
