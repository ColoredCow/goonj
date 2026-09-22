<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Core\Service\AutoSubscriber;

/**
 * Stamps the DPDP consent date on the contact.
 *
 * Consent is recorded as a checkbox the person ticks, but what an audit asks
 * for is when they gave it. Nothing on the public forms carries that date —
 * only the tick — so it is filled in here the first time consent is seen.
 *
 * The date is written once and never rewritten. A returning contributor ticks
 * the box again on every contribution, and the contribution pages update the
 * contact they matched, so anything that simply saved "now" would walk the
 * date forward and destroy the only record of when consent was first given.
 * It also leaves the back end alone: where the team types a date next to the
 * proof they attach, that is the real date and this must not replace it.
 */
class DpdpConsentService extends AutoSubscriber {

  const CUSTOM_GROUP_NAME = 'DPDP_Consent';
  const FIELD_CONSENT_GIVEN = 'Consent_Given';
  const FIELD_CONSENT_DATE = 'Consent_Date';
  const FIELD_AGE_DECLARED = 'Age_18_Declared';

  /**
   * The camp record's own core fields.
   *
   * The individual intent forms do not hold the person who submits them — the
   * people listed on those forms are the other volunteers, and the submitter is
   * only referenced by `Contact_Id` on the camp record. So consent is ticked on
   * the camp and copied onto that contact here.
   *
   * This group carries no subtype restriction, so the one field serves every
   * camp subtype — collection camp, dropping centre and Goonj activities alike.
   */
  const CAMP_GROUP_NAME = 'Collection_Camp_Core_Details';
  const CAMP_FIELD_CONSENT_GIVEN = 'Consent_Given';
  const CAMP_FIELD_INITIATOR = 'Contact_Id';

  /**
   * The material contribution activity's own fields.
   *
   * These forms hold no person at all — only the activity — and the contributor
   * arrives as the activity's core `source_contact_id`. So, as with the camps,
   * consent is ticked on the record and copied onto that contact here.
   *
   * All five material contribution forms share this one group, so a single
   * field serves the lot.
   */
  const ACTIVITY_GROUP_NAME = 'Material_Contribution';
  const ACTIVITY_FIELD_CONSENT_GIVEN = 'Consent_Given';

  /**
   * Stops the Contact::update() below from re-entering this hook.
   *
   * @var bool
   */
  private static $stamping = FALSE;

  /**
   * Cached field metadata, keyed by field name.
   *
   * @var array|null
   */
  private static $fields = NULL;

  /**
   * Cached field metadata for the entity groups, keyed by group then field name.
   *
   * @var array
   */
  private static $groupFields = [];

