<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Inlay\Config;

/**
 * Inlay.CreateBundle API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class Inlay_ConfigTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;

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
   */
  public function testConfig() {
    $c = Config::singleton();
    $settings = $c->getSettings();
    $this->assertEquals([
      'polyfill' => FALSE,
      'publicBaseUrl' => '',
    ], $settings, "Failed to apply defaults");

    $filesRoot = Civi::paths()->getUrl("[civicrm.files]/", 'absolute');
    $this->assertEquals("{$filesRoot}inlay-xxx.js", $c->getBundleUrl('xxx'));

    // Persist settings with different publicBaseUrl
    $settings['publicBaseUrl'] = 'https://proxy.example.org';
    \Civi::settings()->set('inlay', json_encode($settings));

    $c = Config::singleton(TRUE);
    $settings = $c->getSettings();
    $this->assertEquals([
      'polyfill' => FALSE,
      'publicBaseUrl' => 'https://proxy.example.org',
    ], $settings, "Failed to load changed settings");

    // Check the bundle and api URLs are updated
    $this->assertStringStartsWith("https://proxy.example.org", $c->getBundleUrl('xxx'));
    $this->assertStringStartsWith("https://proxy.example.org", $c->getApiEndPoint());

  }

}

