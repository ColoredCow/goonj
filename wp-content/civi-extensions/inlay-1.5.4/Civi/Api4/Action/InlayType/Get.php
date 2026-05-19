<?php
namespace Civi\Api4\Action\InlayType;

use Civi\Api4\Generic\Result;
/**
 * InlayType.get action
 *
 * @package Civi\Api4
 */
class Get extends \Civi\Api4\Generic\AbstractAction {

  public function _run(Result $result) {
    $registeredInlays = [];
    $dummy = NULL;
    \CRM_Utils_Hook::singleton()->invoke(
      ['inlays'],
      $registeredInlays,
      $dummy, $dummy, $dummy, $dummy, $dummy,
      'inlay_registerType');

    $result->exchangeArray(array_values($registeredInlays));
    return $result;
  }
}
