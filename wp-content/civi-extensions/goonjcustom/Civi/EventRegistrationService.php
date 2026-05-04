<?php

namespace Civi;

use Civi\Api4\Email;
use Civi\Api4\Participant;
use Civi\Core\Service\AutoSubscriber;

/**
 * Behavior for the Goonj Event Registration Afform
 * (afformGoonjEventRegistration).
 *
 * Two responsibilities:
 *
 * 1. Contact matching — Afform's `dedupe-rules` attribute only drives prefill
 *    lookup, not save-time matching. Without this service every submission
 *    would create a fresh Contact row and the duplicate-Participant check
 *    below would never fire. We match by primary email and set the id so
 *    Afform updates the existing Contact instead of creating a new one.
 *
 * 2. Duplicate-Participant skip — CiviCRM has no UNIQUE(contact_id, event_id)
 *    constraint. The hook_civicrm_pre here throws on a duplicate (contact,
 *    event) pair; Afform's Submit catches the exception per-entity and
 *    silently drops that Participant from the results. The client-side
 *    directive inspects the response: if every Participant was dropped it
 *    redirects to the duplicate page; otherwise the Afform's own redirect
 *    (success page) fires.
 */
class EventRegistrationService extends AutoSubscriber {

  const AFFORM_NAME = 'afformGoonjEventRegistration';

  /**
   * Tracks (contact_id:event_id) pairs seen within a single request so we
   * also catch in-batch duplicates (user clicks "Add another event" and
   * picks the same event twice).
   */
  private static array $seenPairsThisRequest = [];

  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => 'onParticipantPre',
      'civi.afform.submit' => ['onAfformSubmit', 5],
    ];
  }

  /**
   * Match existing Contact by primary email before save and reset the
   * in-request dedupe tracker for each fresh submission.
   */
  public static function onAfformSubmit(\Civi\Afform\Event\AfformSubmitEvent $event): void {
    $afform = $event->getAfform();
    if (($afform['name'] ?? NULL) !== self::AFFORM_NAME) {
      return;
    }
    if ($event->getEntityName() !== 'Individual1') {
      return;
    }

    self::$seenPairsThisRequest = [];
    $records =& $event->records;
    foreach ($records as &$record) {
      $email = trim($record['fields']['email_primary.email'] ?? '');
      if (empty($record['fields']['id']) && $email) {
        $match = Email::get(FALSE)
          ->addWhere('email', '=', $email)
          ->addWhere('is_primary', '=', TRUE)
          ->addWhere('contact_id.is_deleted', '=', FALSE)
          ->addOrderBy('contact_id', 'ASC')
          ->addSelect('contact_id')
          ->setLimit(1)
          ->execute()
          ->first();
        if (!empty($match['contact_id'])) {
          $record['fields']['id'] = $match['contact_id'];
        }
      }
    }
  }

  /**
   * Block duplicate Participant inserts. Applies to every Participant create
   * path (Afform, admin UI, API) — defense in depth beyond the Afform-only
   * logic above. Afform's Submit catches the exception and drops just that
   * Participant; non-Afform paths will see the exception as usual.
   */
  public static function onParticipantPre($op, $objectName, $id, &$params): void {
    if ($objectName !== 'Participant' || $op !== 'create') {
      return;
    }
    $contactId = (int) ($params['contact_id'] ?? 0);
    $eventId = (int) ($params['event_id'] ?? 0);
    if (!$contactId || !$eventId) {
      return;
    }

    $pairKey = $contactId . ':' . $eventId;
    if (isset(self::$seenPairsThisRequest[$pairKey])) {
      throw new \CRM_Core_Exception('duplicate_participant_in_batch');
    }

    $existing = Participant::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('event_id', '=', $eventId)
      ->addWhere('status_id:name', 'NOT IN', ['Cancelled', 'Rejected'])
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    if ($existing) {
      throw new \CRM_Core_Exception('duplicate_participant_exists');
    }

    self::$seenPairsThisRequest[$pairKey] = TRUE;
  }

}
