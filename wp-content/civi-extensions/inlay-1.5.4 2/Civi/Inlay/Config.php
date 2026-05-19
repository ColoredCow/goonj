<?php
namespace Civi\Inlay;

use Civi;

/**
 * Provides various utility functions which depend on Inlay's global configuration.
 */
class Config {

  /** @var Inlay */
  static protected $singleton;

  /** @var string */
  protected $assetsPath;
  /** @var array Holds the stored settings */
  protected $settings;

  /** @var string */
  protected $apiEndPoint;

  /** @var string */
  protected $assetBaseUrl;

  /**
   * Singleton
   */
  public static function singleton($reload = FALSE) :Config {
    if (!isset(static::$singleton) || $reload) {
      static::$singleton = new static;
    }
    return static::$singleton;
  }

  /**
   * Constructor
   */
  public function __construct() {
    $this->loadConfig();
  }

  /**
   * Load config from database
   */
  protected function loadConfig() :Config {
    // Load the settings
    $defaultSettings = [
      'polyfill' => FALSE,
      'publicBaseUrl' => '',
    ];
    $settings = json_decode(\Civi::settings()->get('inlay', '{}'), TRUE) ?: $defaultSettings;
    // Limit our settings to those keys defined in defaults, and apply defaults if any are missing.
    $settings = array_intersect_key($settings, $defaultSettings) + $defaultSettings;
    $this->settings = $settings;

    // Now calculate in 'apiEndPoint'
    $apiEndPoint = \CRM_Utils_System::url('civicrm/inlay-api', NULL, TRUE /*absolute*/, NULL, FALSE, TRUE);
    $this->apiEndPoint = $this->alterPublicUrl($apiEndPoint);

    // Calculate assetBaseUrl
    $urlTpl = Civi::paths()->getUrl("[civicrm.files]/", 'absolute');
    $this->assetBaseUrl = $this->alterPublicUrl($urlTpl);

    return $this;
  }

  /**
   */
  public function getSettings() :array {
    return $this->settings;
  }

  /**
   */
  public function getApiEndPoint() :string {
    return $this->apiEndPoint;
  }

  /**
   * Calculate the bundle URL for given bundle.
   */
  public function getBundleUrl(string $publicID) :string {
    return $this->assetBaseUrl . 'inlay-' . $publicID . '.js';
  }

  /**
   * Swap out Civi's URL for an overridden one.
   */
  public function alterPublicUrl(string $url) :string {
    if ($this->settings['publicBaseUrl']) {
      return preg_replace('@^https?://[^/]+(.*)$@', $this->settings['publicBaseUrl']. '$1', $url);
    }
    return $url;
  }
}
