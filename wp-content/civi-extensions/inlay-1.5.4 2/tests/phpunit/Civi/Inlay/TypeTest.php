<?php
namespace Civi\Inlay;

use CRM_Inlay_ExtensionUtil as E;
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
class TypeTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

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
   * CSRF test.
   */
  public function testCSRF() {
    $i = new InlayDummy();
    $data = ['first_name' => 'Wilma'];
    $token = $i->getCSRFToken([
      'data' => $data,
      'validFrom' => 2,
      'validTo' => 3,
    ]);
    $this->assertNotEmpty($token);

    // Too early...
    try {
      $i->checkCSRFToken($token, $data);
      $this->fail("Expected token used too early to fail but it passed.");
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('TK2', substr($e->getMessage(), 0, 3),
        "Expected token used too early to fail with TK2 error.");
    }
    sleep(2);
    $error = '';
    try {
      $i->checkCSRFToken($token, $data);
    }
    catch (\InvalidArgumentException $e) {
      $error = $e->getMessage();
    }
    $this->assertEmpty($error);

    try {
      $i->checkCSRFToken('fake data', $data);
      $this->fail("Expected invalid token syntax to fail but it passed.");
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('TK1', substr($e->getMessage(), 0, 3),
        "Expected invalid token syntax to fail with TK1 error");
    }

    try {
      $tamperedToken = (($token[0] === '0') ? 'f' : '0')
        . substr($token,1);
      $i->checkCSRFToken($tamperedToken, $data);
      $this->fail("Expected tampered token to fail but it passed.");
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('TK4', substr($e->getMessage(), 0, 3));
    }

    sleep(2);
    try {
      $i->checkCSRFToken($token, $data);
      $this->fail("Expected expired token to fail but it passed.");
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('TK3', substr($e->getMessage(), 0, 3));
    }

  }

}

