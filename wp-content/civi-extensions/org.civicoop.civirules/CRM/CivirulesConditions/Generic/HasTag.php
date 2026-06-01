<?php

use Civi\Api4\EntityTag;
use Civi\Api4\Tag;

/**
 * Class for CiviRules generic HasTag condition
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Generic_HasTag {
  protected $entityTable = "civicrm_contact";

  /**
   * @param $entityTable
   */
  public function setEntityTable($entityTable) {
    $this->entityTable = $entityTable;
  }

  /**
   * Method to get tags with API4
   * @param $entityId
   * @return array
   */
  public function getApi4TagsWithEntityId($entityId) {
    return EntityTag::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addSelect('tag_id')
      ->addWhere('entity_table', '=', $this->entityTable)
      ->addWhere('entity_id', '=', $entityId)
      ->execute()->column('tag_id');
  }

  /**
   * @param int $entityId
   * @param array $tagIds
   * @return bool
   */
  public function entityHasNotTag(int $entityId, array $tagIds): bool {
    $isValid = TRUE;
    $tags = $this->getApi4TagsWithEntityId($entityId);
    foreach ($tagIds as $tagId) {
      if (in_array($tagId, $tags)) {
        $isValid = FALSE;
      }
    }
    return $isValid;
  }

  /**
   * @param int $entityId
   * @param array $tagIds
   * @return bool
   */
  public function entityHasAllTags(int $entityId, array $tagIds):bool {
    $isValid = 0;
    $tags = $this->getApi4TagsWithEntityId($entityId);
    foreach($tagIds as $tagId) {
      if (in_array($tagId, $tags)) {
        $isValid++;
      }
    }
    if (count($tagIds) == $isValid && count($tagIds) > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @param int $entityId
   * @param array $tagIds
   * @return bool
   */
  public function entityHasOneOfTags(int $entityId, array $tagIds): bool {
    $isValid = FALSE;
    $tags = $this->getApi4TagsWithEntityId($entityId);
    foreach($tagIds as $tagId) {
      if (in_array($tagId, $tags)) {
        $isValid = TRUE;
        break;
      }
    }
    return $isValid;
  }

  /**
   * Method to get operators
   *
   * @return array
   */
  public function getOperatorOptions(): array {
    return [
      'in one of' => ts('In one of selected'),
      'in all of' => ts('In all selected'),
      'not in' => ts('Not in selected'),
    ];
  }

  /**
   * Method to get the tags for the entity
   *
   * @return array
   */
  public function getEntityTags() {
    return $this->getApi4Tags();
  }

  /**
   * Method to get all contact tags with API4
   */
  private function getApi4Tags() {
    $tags = [];
    $apiTags = Tag::get(FALSE)
      ->addSelect('name')
      ->addWhere('used_for', 'LIKE', '%' . $this->entityTable . '%')
      ->execute();
    foreach ($apiTags as $apiTag) {
      if (!isset($tags[$apiTag['id']])) {
        $tags[$apiTag['id']] = $apiTag['name'];
      }
    }
    return $tags;
  }

}
