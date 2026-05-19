<?php
use CRM_Inlay_ExtensionUtil as E;

/**
 * Job.Inlaycleanupassets API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_Inlaycleanupassets_spec(&$spec) {
  $spec['dry_run'] = [
      'description' => 'Do not actually delete anything',
      'api.default' => FALSE,
    ];
  $spec['use_trash'] = [
      'description' => 'Move old files into subdir instead of deleting',
      'api.default' => TRUE,
    ];
}

/**
 * Job.Inlaycleanupassets API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws CRM_Core_Exception
 */
function civicrm_api3_job_Inlaycleanupassets($params) {
  $log = [];
  $assetManager = \Civi\Inlay\Asset::singleton();

  $currentAssets = [];
  $allInlays = \Civi\Api4\Inlay::get(FALSE)
    ->execute();
  foreach ($allInlays as $inlayRow) {
    $inlay = \Civi\Inlay\Type::fromArray($inlayRow);
    foreach ($inlay->getAssets() as $identifier) {
      $currentAssets[$identifier] = 1;
    }
  }

  // Got list of identifiers, turn this into a list of files.
  $dao = \CRM_Core_DAO::executeQuery('SELECT identifier, suffix FROM civicrm_inlay_asset');
  $assetsInDatabase = $dao->fetchMap('identifier', 'suffix');

  // For an asset to survive, it must be (a) in the database, and (b) current according to the inlay.

  if ($params['use_trash']) {
    $trashDir = $assetManager->makeTrashDir();
  }

  // Loop assets in the dir.
  $path = $assetManager->getAssetsPath();
  foreach (new DirectoryIterator($path) as $file) {
    if ($file->isDot() || $file->isDir() || $file->isLink()) continue;
    $log[] = "Found " . $file->getPathname();

    if (preg_match('@^(.*)-([a-zA-Z0-9]+\.[^.]+)$@', $file->getFilename(), $matches)) {
      // Looks like a standard file.
      $identifier = $matches[1];
      $suffix = $matches[2];

      if (isset($currentAssets[$identifier])) {
        // This identifier is still required by an inlay.
        if (isset($assetsInDatabase[$identifier]) && $assetsInDatabase[$identifier] === $suffix) {
          // This is the latest version, keep it!
          // $log[] = "Keeping $identifier-$suffix";
          continue;
        }
        else {
          $log[] = "Removing $identifier-$suffix as not in db or not current suffix ";
        }
      }
      else {
        $log[] = "Removing $identifier-$suffix as not required by inlays";
      }
    }
    else {
      $log[] = "Removing " . $file->getFilename() . " as not valid filename syntax.";
    }
    if (!$params['dry_run']) {

      if ($params['use_trash']) {
        \CRM_Utils_File::createDir($trashDir);
        rename($file->getPathname(), $trashDir . '/' . $file->getFilename());
      }
      else {
        if (!unlink($file->getPathname())) {
          $log[] = "Failed removing " . $file->getPathname();
        }
      }
    }
  }

  // Now delete anything in the database that is not in the current assets list.
  $staleAssets = array_diff_key($assetsInDatabase, $currentAssets);
  if ($staleAssets) {
    ksort($staleAssets);
    $list = CRM_Core_DAO::escapeStrings(array_keys($staleAssets));
    $log[] = "Removing stale assets from database: $list";
    if (!$params['dry_run']) {
      \CRM_Core_DAO::executeQuery("DELETE FROM civicrm_inlay_asset WHERE identifier IN ($list)");
    }
  }

  return civicrm_api3_create_success($log, $params, 'Job', 'Inlaycleanupassets');
  //   throw new CRM_Core_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
}
