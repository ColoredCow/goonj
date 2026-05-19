<?php
namespace Civi\Api4;

use \Civi\Api4\Generic\AbstractEntity;
/**
 * InlayType Entity
 *
 * @package Civi\Api4
 */
class InlayType extends AbstractEntity {
  /**
   * @return \Civi\Api4\Generic\BasicGetFieldsAction
   */
    /**
   * Every entity **must** implement `getFields`.
   *
   * This tells the action classes what input/output fields to expect,
   * and also populates the _API Explorer_.
   *
   * The `BasicGetFieldsAction` takes a callback function. We could have defined the function elsewhere
   * and passed a `callable` reference to it, but passing in an anonymous function works too.
   *
   * The callback function takes the `BasicGetFieldsAction` object as a parameter in case we need to access its properties.
   * Especially useful is the `getAction()` method as we may need to adjust the list of fields per action.
   *
   * Note that it's possible to bypass this function if an action class lists its own fields by declaring a `fields()` method.
   *
   * Read more about how to implement your own `GetFields` action:
   * @see \Civi\Api4\Generic\BasicGetFieldsAction
   *
   * @param bool $checkPermissions
   *
   * @return Generic\BasicGetFieldsAction
   */
  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function($getFieldsAction) {
      return [
        [
          'name' => 'class',
          'data_type' => 'String',
          'description' => 'Class name',
        ],
      ];
    }))->setCheckPermissions($checkPermissions);
  }

}
