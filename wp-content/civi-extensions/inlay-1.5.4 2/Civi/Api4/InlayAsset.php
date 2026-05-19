<?php
namespace Civi\Api4;

use Civi\Api4\Action\InlayAsset\Delete;
use Civi\Api4\Action\InlayAsset\Get;
use Civi\Api4\Action\InlayAsset\Put;
use Civi\Api4\Generic\BasicGetFieldsAction;

/**
 * Utility entity for dealing with assets.
 *
 * Provided by the Inlay extension.
 *
 * @package Civi\Api4
 */
class InlayAsset extends Generic\AbstractEntity {

  /**
   * @return \Civi\Api4\Generic\BasicGetFieldsAction
   */
  public static function getFields() {
    return (new BasicGetFieldsAction(__CLASS__, __FUNCTION__, function($getFieldsAction) {
      return [
        [
          'name' => 'identifier',
          'data_type' => 'String',
          'description' => 'Unique identifier.',
        ],
        [
          'name' => 'url',
          'data_type' => 'String',
          'description' => 'Public URL to the asset.',
        ],
        [
          'name' => 'path',
          'data_type' => 'String',
          'description' => 'Internal full path to asset file.',
        ],
      ];
    }));
  }

  /**
   * Save data to an asset file.
   *
   * This will replace an existing asset for that identifier.
   */
  public static function put(): Put {
    return new Put(__CLASS__, __FUNCTION__);
  }

  /**
   * Get asset data from identifier.
   */
  public static function get(): Get {
    return new Get(__CLASS__, __FUNCTION__);
  }

  /**
   * Delete an asset
   */
  public static function delete(): Delete {
    return new Delete(__CLASS__, __FUNCTION__);
  }

}
