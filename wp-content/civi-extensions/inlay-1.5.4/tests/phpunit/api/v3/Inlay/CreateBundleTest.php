<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Inlay\Type as InlayType;

/**
 * Inlay.CreateBundle API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_Inlay_CreateBundleTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;

  /**
   * Register our dummy inlay that we use for testing.
   */
  public function hook_civicrm_container($container) {
    $container->findDefinition('dispatcher')
      ->addMethodCall('addListener', ['hook_inlay_registerType', [Civi\Inlay\InlayDummy::class, 'register']]);
  }

  /**
   * Set up for headless tests.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
   */
  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * The setup() method is executed before the test is executed (optional).
   */
  public function setUp() :void {
    parent::setUp();
  }

  /**
   * The tearDown() method is executed after the test was executed (optional)
   * This can be used for cleanup.
   */
  public function tearDown() :void {
    parent::tearDown();
  }

  /**
   * Simple example test case.
   *
   * Note how the function name begins with the word "test".
   */
  public function testApiExample() {
    $GLOBALS['InlayDummyTestGlobal'] = 1;
    // Create an Inlay instance.
    $inlayID = \Civi\Api4\Inlay::create(FALSE)->setValues([
      'name' => 'My dummy inlay',
      'config' => '{"a": "hay"}',
      'class' => '\\Civi\\Inlay\\InlayDummy',
    ])->execute()->first()['id'];
    $inlay = InlayType::fromId($inlayID);
    $publicID = $inlay->getPublicID();
    $filename = Civi::paths()->getPath("[civicrm.files]/inlay-$publicID.js");

    $this->assertFileExists($filename);
    $data = file_get_contents($filename);
    $this->assertMatchesRegularExpression('/CiviCRMInlay\.app\.bundleInfo\(\d+, "' . $publicID . '"\)/', $data,
      "Expected that the main inlay script was included, but no sign of it.");

    $this->assertStringContainsString('// external script data', $data,
      "Expected that the bundle contain the inlay-specific script data but not found.");

    $this->assertMatchesRegularExpression("@i.inlays\[\"$publicID\"\] = {\"a\":\"hay\",\"x\":1@", $data,
      "Expected that the bundle contains the inlay config.");

    // Now make a change that would result in different data being exported on rebuild.
    $GLOBALS['InlayDummyTestGlobal'] = 2;
    // Call the thing we are designed to test:
    $result = civicrm_api3('Inlay', 'CreateBundle', []);

    $this->assertEquals($publicID, $result['values'][0]['public_id'], "Expected CreateBundle api result to output the public id");
    $this->assertFileExists($filename);
    $data = file_get_contents($filename);
    $this->assertMatchesRegularExpression('/CiviCRMInlay\.app\.bundleInfo\(\d+, "' . $publicID . '"\)/', $data,
      "Expected that the main inlay script was included, but no sign of it.");
    $this->assertStringContainsString('// external script data', $data,
      "Expected that the bundle contain the inlay-specific script data but not found.");
    // Check the conifg was updated 1»2
    $this->assertMatchesRegularExpression("@i.inlays\[\"$publicID\"\] = {\"a\":\"hay\",\"x\":2@", $data,
      "Expected that the bundle contains the inlay config.");
  }

  public function testCivisAbilityToReadAndWriteData() {
    $GLOBALS['InlayDummyTestGlobal'] = 1;

    // This just fails: $configIn = ['a' => '<p>hello &gt; </p>', 'number' => 123];
    $configIn = ['a' => '<p>hello </p>', 'number' => 123];
    // Create an Inlay instance.
    $inlayID = \Civi\Api4\Inlay::create(FALSE)->setValues([
      'name' => 'My dummy inlay',
      'config' => $configIn,
      'class' => '\\Civi\\Inlay\\InlayDummy',
    ])->execute()->first()['id'];

    /** @var \Civi\Inlay\InlayDummy $inlay */
    $inlay = InlayType::fromId($inlayID);
    $config = $inlay->getConfig();
    $this->assertEquals($configIn['number'], $config['number']);
    $this->assertEquals($configIn['a'], $config['a']);

  }

}
