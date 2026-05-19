<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Inlay\Type as InlayType;

/**
 * Job.Inlaycleanupassets API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_Job_InlaycleanupassetsTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;

  protected $filesToDelete = [];
  /**
   * Register our dummy inlay that we use for testing.
   */
  public function hook_civicrm_container($container) {
    $container->findDefinition('dispatcher')
              ->addMethodCall('addListener', ['hook_inlay_registerType', [Civi\Inlay\InlayDummy::class, 'register']])
            ;
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
    foreach ($this->filesToDelete as $filePath) {
      if (file_exists($filePath)) {
        unlink($filePath);
      }
    }

    $path = \Civi\Inlay\Asset::singleton()->getAssetsPath();
    foreach (new DirectoryIterator($path) as $file) {
      if (!$file->isDot() && $file->isDir() && preg_match('/^trash-\d+-\d+\d+/', $file->getFilename())) {
        // This is a trashdir.
        CRM_Utils_File::cleanDir($file->getPathname());
      }
    }

    parent::tearDown();
  }

  /**
   */
  public function testCleanupAssets() {
    // Create an Inlay instance.
    $row = \Civi\Api4\Inlay::create(FALSE)->setValues([
      'name' => 'My dummy inlay',
      'config' => '{}',
      'class' => '\\Civi\\Inlay\\InlayDummy', // ::class,
    ])->execute()->first();
    $row['config'] = json_decode($row['config'], TRUE);
    $inlay = InlayType::fromArray($row);
    $assetManager = \Civi\Inlay\Asset::singleton();

    // Create 2 versions of asset1, the first one will be superseeded
    $fileName = $assetManager->saveAssetFromData('dummy_asset', 'txt', 'asset1version1');
    $filePath = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName);
    $this->filesToDelete[] = $filePath;
    // this is the one we want to keep.
    $fileName2 = $assetManager->saveAssetFromData('dummy_asset', 'txt', 'asset1version2');
    $filePath2 = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName2);
    $this->filesToDelete[] = $filePath2;

    // Create an asset that dummy inlay does not think it wants.
    $fileName3 = $assetManager->saveAssetFromData('some_other_asset', 'txt', 'asset2version1');
    $filePath3 = \Civi::paths()->getPath('[civicrm.files]/inlay/' . $fileName3);
    $this->filesToDelete[] = $filePath3;

    // Create some random other file in the assets dir.
    $filePath4 = \Civi::paths()->getPath('[civicrm.files]/inlay/random');
    file_put_contents($filePath4, 'random');

    // Create some database cruft.
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_inlay_asset VALUES ('orphaned_asset', 'xxxxxx.txt')");

    $result = civicrm_api3('Job', 'inlaycleanupassets');
    //print "Old: $fileName\nCurrent: $fileName2\nOrphan: $fileName3\n" . implode("\n", $result['values']);

    $oldAssetDeleted = 0;
    $orphanedAssetDeleted = 0;
    $randomFileDeleted = 0;
    $dbCruftDeleted = 0;
    foreach ($result['values'] as $line) {
      $oldAssetDeleted |= preg_match("/^Removing $fileName/", $line);
      $randomFileDeleted |= preg_match("/^Removing random/", $line);
      $orphanedAssetDeleted |= preg_match("/^Removing $fileName3/", $line);
      $dbCruftDeleted |= preg_match("/^Removing stale assets from database: \"orphaned_asset\",\"some_other_asset\"/", $line);
    }
    $this->assertTrue((bool) $oldAssetDeleted, "Expected older versions of assets to have been deleted");
    $this->assertTrue((bool) $randomFileDeleted, "Expected other random file in assets dir to have been deleted");
    $this->assertTrue((bool) $orphanedAssetDeleted, "Expected assets that no inlay owns to have been deleted");
    $this->assertTrue((bool) $dbCruftDeleted, "Expected assets found in db that no inlay owns to have been deleted");

    // Ensure we still have $fileName2
    $f = $assetManager->getAssetPath('dummy_asset');
    $this->assertStringEndsWith($fileName2, $f);
    $this->assertFileExists($filePath2);

    // Ensure stuff was put in trash.
    $path = \Civi\Inlay\Asset::singleton()->getAssetsPath();
    $trashDir = '';
    foreach (new DirectoryIterator($path) as $file) {
      if (!$file->isDot() && $file->isDir() && preg_match('/^trash-\d+-\d+\d+/', $file->getFilename())) {
        // This is a trashdir.
        $trashDir = $file->getPathname();
      }
    }
    $this->assertFileExists("$trashDir/$fileName");
    $this->assertFileExists("$trashDir/$fileName3");
    $this->assertFileExists("$trashDir/random");
  }
}
