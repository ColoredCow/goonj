<?php

use Civi\Api4\EntityTag;
use Civi\Api4\Tag;
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Tag_EntityTag {

  /**
   * Method to get all contact tags with API4
   *
   * @param $table
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getApi4Tags($table) {
    $tags = [];
    $apiTags = Tag::get(FALSE)
      ->addSelect('name')
      ->addWhere('used_for', 'LIKE', '%' . $table . '%')
      ->execute();
    foreach ($apiTags as $apiTag) {
      if (!isset($tags[$apiTag['id']])) {
        $tags[$apiTag['id']] = $apiTag['name'];
      }
    }
    return $tags;
  }

  /**
   * Method to add entity tag with API4
   *
   * @param $entityTable
   * @param $entityId
   * @param $tagId
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function createApi4EntityTag($entityTable, $entityId, $tagId) {
    if (empty($entityTable) || empty($entityId) || empty($tagId)) {
      return;
    }
    EntityTag::create(FALSE)
      ->addValue('entity_table', $entityTable)
      ->addValue('entity_id', $entityId)
      ->addValue('tag_id', $tagId)
      ->execute();
  }

  /**
   * Method to remove entity tag with API4
   *
   * @param $entityTable
   * @param $entityId
   * @param $tagId
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function deleteApi4EntityTag($entityTable, $entityId, $tagId) {
    if (empty($entityTable) || empty($entityId) || empty($tagId)) {
      return;
    }
    EntityTag::delete(FALSE)
      ->addWhere('entity_table', '=', $entityTable)
      ->addWhere('entity_id', '=', $entityId)
      ->addWhere('tag_id', '=', $tagId)
      ->execute();
  }

}
