<?php

namespace Civi;

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
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_selectWhereClause' => 'aclCollectionCamp',
      '&hook_civicrm_pre' => [
        ['handleAuthorizationEmails'],
        ['checkIfPosterNeedsToBeGenerated'],
        ['generateCollectionSourceCode'],

      ],
      '&hook_civicrm_post' => [
        ['maybeGeneratePoster', 20],
        ['handleAuthorizationEmailsPost', 10],
        ['updateMonetaryContributionTotalAmount'],
        ['updateMatrialContributorsCount'],
        ['updateMonetaryContributorsCount'],
      ],
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
    ];
  }

  /**
   *
   */
  public static function updateUniqueContributorsCount(int $eventId, string $activeFieldName, int $currentContactId) {
    $materialActivities = Activity::get(FALSE)
      ->addSelect('source_contact_id')
      ->addWhere($activeFieldName, '=', $eventId)
      ->execute();

    $materialContactIds = [];
    foreach ($materialActivities as $activity) {
      if (!empty($activity['source_contact_id'])) {
        $materialContactIds[] = (int) $activity['source_contact_id'];
      }
    }

    $monetaryContributions = Contribution::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('Contribution_Details.Source.id', '=', $eventId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute();

    $monetaryContactIds = [];
    foreach ($monetaryContributions as $contribution) {
      if (!empty($contribution['contact_id'])) {
        $monetaryContactIds[] = (int) $contribution['contact_id'];
      }
    }

    $allContactIds = array_unique(array_merge($materialContactIds, $monetaryContactIds));
    $uniqueCount = count($allContactIds);

    $inMaterial = in_array($currentContactId, $materialContactIds, TRUE);
    $inMoney    = in_array($currentContactId, $monetaryContactIds, TRUE);

    if (!$inMaterial && !$inMoney) {
      $uniqueCount++;
    }

    try {
      EckEntity::update('Collection_Camp', TRUE)
        ->addValue('Core_Contribution_Details.Number_of_unique_contributors', $uniqueCount)
        ->addWhere('id', '=', $eventId)
        ->execute();
    }
    catch (\Exception $e) {
    }
  }

  /**
   *
   */
  public static function updateMatrialContributorsCount(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'AfformSubmission') {
      return;
    }

    $dataRaw = $objectRef->data ?? NULL;
    if (!$dataRaw) {
      return;
    }

    $dataDecoded = json_decode($dataRaw, TRUE);

    if (!is_array($dataDecoded)) {
      return;
    }
    $fields = $dataDecoded['Activity1'][0]['fields'] ?? [];
    if (!$fields) {
      return;
    }

    $fieldMapping = [
      'Material_Contribution.Collection_Camp' => 'Collection_Camp',
      'Material_Contribution.Institution_Collection_Camp' => 'Institution_Collection_Camp',
      'Material_Contribution.Dropping_Centre' => 'Dropping_Centre',
      'Material_Contribution.Institution_Dropping_Center' => 'Institution_Dropping_Center',
    ];

    $activeFieldName = NULL;
    $activeEntityId = NULL;
    $currentContactId = $fields['source_contact_id'] ?? NULL;

    foreach ($fieldMapping as $fieldKey => $entityType) {
      if (!empty($fields[$fieldKey])) {
        $activeFieldName = $fieldKey;
        $activeEntityId = $fields[$fieldKey];
        break;
      }
    }

    if(!$activeEntityId || !$currentContactId) {
      return;
    }

    self::updateUniqueContributorsCount($activeEntityId, $activeFieldName, $currentContactId);

    if (!$activeFieldName || !$activeEntityId || !$currentContactId) {
      return;
    }

    $activities = Activity::get(FALSE)
      ->addSelect('source_contact_id')
      ->addWhere($activeFieldName, '=', $activeEntityId)
      ->execute();

    $uniqueContactIds = [];
    foreach ($activities as $activity) {
      if (!empty($activity['source_contact_id'])) {
        $uniqueContactIds[$activity['source_contact_id']] = TRUE;
      }
    }

    if (isset($uniqueContactIds[$currentContactId])) {
      return;
    }

    $uniqueContactIds[$currentContactId] = TRUE;
    $uniqueCount = count($uniqueContactIds);

    $entityType = $fieldMapping[$activeFieldName] ?? NULL;
    if (!$entityType) {
      return;
    }

    try {
      EckEntity::update($entityType, FALSE)
        ->addValue('Core_Contribution_Details.Number_of_unique_material_contributors', $uniqueCount)
        ->addWhere('id', '=', $activeEntityId)
        ->execute();
    }
    catch (Exception $e) {
      error_log("Failed to update entity: " . $e->getMessage());
    }
  }

  /**
   *
   */
  public static function updateMonetaryContributorsCount(string $op, string $objectName, $objectId, &$objectRef) {
    static $processed = [];

    if ($objectName !== 'Contribution' || empty($objectRef->id)) {
      return;
    }

    $contributionId = $objectRef->id;
    if (isset($processed[$contributionId])) {
      return;
    }
    $processed[$contributionId] = TRUE;

    $contribution = Contribution::get(FALSE)
      ->addSelect('contact_id', 'Contribution_Details.Source.id')
      ->addWhere('id', '=', $contributionId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute()
      ->first();

    if (!$contribution || empty($contribution['Contribution_Details.Source.id'])) {
      return;
    }

    $eventId = $contribution['Contribution_Details.Source.id'];

    $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('subtype:name')
      ->addWhere('id', '=', $eventId)
      ->execute()
      ->first();

    $subtype = $collectionCamps['subtype:name'] ?? NULL;

    if ($subtype) {
      $fieldName = "Material_Contribution.$subtype";
      self::updateUniqueContributorsCount($eventId, $fieldName, $contribution['contact_id']);
    }

    $allContributions = Contribution::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('Contribution_Details.Source.id', '=', $eventId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute();

    if (!$allContributions) {
      return;
    }

    $contactIds = array_column(iterator_to_array($allContributions), 'contact_id');
    $uniqueContactIds = array_unique($contactIds);
    $newCount = count($uniqueContactIds);

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Core_Contribution_Details.Number_of_unique_monetary_contributors', $newCount)
      ->addWhere('id', '=', $eventId)
      ->execute();

  }

  /**
   *
   */
  public static function updateMonetaryContributionTotalAmount(string $op, string $objectName, $objectId, &$objectRef) {
    static $processed = [];

    if ($objectName !== 'Contribution' || empty($objectRef->id)) {
      return;
    }

    $contributionId = $objectRef->id;
    if (isset($processed[$contributionId])) {
      return;
    }
    $processed[$contributionId] = TRUE;

    $contribution = Contribution::get(FALSE)
      ->addSelect(
        'contribution_status_id:name',
        'total_amount',
        'Contribution_Details.Source.id',
        'payment_instrument_id:name'
      )
      ->addWhere('id', '=', $contributionId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute()
      ->first();

    if (!$contribution || empty($contribution['Contribution_Details.Source.id'])) {
      return;
    }

    $campId = $contribution['Contribution_Details.Source.id'];
    $totalAmount = (float) $contribution['total_amount'];
    $paymentMethod = $contribution['payment_instrument_id:name'];

    $existing = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect(
        'Core_Contribution_Details.Total_online_monetary_contributions',
        'Core_Contribution_Details.Total_cash_cheque_monetary_contributions'
      )
      ->addWhere('id', '=', $campId)
      ->execute()
      ->first();

    $onlineCurrent = (float) ($existing['Core_Contribution_Details.Total_online_monetary_contributions'] ?? 0);
    $cashCurrent = (float) ($existing['Core_Contribution_Details.Total_cash_cheque_monetary_contributions'] ?? 0);

    $update = EckEntity::update('Collection_Camp', FALSE)
      ->addWhere('id', '=', $campId);

    if ($paymentMethod === 'Credit Card') {
      $onlineNew = $onlineCurrent + $totalAmount;
      $update->addValue('Core_Contribution_Details.Total_online_monetary_contributions', $onlineNew);
    }

    if (in_array($paymentMethod, ['Cash', 'Check'], TRUE)) {
      $cashNew = $cashCurrent + $totalAmount;
      $update->addValue('Core_Contribution_Details.Total_cash_cheque_monetary_contributions', $cashNew);
    }

    $update->execute();
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
        ->addSelect('id')
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
    $entityTable = $result['entity_table'];

    if (empty($attachment['path'])) {
      return;
    }

    $columnName = 'poster_' . $posterField['id'];
    $fileIdToUpdate = NULL;

    $checkQuery = "
      SELECT `$columnName` AS poster_value
      FROM `$entityTable`
      WHERE entity_id = %1
    ";
    $dao = \CRM_Core_DAO::executeQuery($checkQuery, [
      1 => [$collectionSourceId, 'Integer'],
    ]);

    if ($dao->fetch()) {
      if (empty($dao->poster_value)) {

        $updateQuery = "
          UPDATE `$entityTable`
          SET $columnName = %1
          WHERE entity_id = %2
        ";
        \CRM_Core_DAO::executeQuery($updateQuery, [
          1 => [$result['id'], 'Integer'],
          2 => [$collectionSourceId, 'Integer'],
        ]);
      }
      else {
        $fileIdToUpdate = (int) $dao->poster_value;
        $attachment1 = $result['values'][$result['id']] ?? NULL;
        if (!empty($attachment1['path'])) {
          $fileName1 = basename($attachment1['path']);

          File::update(FALSE)
            ->addValue('uri', $fileName1)
            ->addWhere('id', '=', $fileIdToUpdate)
            ->execute();
        }
      }
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
    if (!in_array($entity, ['Eck_Collection_Camp', 'Eck_Institution_Visit'])) {
      return FALSE;
    }

    $restrictedRoles = ['admin', 'urban_ops_admin', 'ho_account', 'project_team_ho', 's2s_ho_team', 'njpc_ho_team'];

    $hasRestrictedRole = \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      return;
    }

    try {
      $teamGroupContacts = GroupContact::get(FALSE)
        ->addSelect('group_id')
        ->addWhere('contact_id', '=', $userId)
        ->addWhere('status', '=', 'Added')
        ->addWhere('group_id.Chapter_Contact_Group.Use_Case', '=', 'chapter-team')
        ->execute();

      $teamGroupContact = $teamGroupContacts->first();

      if (!$teamGroupContact) {
        // @todo we should handle it in a better way.
        // if there is no chapter assigned to the contact
        // then ideally she should not see any collection camp which
        // can be done but then it limits for the admin user as well.
        return FALSE;
      }

      $groupId = $teamGroupContact['group_id'];

      $chapterGroups = Group::get(FALSE)
        ->addSelect('Chapter_Contact_Group.States_controlled')
        ->addWhere('id', '=', $groupId)
        ->execute();

      $group = $chapterGroups->first();
      $statesControlled = $group['Chapter_Contact_Group.States_controlled'];

      if (empty($statesControlled)) {
        // Handle the case when the group is not controlling any state.
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      $statesControlled = array_unique($statesControlled);
      $statesList = implode(',', array_map('intval', $statesControlled));

      $stateFields = self::getStateFieldDbDetails($entity);

      $clausesArray = [];
      foreach ($stateFields as $stateField) {
        $selectQueries[] = sprintf(
            'SELECT entity_id FROM `%1$s` WHERE `%2$s` IN (%3$s)',
            $stateField['tableName'],
            $stateField['columnName'],
            $statesList,
        );
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
    if (!in_array($entity, ['Eck_Collection_Camp', 'Eck_Institution_Visit'])) {
      return FALSE;
    }

    if ($entity == 'Eck_Institution_Visit') {
      $stateGroupNameMapper = self::getStateGroupNameMapperForUrbanVisit();
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

}
