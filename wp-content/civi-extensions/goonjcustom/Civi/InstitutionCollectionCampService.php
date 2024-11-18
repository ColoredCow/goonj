<?php

namespace Civi;

use Civi\Api4\CustomField;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionCollectionCampService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
    ];
  }

  /**
   *
   */
  public static function setIndianStateOptions(string $entity, string $field, array &$options, array $params) {
    if ($entity !== 'Eck_Collection_Camp') {
      return;
    }

    $intentStateFields = CustomField::get(FALSE)
      ->addWhere('custom_group_id:name', '=', 'Institution_Collection_Camp_Intent')
      ->addWhere('name', '=', 'State')
      ->execute();

    $stateField = $intentStateFields->first();

    $statefieldId = $stateField['id'];

    if ($field !== "custom_$statefieldId") {
      return;
    }

    $indianStates = StateProvince::get(FALSE)
      ->addWhere('country_id.iso_code', '=', 'IN')
      ->addOrderBy('name', 'ASC')
      ->execute();

    $stateOptions = [];
    foreach ($indianStates as $state) {
      if ($state['is_active']) {
        $stateOptions[$state['id']] = $state['name'];
      }
    }

    $options = $stateOptions;

  }

}
