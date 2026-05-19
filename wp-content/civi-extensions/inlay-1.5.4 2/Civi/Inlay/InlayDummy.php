<?php
namespace Civi\Inlay;

/**
 * Empty implementation for testing.
 */
class InlayDummy extends Type {
  public static $typeName = 'Dummy';
  public static $defaultConfig = [
    'a' => 'aye',
    'b' => 'bee',
    'number' => 123,
  ];

  public function getInitData() :array {
    return [
      'a' => $this->config['a'],
      'x' => $GLOBALS['InlayDummyTestGlobal'] ?? NULL,
    ];
  }

  public function processRequest(\Civi\Inlay\ApiRequest $request) :array {
  }

  public function getExternalScript() :string {
    return "// external script data\n";
  }

  public function getAssets() :array {
    return ['dummy_asset'];
  }

}
