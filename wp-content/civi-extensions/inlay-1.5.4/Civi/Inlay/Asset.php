<?php
namespace Civi\Inlay;

use Civi;
use Civi\Inlay\Config as InlayConfig;

/**
 * Asset manager class
 */
class Asset {

  /**
   * @var Asset
   */
  static protected $singleton;

  /**
   * @var string
   */
  protected $assetsPath;

  public static function singleton() :Asset {
    if (!isset(static::$singleton)) {
      static::$singleton = new static();
    }
    return static::$singleton;
  }

  public function __construct() {
    $inlayDir = Civi::paths()->getPath('[civicrm.files]/inlay');
    \CRM_Utils_File::createDir($inlayDir);
    $this->assetsPath = $inlayDir;
  }

  /**
   * Copy a file to the assets dir, give it a new secret.
   *
   * @return string the new filename
   */
  public function saveAssetFromPath(string $identifier, $sourceFilePath, $removeOriginal = TRUE) :string {
    $this->assertValidIdentifier($identifier);

    if (!file_exists($sourceFilePath)) {
      throw new \RuntimeException("Source file not found: $sourceFilePath");
    }
    preg_match('/\.([^.]{2,12})$/', $sourceFilePath, $matches);
    $extension = $matches[1] ?? NULL;
    $suffix = $this->generateAssetSuffix($extension);

    $dest = "$this->assetsPath/$identifier-$suffix";
    // Copy the file.
    if (!copy($sourceFilePath, $dest)) {
      throw new \RuntimeException("Failed to copy file from $sourceFilePath to $dest");
    }
    if ($removeOriginal && !unlink($sourceFilePath)) {
      throw new \RuntimeException("Failed to remove source file $sourceFilePath after copying it to $dest");
    }

    $this->updateInlayAssetTable($identifier, $suffix);

    return "$identifier-$suffix";
  }

  /**
   * Copy a file to the assets dir, give it a new secret.
   *
   * @return string the new filename
   */
  public function saveAssetFromData(string $identifier, string $extension, string $data) :string {

    $this->assertValidIdentifier($identifier);
    $suffix = $this->generateAssetSuffix($extension);

    if (empty($data)) {
      throw new \RuntimeException("Empty data trying to save asset with identifier $identifier");
    }

    $dest = "$this->assetsPath/$identifier-$suffix";
    if (!file_put_contents($dest, $data)) {
      throw new \RuntimeException("Failed to write data to $dest");
    }

    $this->updateInlayAssetTable($identifier, $suffix);
    return "$identifier-$suffix";
  }

  /**
   * Delete an asset.
   *
   * @return bool
   *   TRUE if a file was deleted.
   */
  public function deleteAsset(string $identifier) :bool {
    $deleted = FALSE;
    $path = $this->getAssetPath($identifier);
    if (file_exists($path)) {
      unlink($path);
      $deleted = TRUE;
    }
    \CRM_Core_DAO::executeQuery("DELETE FROM civicrm_inlay_asset WHERE identifier = %1;", [1 => [$identifier, 'String']]);
    return $deleted;
  }

  /**
   * Return an absolute URL to the asset.
   *
   * If not found, NULL is returned and a notice logged.
   */
  public function getAssetUrl(string $identifier) :?string {
    $this->assertValidIdentifier($identifier);

    $suffix = \CRM_Core_DAO::singleValueQuery("SELECT suffix FROM civicrm_inlay_asset WHERE identifier = %1;", [1 => [$identifier, 'String']]);
    if (empty($suffix)) {
      // \Civi::log()->debug("Requested (getAssetUrl) Inlay asset identified by '$identifier' not found in the asset table.");
      return NULL;
    }

    $url = \Civi::paths()->getUrl("[civicrm.files]/inlay/$identifier-$suffix", 'absolute');
    InlayConfig::singleton()->alterPublicUrl($url);

    return $url;
  }

  /**
   * Return an absolute path to the asset.
   *
   * If not found, NULL is returned and a notice logged.
   */
  public function getAssetPath(string $identifier) :?string {
    $this->assertValidIdentifier($identifier);

    $suffix = \CRM_Core_DAO::singleValueQuery("SELECT suffix FROM civicrm_inlay_asset WHERE identifier = %1;", [1 => [$identifier, 'String']]);
    if (empty($suffix)) {
      // \Civi::log()->debug("Requested (getAssetPath) Inlay asset identified by '$identifier' not found in the asset table.");
      return NULL;
    }

    return "$this->assetsPath/$identifier-$suffix";
  }

  /**
   * Return the path to our assets dir, ensuring it exists first.
   */
  public function getAssetsPath() :string {
    return $this->assetsPath;
  }

  /**
   * Create a trash dir and return its path.
   */
  public function makeTrashDir():string {
    $trashDir = Civi::paths()->getPath('[civicrm.files]/inlay/trash-' . date('Y-m-d') . '-' . static::generateRandomString());
    return $trashDir;
  }

  /**
   * Generates random string.
   */
  public static function generateRandomString():string {
    // Generate a random string without special chars.
    $uid = '';
    do {
      $uid .= preg_replace('@[/+]@', '', base64_encode(random_bytes(18)));
    } while (strlen($uid) < 24);

    return substr($uid, 0, 24);
  }

  /**
   * The civicrm_inlay_asset table stores the most up-to-date asset for the given identifier.
   *
   * Files that start with this identifier and don't end in the latest suffix
   * will/should be removed by a nightly cron job.
   */
  protected function updateInlayAssetTable(string $identifier, string $suffix) {
    \CRM_Core_DAO::executeQuery("DELETE FROM civicrm_inlay_asset WHERE identifier = %1;", [1 => [$identifier, 'String']]);
    \CRM_Core_DAO::executeQuery("INSERT INTO civicrm_inlay_asset VALUES (%1, %2);", [
      1 => [$identifier, 'String'],
      2 => [$suffix, 'String'],
    ]);
  }

  protected function assertValidIdentifier(string $identifier) {
    if (!preg_match('/^[a-z0-9][a-z0-9_]{0,200}$/', $identifier)) {
      throw new \RuntimeException("Invalid asset identifier. Must be only lowercase, digit or underscore (but not at start). Got: $identifier");
    }
  }

  /**
   * Throw exception if (asset) extension is not allowed.
   */
  protected function generateAssetSuffix(string $extension) :string {
    if (in_array($extension, ['php', 'exe', 'sh', 'dll'])) {
      throw new \RuntimeException("Source file path has forbidden extension: $extension");
    }

    return static::generateRandomString() . ".$extension";
  }

}
