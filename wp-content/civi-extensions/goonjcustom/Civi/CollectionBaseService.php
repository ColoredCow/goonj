<?php

namespace Civi;

use Civi\Api4\CustomGroup;
use Civi\Api4\Address;
use Civi\Api4\Activity;
use Civi\Api4\Contribution;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\File;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;

/**
 *
 */
class CollectionBaseService extends AutoSubscriber {
  use CollectionSource;

  private static $stateCustomFieldDbDetails = [];
  private static $collectionAuthorized = NULL;
  private static $collectionAuthorizedStatus = NULL;
  private static $authorizationEmailQueued = NULL;
  private static $generatePosterRequest = NULL;

  /**
   * CollectionSourceId => latest poster file id we just wrote to poster_202.
   *
   * Confirmed in CiviCRM 6.13.x via MySQL general log:
   *   1. maybeGeneratePoster's UPDATE sets poster_202 = <file_id> (lands).
   *   2. Later in the same request, AssignInitiator (and similar hooks) call
   *      EckEntity::update which triggers CustomValueTable::store. That store
   *      writes a WIDE UPDATE of the whole Collection_Camp_Core_Details row
   *      and sets poster_202 = NULL because the caller didn't supply a value.
   *
   * applyPendingPosterFileIds (subscribed to hook_civicrm_custom) re-applies
   * the latest value AFTER each CustomValueTable::store completes, so the
   * wipe is recovered immediately and the column never ends up NULL once a
   * poster has been generated.
   */
  private static $pendingPosterFileIds = [];

  /**
   * Collection source id of each contribution being edited or deleted, keyed
   * by contribution id. Set in the pre hook, read in the post hook.
   */
  private static $contributionSourceBeforeSave = [];

  /**
   * Collection source ids of each material contribution activity being edited
   * or deleted, keyed by activity id. Set in the pre hook, read in the post hook.
   */
  private static $materialSourceBeforeSave = [];

  private static $materialContributionActivityTypeId = NULL;

