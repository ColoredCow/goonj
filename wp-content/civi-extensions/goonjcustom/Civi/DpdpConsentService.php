<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
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
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_custom' => [
        ['stampConsentDate'],
      ],
    ];
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

    // A date already in this same write is the team filling in a back-dated
    // register entry. Theirs is the accurate one, so leave it alone.
    if (self::hasValueFor($params, (int) $consentDate['id'])) {
      return;
    }

    try {
      $existing = Contact::get(FALSE)
        ->addSelect(self::CUSTOM_GROUP_NAME . '.' . self::FIELD_CONSENT_DATE)
        ->addWhere('id', '=', $entityID)
        ->execute()
        ->first();

      if (!empty($existing[self::CUSTOM_GROUP_NAME . '.' . self::FIELD_CONSENT_DATE])) {
        return;
      }

      self::$stamping = TRUE;
      Contact::update(FALSE)
        ->addWhere('id', '=', $entityID)
        ->addValue(self::CUSTOM_GROUP_NAME . '.' . self::FIELD_CONSENT_DATE, date('Y-m-d'))
        ->execute();
    }
    catch (\Throwable $e) {
      // A contribution must never fail because we could not write the date.
      // The tick is already saved; log it and let the payment finish.
      \Civi::log()->error('[DPDP] Could not stamp consent date', [
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
        ->addWhere('name', 'IN', [self::FIELD_CONSENT_GIVEN, self::FIELD_CONSENT_DATE])
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
