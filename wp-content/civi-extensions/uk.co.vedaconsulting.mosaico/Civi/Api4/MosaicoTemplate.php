<?php
namespace Civi\Api4;

/**
 * MosaicoTemplate entity.
 *
 * Provided by the Mosaico extension.
 *
 * @package Civi\Api4
 */
class MosaicoTemplate extends Generic\DAOEntity {

  /**
   * @return array
   */
  public static function permissions():array {
    return [
      'get' => ['access CiviCRM'],
      'create' => [
        ['edit message templates', 'edit user driven message templates']
      ],
      'update' => [
        ['edit message templates', 'edit user driven message templates']
      ],
      'delete' => [
        ['edit message templates', 'edit user driven message templates']
      ]
    ];
  }

}