  /**
   * Material contribution field for each collection source subtype. Goonj
   * Activities have no material contribution field, so they have no
   * material contributors.
   */
  private const MATERIAL_CONTRIBUTION_FIELD_BY_SUBTYPE = [
    'Collection_Camp' => 'Material_Contribution.Collection_Camp',
    'Dropping_Center' => 'Material_Contribution.Dropping_Center',
    'Institution_Collection_Camp' => 'Material_Contribution.Institution_Collection_Camp',
    'Institution_Dropping_Center' => 'Material_Contribution.Institution_Dropping_Center',
  ];

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_selectWhereClause' => [
        ['aclCollectionCamp'],
        ['aclUrbanPlanningVisit'],
      ],
      '&hook_civicrm_pre' => [
        ['handleAuthorizationEmails'],
        ['checkIfPosterNeedsToBeGenerated'],
        ['generateCollectionSourceCode'],
        ['rememberMaterialContributionSource'],
        ['rememberContributionSource'],

      ],
      '&hook_civicrm_post' => [
        ['maybeGeneratePoster', 20],
        ['handleAuthorizationEmailsPost', 10],
        ['refreshContributionSourceStats'],
        ['refreshMaterialContributionSourceStats'],
      ],
      // Fires after CustomValueTable::store. Re-applies poster_202 if a
      // downstream save (e.g. AssignInitiator's EckEntity::update) just
      // wiped it. See $pendingPosterFileIds for full context.
      '&hook_civicrm_custom' => [
        ['applyPendingPosterFileIds'],
      ],
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
    ];
  }

  /**
   * Re-applies the latest generated poster file id to poster_202.
   *
   * Fires after each CustomValueTable::store completes. If we recently
   * generated a poster for $entityID and the column was just wiped to NULL
   * (or set to a different value) by a wide custom-group save, we restore
   * the right file id.
   */
  public static function applyPendingPosterFileIds($op, $groupID, $entityID, &$params) {
    // Resolve the Collection_Camp_Core_Details group id once (name is stable, id may differ across envs).
    static $targetGroupId = NULL;
    if ($targetGroupId === NULL) {
      try {
        $row = CustomGroup::get(FALSE)
          ->addSelect('id')
          ->addWhere('name', '=', 'Collection_Camp_Core_Details')
          ->execute()
          ->single();
        $targetGroupId = (int) $row['id'];
      }
      catch (\Throwable $e) {
        return;
      }
    }
    if ((int) $groupID !== $targetGroupId) {
      return;
    }
    if (empty(self::$pendingPosterFileIds[$entityID])) {
      return;
    }

    $pending = self::$pendingPosterFileIds[$entityID];
    try {
      \CRM_Core_DAO::executeQuery(
        "UPDATE `{$pending['table']}` SET `{$pending['column']}` = %1 WHERE entity_id = %2",
        [
          1 => [$pending['fileId'], 'Integer'],
          2 => [$entityID, 'Integer'],
        ]
      );
    }
    catch (\Throwable $e) {
      \Civi::log()->error('applyPendingPosterFileIds failed', [
        'entityID' => $entityID,
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   *
   */
  public static function aclUrbanPlanningVisit($entity, &$clauses, $userId, $conditions = []) {
    if (!in_array($entity, ['Eck_Institution_Visit'])) {
      return FALSE;
    }

    $restrictedRoles = [
      'admin', 'urban_ops_admin', 'ho_account',
      'project_team_ho', 's2s_ho_team', 'njpc_ho_team', 'project_ho_and_accounts',
      'communications_team',
    ];

    if (\CRM_Core_Permission::checkAnyPerm($restrictedRoles)) {
      return;
    }

    try {
      // Step 0: Get user's group-controlled states.
      $statesControlled = self::getStatesControlledByUser($userId);

      if (empty($statesControlled)) {
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      // Step 1: Fetch all camps as array.
      try {
        $campsResult = EckEntity::get('Institution_Visit', FALSE)
          ->addSelect('Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_')
          ->execute();

        $camps = iterator_to_array($campsResult);
      }
      catch (\Exception $e) {
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      // Step 2: Extract unique processing center IDs.
      $processingCenterIds = array_unique(array_map(function ($camp) {
          return $camp['Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_'] ?? NULL;
      }, $camps));

      if (empty($processingCenterIds)) {
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      // Step 3: Fetch states of processing center contacts as array.
      $addressesResult = Address::get(FALSE)
        ->addSelect('contact_id')
        ->addSelect('state_province_id')
        ->addWhere('contact_id', 'IN', $processingCenterIds)
        ->execute();

      $addresses = iterator_to_array($addressesResult);
      $contactStates = [];
      foreach ($addresses as $addr) {
        $contactStates[$addr['contact_id']] = $addr['state_province_id'];
      }

      // Step 4: Filter camps based on group-controlled states.
      $allowedCampIds = [];
      foreach ($camps as $camp) {
        $contactId = $camp['Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_'] ?? NULL;
        $campId = $camp['id'] ?? NULL;

        if ($contactId && $campId && isset($contactStates[$contactId]) && in_array($contactStates[$contactId], $statesControlled)) {
          $allowedCampIds[] = $campId;
        }
      }

      if (empty($allowedCampIds)) {
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      // Step 5: Add allowed camp IDs to ACL clauses.
      if (!empty($allowedCampIds)) {
        // Instead of pushing into array, directly set the clause string:
        $clauses['id'] = ['IN (' . implode(',', array_map('intval', $allowedCampIds)) . ')'];
      }
      else {
        $clauses['id'] = ['IN (null)'];
      }

      return TRUE;

    }
    catch (\Exception $e) {
      \Civi::log()->warning("ACL: Unable to apply ACL on $entity for user $userId. " . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Remembers which collection sources a material contribution activity was
   * linked to before it is edited or deleted, so the post hook can refresh
   * them too (see refreshMaterialContributionSourceStats).
   */
  public static function rememberMaterialContributionSource(string $op, string $objectName, $objectId, &$params) {
    if ($objectName !== 'Activity' || !in_array($op, ['edit', 'delete'], TRUE) || empty($objectId)) {
      return;
    }

    $sourceIds = self::getMaterialContributionSourceIds((int) $objectId);
    if ($sourceIds !== NULL) {
      self::$materialSourceBeforeSave[$objectId] = $sourceIds;
    }
  }

  /**
   * Refreshes the Footfall & Contribution Details of the collection source a
   * material contribution belongs to, on every create, edit and delete.
   *
   * Covers material contributions added from the volunteer form as well as
   * from the back end, since both save the same Material Contribution activity.
   */
  public static function refreshMaterialContributionSourceStats(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Activity' || !in_array($op, ['create', 'edit', 'delete'], TRUE) || empty($objectId)) {
      return;
    }

    $wasMaterialContribution = array_key_exists($objectId, self::$materialSourceBeforeSave);
    $sourceIds = self::$materialSourceBeforeSave[$objectId] ?? [];
    unset(self::$materialSourceBeforeSave[$objectId]);

    $isNewMaterialContribution = $op === 'create'
      && (int) ($objectRef->activity_type_id ?? 0) === self::getMaterialContributionActivityTypeId();

    if ($op !== 'delete' && ($wasMaterialContribution || $isNewMaterialContribution)) {
      $sourceIds = array_merge($sourceIds, self::getMaterialContributionSourceIds((int) $objectId) ?? []);
    }

    foreach (array_unique($sourceIds) as $sourceId) {
      try {
        self::recalculateContributionStats($sourceId);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Failed to refresh contribution stats for collection source', [
          'collection_source_id' => $sourceId,
          'activity_id' => $objectId,
          'message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Returns the collection source ids a Material Contribution activity is
   * linked to, or NULL if the activity is not a Material Contribution.
   */
  private static function getMaterialContributionSourceIds(int $activityId): ?array {
    $materialFields = array_values(self::MATERIAL_CONTRIBUTION_FIELD_BY_SUBTYPE);

    $activity = Activity::get(FALSE)
      ->addSelect(...$materialFields)
      ->addWhere('id', '=', $activityId)
      ->addWhere('activity_type_id', '=', self::getMaterialContributionActivityTypeId())
      ->execute()
      ->first();

    if (!$activity) {
      return NULL;
    }

    $sourceIds = [];
    foreach ($materialFields as $field) {
      if (!empty($activity[$field])) {
        $sourceIds[] = (int) $activity[$field];
      }
    }

    return $sourceIds;
  }

  /**
   *
   */
  private static function getMaterialContributionActivityTypeId(): int {
    if (self::$materialContributionActivityTypeId === NULL) {
      self::$materialContributionActivityTypeId = (int) \CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Material Contribution');
    }

    return self::$materialContributionActivityTypeId;
  }

  /**
   * Remembers which collection source a contribution was linked to before
   * it is edited or deleted.
   *
   * The post hook needs this to refresh the old source too: when a
   * contribution is deleted its custom values are already gone, and when its
   * Collection Source is changed the old source would otherwise keep counting it.
   */
  public static function rememberContributionSource(string $op, string $objectName, $objectId, &$params) {
    if ($objectName !== 'Contribution' || !in_array($op, ['edit', 'delete'], TRUE) || empty($objectId)) {
      return;
    }

    $contribution = Contribution::get(FALSE)
      ->addSelect('Contribution_Details.Source')
      ->addWhere('id', '=', $objectId)
      ->execute()
      ->first();

    if (!empty($contribution['Contribution_Details.Source'])) {
      self::$contributionSourceBeforeSave[$objectId] = (int) $contribution['Contribution_Details.Source'];
    }
  }

  /**
   * Refreshes the Footfall & Contribution Details of the collection source a
   * contribution belongs to, on every create, edit and delete.
   *
   * This runs whatever the contribution's status. A CSV import saves a
   * contribution as Pending and completes it with a separate Payment save, and
   * a cancellation or refund moves it away from Completed; the totals are
   * recalculated from the database each time, so every one of these ends with
   * the right numbers.
   */
  public static function refreshContributionSourceStats(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Contribution' || !in_array($op, ['create', 'edit', 'delete'], TRUE) || empty($objectId)) {
      return;
    }

    $sourceIds = [];

    if (isset(self::$contributionSourceBeforeSave[$objectId])) {
      $sourceIds[] = self::$contributionSourceBeforeSave[$objectId];
      unset(self::$contributionSourceBeforeSave[$objectId]);
    }

    if ($op !== 'delete') {
      $contribution = Contribution::get(FALSE)
        ->addSelect('Contribution_Details.Source')
        ->addWhere('id', '=', $objectId)
        ->execute()
        ->first();

      if (!empty($contribution['Contribution_Details.Source'])) {
        $sourceIds[] = (int) $contribution['Contribution_Details.Source'];
      }
    }

    foreach (array_unique($sourceIds) as $sourceId) {
      try {
        self::recalculateContributionStats($sourceId);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Failed to refresh contribution stats for collection source', [
          'collection_source_id' => $sourceId,
          'contribution_id' => $objectId,
          'message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Recalculates all five Footfall & Contribution Details fields of a
   * collection source from its material contributions and its Completed
   * monetary contributions, and saves them only if something changed.
   *
   * @return array|null
   *   The calculated values keyed by field name, or NULL if the collection
   *   source does not exist.
   */
  public static function recalculateContributionStats(int $collectionSourceId, bool $save = TRUE): ?array {
    $collectionSource = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect(
        'subtype:name',
        'Core_Contribution_Details.Number_of_unique_contributors',
        'Core_Contribution_Details.Number_of_unique_material_contributors',
        'Core_Contribution_Details.Number_of_unique_monetary_contributors',
        'Core_Contribution_Details.Total_online_monetary_contributions',
        'Core_Contribution_Details.Total_cash_cheque_monetary_contributions'
      )
      ->addWhere('id', '=', $collectionSourceId)
      ->execute()
      ->first();

    if (!$collectionSource) {
      return NULL;
    }

    $materialContactIds = [];
    $materialField = self::MATERIAL_CONTRIBUTION_FIELD_BY_SUBTYPE[$collectionSource['subtype:name']] ?? NULL;

    if ($materialField) {
      $materialActivities = Activity::get(FALSE)
        ->addSelect('source_contact_id')
        ->addWhere($materialField, '=', $collectionSourceId)
        ->execute();

      foreach ($materialActivities as $activity) {
        if (!empty($activity['source_contact_id'])) {
          $materialContactIds[(int) $activity['source_contact_id']] = TRUE;
        }
      }
    }

    $monetaryContactIds = [];
    $onlineTotal = 0.0;
    $cashChequeTotal = 0.0;

    $monetaryContributions = Contribution::get(FALSE)
      ->addSelect('contact_id', 'total_amount', 'payment_instrument_id:name')
      ->addWhere('Contribution_Details.Source', '=', $collectionSourceId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute();

    foreach ($monetaryContributions as $contribution) {
      if (!empty($contribution['contact_id'])) {
        $monetaryContactIds[(int) $contribution['contact_id']] = TRUE;
      }

      $amount = (float) ($contribution['total_amount'] ?? 0);
      $method = $contribution['payment_instrument_id:name'] ?? '';

      if ($method === 'Credit Card') {
        $onlineTotal += $amount;
      }
      elseif (in_array($method, ['Cash', 'Check'], TRUE)) {
        $cashChequeTotal += $amount;
      }
    }

    $stats = [
      'Core_Contribution_Details.Number_of_unique_contributors' => count($materialContactIds + $monetaryContactIds),
      'Core_Contribution_Details.Number_of_unique_material_contributors' => count($materialContactIds),
      'Core_Contribution_Details.Number_of_unique_monetary_contributors' => count($monetaryContactIds),
      'Core_Contribution_Details.Total_online_monetary_contributions' => $onlineTotal,
      'Core_Contribution_Details.Total_cash_cheque_monetary_contributions' => $cashChequeTotal,
    ];

    $changed = FALSE;
    foreach ($stats as $field => $value) {
      $current = $collectionSource[$field];
      if ($current === NULL || $current === '' || abs((float) $current - (float) $value) > 0.001) {
        $changed = TRUE;
        break;
      }
    }

    if ($save && $changed) {
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $collectionSourceId)
        ->setValues($stats)
        ->execute();
    }

    return $stats;
  }

  /**
   *
   */
  public static function checkIfPosterNeedsToBeGenerated($op, $objectName, $id, &$params) {
    if ($objectName !== 'Eck_Collection_Camp' || $op !== 'edit' || !isset($params['Collection_Camp_Core_Details.Poster_Template'])) {
      return;
    }

    $messageTemplateId = $params['Collection_Camp_Core_Details.Poster_Template'];

    self::$generatePosterRequest = [
      'collectionSourceId' => $id,
      'messageTemplateId' => $messageTemplateId,
      'customData' => $params,
    ];
  }

  /**
   *
   */
  private static function generateBaseFileName($collectionSourceId) {
    // Get the entity subtype name for the collection source.
    $entitySubtype = self::getEntitySubtypeName($collectionSourceId);

    $baseFileName = strtolower($entitySubtype) . "_{$collectionSourceId}.png";

    return $baseFileName;
  }

  /**
   *
   */
  public static function maybeGeneratePoster(string $op, string $objectName, int $objectId, &$objectRef) {

    if (!self::$generatePosterRequest || $objectName !== 'Eck_Collection_Camp' || $op !== 'edit') {
      return;
    }

    $collectionSourceId = (int) self::$generatePosterRequest['collectionSourceId'];
    $messageTemplateId = self::$generatePosterRequest['messageTemplateId'];

    $messageTemplate = MessageTemplate::get(FALSE)
      ->addWhere('id', '=', $messageTemplateId)
      ->execute()
      ->single();
    $html = $messageTemplate['msg_html'];

    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '{literal}<style>$1</style>{/literal}', $html);
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '{literal}<script>$1</script>{/literal}', $html);

    $rendered = \CRM_Core_TokenSmarty::render(
      ['html' => $html],
      [
        'collectionSourceId' => $collectionSourceId,
        'collectionSourceCustomData' => self::$generatePosterRequest['customData'],
      ]
    );

    $baseFileName = self::generateBaseFileName($collectionSourceId);
    $fileName = \CRM_Utils_File::makeFileName($baseFileName);
    $tempFilePath = \CRM_Utils_File::tempnam($baseFileName);

    $posterGenerated = self::html2image($rendered['html'], $tempFilePath);
    if (!$posterGenerated) {
      \Civi::log()->info('There was an error generating the poster!');
      return;
    }

    try {
      $posterField = CustomField::get(FALSE)
        ->addSelect('id', 'custom_group_id.table_name')
        ->addWhere('custom_group_id:name', '=', 'Collection_Camp_Core_Details')
        ->addWhere('name', '=', 'poster')
        ->execute()
        ->single();
    }
    catch (\Exception $ex) {
      \CRM_Core_Error::debug_log_message('Cannot find field to save poster for collection camp ID ' . $collectionSourceId);
      return FALSE;
    }

    $posterFieldId = 'custom_' . $posterField['id'];
    $tableName = $posterField['custom_group_id.table_name'];

    // Save the poster image as an attachment linked to the collection camp.
    $params = [
      'entity_id' => $collectionSourceId,
      'entity_table' => 'civicrm_eck_collection_camp',
      'name' => $baseFileName,
      'mime_type' => 'image/png',
      'field_name' => $posterFieldId,
      'options' => [
        'move-file' => $tempFilePath,
      ],
    ];

    $result = civicrm_api3('Attachment', 'create', $params);

    if (empty($result['id'])) {
      \CRM_Core_Error::debug_log_message('Failed to upload poster image for collection camp ID ' . $collectionSourceId);
      return FALSE;
    }

    $attachment = $result['values'][$result['id']] ?? NULL;

    if (empty($attachment['path'])) {
      return;
    }

    $columnName = 'poster_' . $posterField['id'];

    if (!$tableName || !$columnName) {
      return;
    }
    try {
      $checkQuery = "
        SELECT `$columnName` AS poster_value
        FROM `$tableName`
        WHERE entity_id = %1
      ";
      $dao = \CRM_Core_DAO::executeQuery($checkQuery, [
        1 => [$collectionSourceId, 'Integer'],
      ]);

      $rowExists = $dao->fetch();

      if ($rowExists) {
        // Always point poster_202 at the freshly-generated file id. Previously
        // there was a "Branch B" that kept the existing file_id and updated its
        // URI via File::update, but CiviCRM 6.13.x rejects URI changes
        // ("Uri of existing file cannot be changed"). Just replace the
        // reference — orphan File rows can be cleaned up separately if needed.
        $updateQuery = "
          UPDATE `$tableName`
          SET `{$columnName}` = %1
          WHERE entity_id = %2
        ";
        \CRM_Core_DAO::executeQuery($updateQuery, [
          1 => [$result['id'], 'Integer'],
          2 => [$collectionSourceId, 'Integer'],
        ]);

        // Stash for applyPendingPosterFileIds — that hook re-applies this
        // value after CustomValueTable::store wide-writes the row, which
        // otherwise wipes poster_202 to NULL on CiviCRM 6.13.x.
        self::$pendingPosterFileIds[$collectionSourceId] = [
          'fileId' => $result['id'],
          'table' => $tableName,
          'column' => $columnName,
        ];
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error checking/updating poster for collection ID {$collectionSourceId}: " . $e->getMessage());
    }
  }

  /**
   *
   */
  public static function html2image($htmlContent, $outputPath) {
    $nodePath = NODE_PATH;
    $puppeteerJsPath = escapeshellarg(\CRM_Goonjcustom_ExtensionUtil::path('js/puppeteer.js'));
    $htmlContent = escapeshellarg($htmlContent);

    $command = "$nodePath $puppeteerJsPath $htmlContent $outputPath";
    exec($command, $output, $returnCode);

    if ($returnCode === 0) {
      \Civi::log()->info("Poster image successfully created at: $outputPath");
      return TRUE;
    }
    else {
      \Civi::log()->debug("Failed to generate poster image, return code: $returnCode");
      return FALSE;
    }
  }

  /**
   *
   */
  public static function aclCollectionCamp($entity, &$clauses, $userId, $conditions) {
    if (!in_array($entity, ['Eck_Collection_Camp'])) {
      return FALSE;
    }

    $restrictedRoles = ['admin', 'urban_ops_admin', 'ho_account', 'project_team_ho', 's2s_ho_team', 'njpc_ho_team', 'project_ho_and_accounts', 'api_global_access', 'communications_team'];

    $hasRestrictedRole = \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      return;
    }

    try {
      $statesControlled = self::getStatesControlledByUser($userId);

      if (empty($statesControlled)) {
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      $statesList = implode(',', array_map('intval', $statesControlled));

      $stateFields = self::getStateFieldDbDetails($entity);

      $selectQueries = [];
      foreach ($stateFields as $stateField) {
        $selectQueries[] = sprintf(
            'SELECT entity_id FROM `%1$s` WHERE `%2$s` IN (%3$s)',
            $stateField['tableName'],
            $stateField['columnName'],
            $statesList,
        );
      }

      if (empty($selectQueries)) {
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      $concatenatedQuery = implode(' UNION ', $selectQueries);

      $clauseString = "IN ($concatenatedQuery)";

      $clauses['id'][] = $clauseString;
    }
    catch (\Exception $e) {
      \Civi::log()->warning("Unable to apply acl on collection camp for user $userId. " . $e->getMessage());
    }

    return TRUE;
  }

  /**
   * Get all state IDs controlled by all chapter-team groups the user belongs to.
   */
  private static function getStatesControlledByUser(int $userId): array {
    $teamGroupContacts = GroupContact::get(FALSE)
      ->addSelect('group_id')
      ->addWhere('contact_id', '=', $userId)
      ->addWhere('status', '=', 'Added')
      ->addWhere('group_id.Chapter_Contact_Group.Use_Case', '=', 'chapter-team')
      ->execute();

    $groupIds = $teamGroupContacts->column('group_id');

    if (empty($groupIds)) {
      \Civi::log()->debug('ACL multi-group: No chapter-team groups found for user.', ['userId' => $userId]);
      return [];
    }

    $groups = Group::get(FALSE)
      ->addSelect('id', 'Chapter_Contact_Group.States_controlled')
      ->addWhere('id', 'IN', $groupIds)
      ->execute();

    $allStates = [];
    foreach ($groups as $group) {
      $states = $group['Chapter_Contact_Group.States_controlled'] ?? [];
      if (!empty($states)) {
        $allStates = array_merge($allStates, $states);
      }
    }

    $allStates = array_unique($allStates);

    \Civi::log()->debug('ACL multi-group: Resolved states for user.', [
      'userId' => $userId,
      'groupCount' => count($groupIds),
      'groupIds' => $groupIds,
      'stateCount' => count($allStates),
    ]);

    return $allStates;
  }

  /**
   *
   */
  private static function getStateFieldDbDetails($entity) {
    if (empty(self::$stateCustomFieldDbDetails)) {

      if ($entity == 'Eck_Institution_Visit') {
        $stateGroupNameMapper = self::getStateGroupNameMapperForUrbanVisit();
      }
      else {
        $stateGroupNameMapper = self::getStateGroupNameMapper();
      }

      $stateFields = [];
      foreach ($stateGroupNameMapper as $subtype => $groupName) {
        $customField = CustomField::get(FALSE)
          ->addSelect('column_name', 'custom_group_id.table_name')
          ->addWhere('custom_group_id.name', '=', $groupName)
          ->addWhere('name', '=', 'state')
          ->execute()
          ->single();

        if ($customField) {
          $stateFields[] = [
            'tableName' => $customField['custom_group_id.table_name'],
            'columnName' => $customField['column_name'],
          ];
        }
      }

      self::$stateCustomFieldDbDetails = $stateFields;
    }

    return self::$stateCustomFieldDbDetails;
  }

  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  public static function handleAuthorizationEmails(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || !$objectId) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    $subType = $objectRef['subtype'] ?? '';

    if (!$newStatus) {
      return;
    }

    $currentCollectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'Institution_Goonj_Activities.Institution_POC', 'Institution_Collection_Camp_Intent.Institution_POC', 'Institution_Dropping_Center_Intent.Institution_POC', 'subtype:name')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();
    \Civi::log()->info('currentCollectionCamp', ['currentCollectionCamp' => $currentCollectionCamp]);

    $initiatorId = self::getInitiatorId($currentCollectionCamp);
    \Civi::log()->info('initiatorId', ['initiatorId' => $initiatorId]);

    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];

    if (!in_array($newStatus, ['authorized', 'unauthorized'])) {
      return;
    }

    if (!self::shouldSendAuthorizationEmail($currentCollectionCamp['subtype:name'], $newStatus, $objectRef)) {
      return;
    }

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      self::$collectionAuthorized = $objectId;
      self::$collectionAuthorizedStatus = $newStatus;
      \Civi::log()->info('currentStatus', ['currentStatus' => $currentStatus, 'newStatus' => $newStatus]);
    }
  }

  /**
   *
   */
  public static function handleAuthorizationEmailsPost(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || $op !== 'edit' || !$objectId || $objectId !== self::$collectionAuthorized) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'Institution_Goonj_Activities.Institution_POC', 'Institution_Collection_Camp_Intent.Institution_POC', 'Institution_Dropping_Center_Intent.Institution_POC', 'subtype', 'subtype:name')
      ->addWhere('id', '=', $objectRef->id)
      ->execute()->single();

    $initiatorId = self::getInitiatorId($collectionCamp);
    \Civi::log()->info('initiatorId2', ['initiatorId2' => $initiatorId, self::$authorizationEmailQueued]);

    $collectionSourceId = $collectionCamp['id'];
    $subtype = $collectionCamp['subtype'];

    if (!self::$authorizationEmailQueued) {
      self::queueAuthorizationEmail($initiatorId, $subtype, self::$collectionAuthorizedStatus, $collectionSourceId);
    }
  }

  /**
   * Send Authorization Email to contact.
   */
  private static function queueAuthorizationEmail($initiatorId, $subtype, $status, $collectionSourceId) {

    try {
      $params = [
        'initiatorId' => $initiatorId,
        'subtype' => $subtype,
        'status' => $status,
        'collectionSourceId' => $collectionSourceId,
      ];

      $queue = \Civi::queue(\CRM_Goonjcustom_Engine::QUEUE_NAME, [
        'type' => 'Sql',
        'error' => 'abort',
        'runner' => 'task',
      ]);

      $queue->createItem(new \CRM_Queue_Task(
          [self::class, 'processQueuedEmail'],
          [$params],
      ), [
        'weight' => 1,
      ]);

      self::$authorizationEmailQueued = TRUE;

    }
    catch (\Exception $ex) {
      \Civi::log()->debug('Cannot queue authorization email for initiator.', [
        'initiatorId' => $initiatorId,
        'status' => $status,
        'entityId' => $objectRef['id'],
        'error' => $ex->getMessage(),
      ]);
    }
  }

  /**
   *
   */
  public static function processQueuedEmail($queue, $params) {
    try {
      $emailParams = self::getAuthorizationEmailParams($params);
      civicrm_api3('MessageTemplate', 'send', $emailParams);
    }
    catch (\Exception $ex) {
      \Civi::log()->error('Failed to send email.', ['error' => $ex->getMessage(), 'params' => $emailParams]);
    }
  }

  /**
   *
   */
  private static function getAuthorizationEmailParams($params) {
    $collectionSourceId = $params['collectionSourceId'];
    $collectionSourceType = $params['subtype'];
    $status = $params['status'];

    $initiatorId = $params['initiatorId'];

    $collectionCampSubtypes = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'eck_sub_types')
      ->addWhere('grouping', '=', 'Collection_Camp')
      ->execute();
    foreach ($collectionCampSubtypes as $subtype) {
      $subtypeValue = $subtype['value'];
      $subtypeName = $subtype['name'];

      $mapper[$subtypeValue]['authorized'] = $subtypeName . ' authorized';
      $mapper[$subtypeValue]['unauthorized'] = $subtypeName . ' unauthorized';
    }

    $msgTitleStartsWith = $mapper[$collectionSourceType][$status] . '%';
    $messageTemplates = MessageTemplate::get(FALSE)
      ->addSelect('id')
      ->addWhere('msg_title', 'LIKE', $msgTitleStartsWith)
      ->addWhere('is_active', '=', TRUE)
      ->execute();

    $messageTemplate = $messageTemplates->first();
    $messageTemplateId = $messageTemplate['id'];

    $toEmail = Email::get(FALSE)
      ->addWhere('contact_id', '=', $initiatorId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()->single();
    $fromEmail = OptionValue::get(FALSE)
      ->addSelect('label')
      ->addWhere('option_group_id:name', '=', 'from_email_address')
      ->addWhere('is_default', '=', TRUE)
      ->execute()->single();

    $emailParams = [
      'contact_id' => $initiatorId,
      'to_email' => $toEmail['email'],
      'from' => $fromEmail['label'],
      'id' => $messageTemplateId,
    ];

    if ($status === 'authorized') {
      $collectionSource = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Collection_Camp_Core_Details.Poster')
        ->addWhere('id', '=', $collectionSourceId)
        ->execute()->single();

      $posterFileId = $collectionSource['Collection_Camp_Core_Details.Poster'];

      if ($posterFileId) {
        $file = File::get(FALSE)
          ->addWhere('id', '=', $posterFileId)
          ->execute()->single();

        $config = \CRM_Core_Config::singleton();
        $filePath = $config->customFileUploadDir . $file['uri'];
        $emailParams['attachments'][] = [
          'fullPath' => $filePath,
          'mime_type' => $file['mime_type'],
          'cleanName' => self::generateBaseFileName($collectionSourceId),
        ];
      }

    }

    return $emailParams;
  }

  /**
   *
   */
  public static function setIndianStateOptions(string $entity, string $field, ?array &$options, array $params) {
    $allowedEntities = [
      'Eck_Collection_Camp',
      'Eck_Institution_Visit',
      'Eck_Meetings_Sessions',
      'Eck_Engagement_Intent',
    ];

    if (!in_array($entity, $allowedEntities)) {
      return FALSE;
    }

    if ($entity == 'Eck_Institution_Visit') {
      $stateGroupNameMapper = self::getStateGroupNameMapperForUrbanVisit();
    }
    elseif ($entity == 'Eck_Meetings_Sessions') {
      $stateGroupNameMapper = self::getStateGroupNameMapperForMeeting();
    }
    elseif ($entity == 'Eck_Engagement_Intent') {
      $stateGroupNameMapper = self::getStateGroupNameMapperForEngagementIntent();
    }
    else {
      $stateGroupNameMapper = self::getStateGroupNameMapper();
    }

    $stateFieldNames = array_map(fn ($i) => "{$i}.State", $stateGroupNameMapper);

    if (!in_array($field, $stateFieldNames)) {
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

  /**
   *
   */
  public static function getStateFieldNames() {
    $stateGroupNameMapper = self::getStateGroupNameMapper();

    $intentStateFields = CustomField::get(FALSE)
      ->addWhere('custom_group_id:name', 'IN', array_values($stateGroupNameMapper))
      ->addWhere('name', '=', 'State')
      ->execute();

    $statefieldNames = array_map(fn ($field) => 'custom_' . $field['id'], $intentStateFields->jsonSerialize());

    return $statefieldNames;
  }

  /**
   *
   */
  private static function getStateGroupNameMapper() {
    return [
      'Collection_Camp' => 'Collection_Camp_Intent_Details',
      'Dropping_Center' => 'Dropping_Centre',
      'Institution_Collection_Camp' => 'Institution_Collection_Camp_Intent',
      'Goonj_Activities' => 'Goonj_Activities',
      'Institution_Dropping_Center' => 'Institution_Dropping_Center_Intent',
      'Institution_Goonj_Activities' => 'Institution_Goonj_Activities',
    ];
  }

  /**
   *
   */
  private static function getStateGroupNameMapperForUrbanVisit() {
    return [
      'Institution_Visit' => 'Urban_Planned_Visit',
    ];
  }

  /**
   *
   */
  private static function getStateGroupNameMapperForMeeting() {
    return [
      'Meetings_Sessions' => 'Urban_Meetings',
    ];
  }

  /**
   * Maps the Engagement Intent Eck subtype to its state custom group.
   */
  private static function getStateGroupNameMapperForEngagementIntent() {
    return [
      'Engagement_Intent' => 'Engagement_Intent_Form',
    ];
  }

}