  /**
   * Whether a tick on this request may be read as an age declaration too.
   *
   * The wording beside the checkbox differs by surface. On the public forms it
   * covers both being over 18 and consenting, so one tick asserts both and the
   * second field is filled from it.
   *
   * Two surfaces must not infer anything:
   *
   * - The monetary pages, where Goonj decided the checkbox is consent alone. A
   *   minor's contribution would need guardian consent and proof of the
   *   relationship, which is out of scope for now.
   * - The back-office contact screen, where the team sees both checkboxes and
   *   sets each one itself. Inferring there would overrule what the staff
   *   member chose and put a declaration on the record that the person never
   *   made — for a signed register entry, the paper may say nothing about age.
   *
   * The custom hook is handed no clue about which form it is serving, so the
   * surface is recorded while the form is being built and read back later.
   *
   * @var bool
   */
  private static $mayInferAge = TRUE;

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_buildForm' => [
        ['noteConsentOnlyForm'],
      ],
      '&hook_civicrm_custom' => [
        ['stampConsentDate'],
        ['copyCampConsentToInitiator'],
        ['copyActivityConsentToContributor'],
      ],
    ];
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * Records that this request is a surface where a tick means consent and
   * nothing else, so the consent written later is not read as an age
   * declaration.
   *
   * @param string $formName
   *   The form being built.
   * @param object $form
   *   The form object.
   */
  public static function noteConsentOnlyForm($formName, &$form) {
    $consentOnly = [
      // The public monetary pages.
      'CRM_Contribute_Form_Contribution_',
      // Adding or editing a contact in the back office — the full form and the
      // inline edit on the custom field block are different classes, and both
      // show the two checkboxes separately.
      'CRM_Contact_Form_',
    ];

    foreach ($consentOnly as $prefix) {
      if (strpos($formName, $prefix) === 0) {
        self::$mayInferAge = FALSE;
        return;
      }
    }
  }

  /**
   * Implements hook_civicrm_custom().
   *
   * Moves the consent ticked on a camp record onto the person who submitted it.
   *
   * Writing it to the contact is what matters — consent belongs to the person,
   * not to the camp they happened to create. The camp keeps its own tick as the
   * record of which submission the consent arrived on, which is what an audit
   * asks for.
   *
   * Only the submitter is touched. The other people named on these forms are
   * entered by the submitter, and one person cannot consent for another; they
   * are covered by the declaration the submitter makes instead.
   *
   * @param string $op
   *   The operation being performed.
   * @param int $groupID
   *   The custom group being written.
   * @param int $entityID
   *   The camp record the values belong to.
   * @param array $params
   *   The custom values written.
   */
  public static function copyCampConsentToInitiator($op, $groupID, $entityID, &$params) {
    if (!in_array($op, ['create', 'edit'], TRUE) || !$entityID) {
      return;
    }

    $campFields = self::getGroupFields(self::CAMP_GROUP_NAME, [self::CAMP_FIELD_CONSENT_GIVEN, self::CAMP_FIELD_INITIATOR]);
    $consent = $campFields[self::CAMP_FIELD_CONSENT_GIVEN] ?? NULL;
    $initiator = $campFields[self::CAMP_FIELD_INITIATOR] ?? NULL;
    if (!$consent || !$initiator || (int) $groupID !== (int) $consent['custom_group_id']) {
      return;
    }

    if (!self::isConsentTicked($params, (int) $consent['id'])) {
      return;
    }

    // The initiator is usually written in this same save, but a camp edited
    // later will already have it on the record instead.
    $contactId = self::findValue($params, (int) $initiator['id']);
    if (!$contactId) {
      $contactId = self::getStoredInitiator($entityID);
    }

    // Around a third of camp records carry no initiator at all — the ones
    // created from the back office. There is nobody to record consent against,
    // so leave it on the camp and stop.
    if (!$contactId) {
      return;
    }

    try {
      Contact::update(FALSE)
        ->addWhere('id', '=', (int) $contactId)
        ->addValue(self::CUSTOM_GROUP_NAME . '.' . self::FIELD_CONSENT_GIVEN, ['1'])
        ->execute();
    }
    catch (\Throwable $e) {
      \Civi::log()->error('[DPDP] Could not copy camp consent to the initiator', [
        'campId' => $entityID,
        'contactId' => $contactId,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Implements hook_civicrm_custom().
   *
   * Moves the consent ticked on a material contribution onto the contributor.
   *
   * These forms carry no person record at all, only the activity — the
   * contributor was identified at the check-user step and arrives as the
   * activity's source contact. That is the person the consent belongs to.
   *
   * @param string $op
   *   The operation being performed.
   * @param int $groupID
   *   The custom group being written.
   * @param int $entityID
   *   The activity the values belong to.
   * @param array $params
   *   The custom values written.
   */
  public static function copyActivityConsentToContributor($op, $groupID, $entityID, &$params) {
    if (!in_array($op, ['create', 'edit'], TRUE) || !$entityID) {
      return;
    }

    $fields = self::getGroupFields(self::ACTIVITY_GROUP_NAME, [self::ACTIVITY_FIELD_CONSENT_GIVEN]);
    $consent = $fields[self::ACTIVITY_FIELD_CONSENT_GIVEN] ?? NULL;
    if (!$consent || (int) $groupID !== (int) $consent['custom_group_id']) {
      return;
    }

    if (!self::isConsentTicked($params, (int) $consent['id'])) {
      return;
    }

    $contactId = self::getActivitySourceContact($entityID);
    if (!$contactId) {
      return;
    }

    try {
      Contact::update(FALSE)
        ->addWhere('id', '=', (int) $contactId)
        ->addValue(self::CUSTOM_GROUP_NAME . '.' . self::FIELD_CONSENT_GIVEN, ['1'])
        ->execute();
    }
    catch (\Throwable $e) {
      \Civi::log()->error('[DPDP] Could not copy contribution consent to the contributor', [
        'activityId' => $entityID,
        'contactId' => $contactId,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * The contact recorded as the source of an activity.
   *
   * @param int $entityID
   *   The activity.
   *
   * @return int|null
   *   The contact id, or NULL when the activity has no source contact.
   */
  private static function getActivitySourceContact($entityID) {
    try {
      $activity = Activity::get(FALSE)
        ->addSelect('source_contact_id')
        ->addWhere('id', '=', $entityID)
        ->execute()
        ->first();

      return $activity['source_contact_id'] ?? NULL;
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * The initiator already stored against a camp record.
   *
   * @param int $entityID
   *   The camp record.
   *
   * @return int|null
   *   The contact id, or NULL when the camp has no initiator.
   */
  private static function getStoredInitiator($entityID) {
    try {
      $camp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect(self::CAMP_GROUP_NAME . '.' . self::CAMP_FIELD_INITIATOR)
        ->addWhere('id', '=', $entityID)
        ->execute()
        ->first();

      return $camp[self::CAMP_GROUP_NAME . '.' . self::CAMP_FIELD_INITIATOR] ?? NULL;
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * Fields of a custom group, looked up by name and cached per group.
   *
   * Field ids are generated per environment, so everything here resolves from
   * the names — which are the same everywhere — rather than hard-coded ids.
   *
   * @param string $groupName
   *   The custom group.
   * @param array $fieldNames
   *   The fields wanted from it.
   *
   * @return array
   *   Field metadata keyed by field name, empty when the group is absent.
   */
  private static function getGroupFields(string $groupName, array $fieldNames): array {
    if (isset(self::$groupFields[$groupName])) {
      return self::$groupFields[$groupName];
    }

    try {
      $fields = CustomField::get(FALSE)
        ->addSelect('id', 'name', 'custom_group_id')
        ->addWhere('custom_group_id:name', '=', $groupName)
        ->addWhere('name', 'IN', $fieldNames)
        ->execute();

      self::$groupFields[$groupName] = [];
      foreach ($fields as $field) {
        self::$groupFields[$groupName][$field['name']] = $field;
      }
    }
    catch (\Throwable $e) {
      // These groups are created by hand on each environment, so one may
      // legitimately not exist yet. Stay quiet rather than erroring on every save.
      self::$groupFields[$groupName] = [];
    }

    return self::$groupFields[$groupName];
  }

  /**
   * Implements hook_civicrm_custom().
   *
   * Custom field values are written outside the contact's own save, so this is
   * the point at which the ticked consent is actually on the record. Reading it
   * from a contact pre/post hook instead would see the contact without it.
   *
   * @param string $op
   *   The operation being performed.
   * @param int $groupID
   *   The custom group being written.
   * @param int $entityID
   *   The contact the values belong to.
   * @param array $params
   *   The custom values written.
   */
  public static function stampConsentDate($op, $groupID, $entityID, &$params) {
    if (self::$stamping || !in_array($op, ['create', 'edit'], TRUE) || !$entityID) {
      return;
    }

    $fields = self::getFields();
    if (!$fields) {
      return;
    }

    $consentGiven = $fields[self::FIELD_CONSENT_GIVEN] ?? NULL;
    $consentDate = $fields[self::FIELD_CONSENT_DATE] ?? NULL;
    if (!$consentGiven || !$consentDate || (int) $groupID !== (int) $consentGiven['custom_group_id']) {
      return;
    }

    if (!self::isConsentTicked($params, (int) $consentGiven['id'])) {
      return;
    }

    $ageDeclared = $fields[self::FIELD_AGE_DECLARED] ?? NULL;
    $groupPrefix = self::CUSTOM_GROUP_NAME . '.';

    try {
      $existing = Contact::get(FALSE)
        ->addSelect(
          'contact_type',
          $groupPrefix . self::FIELD_CONSENT_DATE,
          $groupPrefix . self::FIELD_AGE_DECLARED
        )
        ->addWhere('id', '=', $entityID)
        ->execute()
        ->first();

      $values = [];

      // A date supplied in this same write is the team filling in a back-dated
      // register entry, and a date already on the record is the first consent.
      // Either way theirs is the accurate one, so only an empty date is filled.
      if (!self::hasValueFor($params, (int) $consentDate['id'])
        && empty($existing[$groupPrefix . self::FIELD_CONSENT_DATE])) {
        $values[$groupPrefix . self::FIELD_CONSENT_DATE] = date('Y-m-d');
      }

      // On the public forms the single checkbox is worded to cover both being
      // over 18 and consenting, so ticking it asserts both. They stay two
      // fields because they are two different facts to answer for in an audit,
      // and the second is filled here rather than asking twice.
      //
      // Where the tick is consent alone, no age declaration may be inferred
      // from it — recording one the person never made would be worse than
      // having none at all. An age declaration is also a statement only a
      // person can make, so it is never put on an organisation, which consents
      // through whoever signs for it.
      if ($ageDeclared
        && self::$mayInferAge
        && ($existing['contact_type'] ?? NULL) === 'Individual'
        && !self::hasValueFor($params, (int) $ageDeclared['id'])
        && empty($existing[$groupPrefix . self::FIELD_AGE_DECLARED])) {
        $values[$groupPrefix . self::FIELD_AGE_DECLARED] = ['1'];
      }

      if (!$values) {
        return;
      }

      self::$stamping = TRUE;
      $update = Contact::update(FALSE)->addWhere('id', '=', $entityID);
      foreach ($values as $field => $value) {
        $update->addValue($field, $value);
      }
      $update->execute();
    }
    catch (\Throwable $e) {
      // A contribution must never fail because we could not write these.
      // The tick is already saved; log it and let the payment finish.
      \Civi::log()->error('[DPDP] Could not complete the consent record', [
        'contactId' => $entityID,
        'message' => $e->getMessage(),
      ]);
    }
    finally {
      self::$stamping = FALSE;
    }
  }

  /**
   * Whether the consent checkbox was ticked in this write.
   *
   * CiviCRM hands checkbox values over in more than one shape depending on
   * where the save came from — an array of ticked options from a profile, or
   * the packed "\x011\x01" string it stores them as. An unticked box arrives
   * as an empty value rather than not arriving at all, so the emptiness is what
   * decides, not the presence of the key.
   *
   * @param array $params
   *   The custom values written.
   * @param int $fieldId
   *   The consent field's id.
   *
   * @return bool
   *   TRUE when consent was ticked.
   */
  private static function isConsentTicked(array $params, int $fieldId): bool {
    $value = self::findValue($params, $fieldId);
    if ($value === NULL) {
      return FALSE;
    }
    if (is_array($value)) {
      return !empty(array_filter($value));
    }

    // Serialised checkboxes arrive wrapped in CiviCRM's \x01 separators, and an
    // unticked box arrives as an empty string rather than being left out — so
    // it is the emptiness that decides, not whether the key is present.
    return trim((string) $value, " \t\n\r\0\x0B\x01") !== '';
  }

  /**
   * Whether this write already carries a value for the given field.
   *
   * @param array $params
   *   The custom values written.
   * @param int $fieldId
   *   The field's id.
   *
   * @return bool
   *   TRUE when a non-empty value is present.
   */
  private static function hasValueFor(array $params, int $fieldId): bool {
    return !empty(self::findValue($params, $fieldId));
  }

  /**
   * The value written for a field, or NULL when the field is not in this write.
   *
   * CiviCRM hands this hook a flat list of field entries, each carrying its own
   * custom_field_id. Older code paths nest them a level deeper under the group,
   * so both shapes are walked rather than assuming one.
   *
   * @param array $params
   *   The custom values written.
   * @param int $fieldId
   *   The field's id.
   *
   * @return mixed
   *   The value written, or NULL when the field is absent.
   */
  private static function findValue(array $params, int $fieldId) {
    foreach ($params as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      if (array_key_exists('custom_field_id', $entry)) {
        if ((int) $entry['custom_field_id'] === $fieldId) {
          return $entry['value'] ?? NULL;
        }
        continue;
      }
      $nested = self::findValue($entry, $fieldId);
      if ($nested !== NULL) {
        return $nested;
      }
    }

    return NULL;
  }

  /**
   * The consent fields, looked up by name.
   *
   * Field ids are generated per environment, so they are resolved from the
   * names — which are the same everywhere — rather than hard-coded.
   *
   * @return array
   *   Field metadata keyed by field name, empty when the group is absent.
   */
  private static function getFields(): array {
    if (self::$fields !== NULL) {
      return self::$fields;
    }

    try {
      $fields = CustomField::get(FALSE)
        ->addSelect('id', 'name', 'custom_group_id')
        ->addWhere('custom_group_id:name', '=', self::CUSTOM_GROUP_NAME)
        ->addWhere('name', 'IN', [
          self::FIELD_CONSENT_GIVEN,
          self::FIELD_CONSENT_DATE,
          self::FIELD_AGE_DECLARED,
        ])
        ->execute();

      self::$fields = [];
      foreach ($fields as $field) {
        self::$fields[$field['name']] = $field;
      }
    }
    catch (\Throwable $e) {
      // The group is created by hand on each environment, so it may legitimately
      // not exist yet. Stay quiet rather than erroring on every custom save.
      self::$fields = [];
    }

    return self::$fields;
  }

}
