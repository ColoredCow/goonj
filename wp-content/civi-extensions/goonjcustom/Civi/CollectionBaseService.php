<?php

namespace Civi;

use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\File;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class CollectionBaseService extends AutoSubscriber {

  const ENTITY_NAME = 'Collection_Camp';
  const INTENT_CUSTOM_GROUP_NAME = 'Collection_Camp_Intent_Details';

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
      '&hook_civicrm_tabset' => 'collectionBaseTabset',
      '&hook_civicrm_selectWhereClause' => 'aclCollectionCamp',
      '&hook_civicrm_pre' => [
        ['handleAuthorizationEmails'],
        ['checkIfPosterNeedsToBeGenerated'],
      ],
      '&hook_civicrm_post' => [
        ['maybeGeneratePoster', 20],
        ['handleAuthorizationEmailsPost', 10],
      ],
    ];
  }

  /**
   *
   */
  public static function checkIfPosterNeedsToBeGenerated($op, $objectName, $id, &$params) {
    if ($objectName !== 'Eck_Collection_Camp' || $op !== 'edit') {
      return;
    }

    if (!($messageTemplateId = $params['Collection_Camp_Core_Details.Poster_Template'])) {
      return;
    }

    $currentCollectionSource = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('custom.*')
      ->addWhere('id', '=', $id)
      ->execute()->single();

    $posterExists = !is_null($currentCollectionSource['Collection_Camp_Core_Details.Poster']);

    if ($posterExists) {
      return;
    }

    self::$generatePosterRequest = [
      'collectionSourceId' => $currentCollectionSource['id'],
      'messageTemplateId' => $messageTemplateId,
      'customData' => $params,
    ];

  }

  /**
   *
   */
  public static function maybeGeneratePoster(string $op, string $objectName, int $objectId, &$objectRef) {
    if (!self::$generatePosterRequest || $objectName !== 'Eck_Collection_Camp' || $op !== 'edit') {
      return;
    }

    $collectionSourceId = self::$generatePosterRequest['collectionSourceId'];
    $messageTemplateId = self::$generatePosterRequest['messageTemplateId'];

    $messageTemplate = MessageTemplate::get(FALSE)
      ->addWhere('id', '=', $messageTemplateId)
      ->execute()->single();
    $html = $messageTemplate['msg_html'];

    // Regular expression to find <style>...</style> and replace it with {literal}<style>...</style>{/literal}.
    $pattern = '/<style\b[^>]*>(.*?)<\/style>/is';
    $replacement = '{literal}<style>$1</style>{/literal}';

    // Perform the replacement.
    $modifiedHtml = preg_replace($pattern, $replacement, $html);

    $pattern = '/<script\b[^>]*>(.*?)<\/script>/is';
    $replacement = '{literal}<script>$1</script>{/literal}';

    $modifiedHtml = preg_replace($pattern, $replacement, $modifiedHtml);

    $rendered = \CRM_Core_TokenSmarty::render(
    ['html' => $modifiedHtml],
    [
      'collectionSourceId' => $collectionSourceId,
      'collectionSourceCustomData' => self::$generatePosterRequest['customData'],
    ],
    );

    $baseFileName = "poster_{$collectionSourceId}.png";
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
        ->execute()->single();
    }
    catch (\Exception $ex) {
      \CRM_Core_Error::debug_log_message('Cannot find field to save poster for collection camp ID ' . $collectionSourceId);
      return FALSE;
    }

    $posterFieldId = 'custom_' . $posterField['id'];

    // Save the poster image as an attachment linked to the collection camp.
    $params = [
      'entity_id' => $collectionSourceId,
      'name' => $fileName,
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
  }

  /**
   *
   */
  public static function html2image($htmlContent, $outputPath) {
    $nodePath = NODE_PATH;
    $puppeteerJsPath = escapeshellarg(\CRM_Goonjcustom_ExtensionUtil::path('/js/puppeteer.js'));
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
  public static function collectionBaseTabset($tabsetName, &$tabs, $context) {
    if ($tabsetName !== 'civicrm/eck/entity' || empty($context) || $context['entity_type']['name'] !== self::ENTITY_NAME) {
      return;
    }

    $tabConfigs = [
      'eventVolunteers' => [
        'title' => ts('Event Volunteers'),
        'module' => 'afsearchEventVolunteer',
        'directive' => 'afsearch-event-volunteer',
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchCollectionCampMaterialContributions',
        'directive' => 'afsearch-collection-camp-material-contributions',
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchCampVehicleDispatchData',
        'directive' => 'afsearch-camp-vehicle-dispatch-data',
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchAcknowledgementForLogisticsData',
        'directive' => 'afsearch-acknowledgement-for-logistics-data',
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'is_active' => 1,
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'module' => $config['module'],
        'directive' => $config['directive'],
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
  }

  /**
   *
   */
  public static function aclCollectionCamp($entity, &$clauses, $userId, $conditions) {
    if ($entity !== 'Eck_Collection_Camp') {
      return FALSE;
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

      $stateField = self::getStateFieldDbDetails();

      $clauseString = sprintf(
      'IN (SELECT entity_id FROM `%1$s` WHERE `%2$s` IN (%3$s))',
      $stateField['tableName'],
      $stateField['columnName'],
      $statesList,
      );

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
  private static function getStateFieldDbDetails() {
    if (empty(self::$stateCustomFieldDbDetails)) {
      $customField = CustomField::get(FALSE)
        ->addSelect('column_name', 'custom_group_id.table_name')
        ->addWhere('custom_group_id.name', '=', self::INTENT_CUSTOM_GROUP_NAME)
        ->addWhere('name', '=', 'state')
        ->execute()->single();

      self::$stateCustomFieldDbDetails = [
        'tableName' => $customField['custom_group_id.table_name'],
        'columnName' => $customField['column_name'],
      ];

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
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $initiatorId = $currentCollectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    if (!in_array($newStatus, ['authorized', 'unauthorized'])) {
      return;
    }

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      self::$collectionAuthorized = $objectId;
      self::$collectionAuthorizedStatus = $newStatus;
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
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'subtype')
      ->addWhere('id', '=', $objectRef->id)
      ->execute()->single();

    $initiator = $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];
    $subtype = $collectionCamp['subtype'];

    $collectionSourceId = $collectionCamp['id'];

    if (!self::$authorizationEmailQueued) {
      self::queueAuthorizationEmail($initiator, $subtype, self::$collectionAuthorizedStatus, $collectionSourceId);
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

      $file = File::get(FALSE)
        ->addWhere('id', '=', $posterFileId)
        ->execute()->single();

      $config = \CRM_Core_Config::singleton();
      $filePath = $config->customFileUploadDir . $file['uri'];
      $emailParams['attachments'][] = [
        'fullPath' => $filePath,
        'mime_type' => $file['mime_type'],
        'cleanName' => $file['uri'],
      ];
    }

    return $emailParams;
  }

}
