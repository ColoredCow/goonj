<?php
namespace Civi\Api4;

/**
 * Inlay entity.
 *
 * Provided by the Inlay extension.
 *
 * @package Civi\Api4
 */
class Inlay extends Generic\DAOEntity {

  /**
   * Override the factory method for the Get action with our own implementation so we can decode the JSON.
   *
   * @param bool $checkPermissions
   * @return \Civi\Api4\Generic\DAOGetAction
   */
  public static function get($checkPermissions = TRUE) {
    return (new Action\Inlay\Get(static::class, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return DAOSaveAction
   */
  public static function save($checkPermissions = TRUE) {
    return (new Action\Inlay\Save(static::class, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return DAOUpdateAction
   */
  public static function update($checkPermissions = TRUE) {
    return (new Action\Inlay\Update(static::class, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return DAOCreateAction
   *
   * @throws \CRM_Core_Exception
   */
  public static function create($checkPermissions = TRUE) {
    return (new Action\Inlay\Create(static::class, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
