<?php
namespace Civi\Inlay;

// use CRM_Inlay_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Inlay\Type as InlayType;
use Civi\Api4\Inlay as Api4Inlay;

/**
 * Generic tests
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class AssetTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $filesToDelete = [];

  /**
   * Register our dummy inlay that we use for testing.
   */
  public function hook_civicrm_container($container) {
    $container->findDefinition('dispatcher')
              ->addMethodCall('addListener', ['hook_inlay_registerType', [Civi\Inlay\InlayDummy::class, 'register']])
            ;
  }
  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() :void {
    parent::setUp();
  }

  public function tearDown() :void {
    foreach ($this->filesToDelete as $filePath) {
      if (file_exists($filePath)) {
        unlink($filePath);
      }
    }
    parent::tearDown();
  }

  /**
   */
  public function testAssets() {
    // Create an Inlay instance.
    $row = Api4Inlay::create(FALSE)->setValues([
      'name' => 'My dummy inlay',
      'config' => '{}',
      'class' => InlayDummy::class,
    ])->execute()->first();
    $row['config'] = json_decode($row['config'], TRUE);
    $inlay = InlayType::fromArray($row);
    $asset = \Civi\Inlay\Asset::singleton();

    $data = 'this is a test';
    $fileName = $asset->saveAssetFromData('dummy_asset', 'txt', $data);
    $this->assertMatchesRegularExpression('/^dummy_asset-[a-zA-Z0-9]+\.txt$/', $fileName);

    // Check file exists and contains the right stuff.
    $filePath = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName);
    $this->assertFileExists($filePath);
    $this->filesToDelete[] = $filePath;
    $this->assertEquals($data, file_get_contents($filePath));

    // Provide a new file.
    $data2 = 'another test';
    $fileName2 = $asset->saveAssetFromData('dummy_asset', 'txt', $data);
    $this->assertMatchesRegularExpression('/^dummy_asset-[a-zA-Z0-9]+\.txt$/', $fileName);
    $this->assertNotEquals($fileName, $fileName2, "We expected a new filename to be created");

    // Check file exists and contains the right stuff.
    $filePath2 = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName2);
    $this->assertFileExists($filePath2);
    $this->filesToDelete[] = $filePath2;
    $this->assertEquals($data, file_get_contents($filePath2));

    // Fetch the asset by identifier, it should be the 2nd one we made.
    $this->assertEquals($filePath2, $asset->getAssetPath('dummy_asset'));

    // Create another version, this time from a file. We'll copy the first file and delete it.
    $fileName4 = $asset->saveAssetFromPath('dummy_asset', $filePath);
    $this->assertMatchesRegularExpression('/^dummy_asset-[a-zA-Z0-9]+\.txt$/', $fileName4);
    // Check file exists and contains the right stuff.
    $filePath4 = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName4);
    $this->assertFileExists($filePath4);
    $this->filesToDelete[] = $filePath4;
    $this->assertEquals('this is a test', file_get_contents($filePath4));
    // Make sure that the source file was deleted.
    $this->assertFileDoesNotExist($filePath);
    // Make sure the asset table was updated
    $this->assertEquals($filePath4, $asset->getAssetPath('dummy_asset'));

    // Create another version, from the 2nd file, don't delete the original.
    $fileName5 = $asset->saveAssetFromPath('dummy_asset', $filePath2, FALSE);
    // Check file exists and contains the right stuff.
    $filePath5 = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName5);
    $this->assertFileExists($filePath5);
    $this->filesToDelete[] = $filePath5;
    $this->assertEquals($data, file_get_contents($filePath5));
    // Make sure that the source file was not deleted.
    $this->assertFileExists($filePath2);
    // Make sure the asset table was updated
    $this->assertEquals($filePath5, $asset->getAssetPath('dummy_asset'));

  }
}

