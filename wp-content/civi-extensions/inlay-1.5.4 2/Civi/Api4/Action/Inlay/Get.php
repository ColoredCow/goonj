<?php
namespace Civi\Api4\Action\Inlay;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;
use Civi\Inlay\Config as InlayConfig;

/**
 *
 * @method Get setValidateConfig(bool $validateConfig)
 */
 class Get extends DAOGetAction {

  /**
   * Whether to validate the config. For upgrader use only.
   *
   * @var bool
   *
   * @default true
   */
  protected $validateConfig = TRUE;

  /**
   * Override the _run method.
   */
  public function _run(Result $result) {
    parent::_run($result);
    $select = $this->getSelect();

    $weCanAddBundleUrl = ($select === [] || (in_array('public_id', $select)));
    $configDataLoaded = ($select === [] || (in_array('public_id', $select)));
    $weCanValidateConfig = ($select === [] || (count(array_intersect($select, ['class', 'public_id', 'config', 'name'])) === 4));
    $inlayConfig = InlayConfig::singleton();

    foreach ($result as &$item) {

      if ($weCanAddBundleUrl) {
        $item['scriptUrl'] = $inlayConfig->getBundleUrl($item['public_id']);
      }

      if ($this->validateConfig) {
        // Normally we only allow access to 'config' if we can validate it.
        if ($weCanValidateConfig) {
          // Allow the class to validate the input.
          $item['config'] = json_decode($item['config'] ?? '[]', TRUE);
          try {
            $inlay = \Civi\Inlay\Type::fromArray($item);
            $item['config'] = $inlay->config;
          }
          catch (\RuntimeException $e) {
            if ($e->getCode() === \Civi\Inlay\Type::INVALID_SUBCLASS) {
              $item['error'] = $e->getMessage();
            }
            else {
              throw $e;
            }
          }
        }
        else {
          // Do not allow access to the config unless we have enough to validate it with the class.
          unset($item['config']);
        }
      }
      else {
        // Special case: do NOT validate the config. This can be used by
        // Upgrader scripts that need to munge config; if they only ever
        // got valid config they might lose access to deprecated values.
        if ($configDataLoaded) {
          $item['config'] = json_decode($item['config'] ?? '[]', TRUE);
        }
      }
    }
  }

}
