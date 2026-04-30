<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;

/**
 * Handles subscription expiry reminder emails for recurring donations.
 *
 * Sends reminders at 30, 7, and 2 days before the calculated subscription
 * end date. Logs a CiviCRM activity after each send to prevent duplicates.
 *
 * Two independent pipelines are processed in a single run:
 *   1. Team 5000 recurs (contribution page = Team_5000)
 *   2. Generic recurring donations (everything else)
 */
class Team5000SubscriptionReminderService {

  const REMINDER_DAYS = [30, 7, 2];
  const CC_RECIPIENTS = 'priyanka@goonj.org, accounts@goonj.org';

  const TEAM_5000_PAGE_NAME = 'Team_5000';
  const TEAM_5000_ACTIVITY_TYPE = 'Team 5000 Subscription Reminder';
  const GENERIC_ACTIVITY_TYPE = 'Recurring Donation Reminder';

  /**
   * Entry point called by the cron. Runs both pipelines.
   *
   * Each pipeline is wrapped in its own try/catch so a failure in one
   * does not prevent the other from running.
   */
  public static function processReminders(\DateTimeImmutable $now, string $from): void {
    try {
      self::processTeam5000Pipeline($now, $from);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Team 5000: Pipeline failed', [
        'error' => $e->getMessage(),
      ]);
    }

    try {
      self::processGenericPipeline($now, $from);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Recurring: Pipeline failed', [
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Pipeline 1 — Team 5000.
   *
   * Finds all recurs whose first contribution is on the Team 5000
   * contribution page, then sends reminders for any that are due.
   */
  private static function processTeam5000Pipeline(\DateTimeImmutable $now, string $from): void {
    // Get unique recur IDs via the first contribution of each Team 5000 subscription.
    // The first contribution always has contribution_page_id = Team_5000.
    // Subsequent Razorpay payments have contribution_page_id = null, but we only
    // need one match per recur — after that we work directly on civicrm_contribution_recur.
    $contributions = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contribution_page_id:name', '=', self::TEAM_5000_PAGE_NAME)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->addWhere('is_test', '=', TRUE)
      ->execute();

    $team5000RecurIds = array_values(array_unique(array_column((array) $contributions, 'contribution_recur_id')));

    if (empty($team5000RecurIds)) {
      \Civi::log()->info('Team 5000: No recurring contributions found for contribution page: ' . self::TEAM_5000_PAGE_NAME);
      return;
    }

    $recurs = ContributionRecur::get(FALSE)
      ->addSelect('id', 'contact_id', 'start_date', 'installments', 'frequency_interval', 'frequency_unit', 'amount')
      ->addWhere('id', 'IN', $team5000RecurIds)
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->addWhere('is_test', '=', TRUE)
      ->execute();

    \Civi::log()->info('Team 5000: Active recurs to process: ' . $recurs->count());

    $config = [
      'is_team_5000' => TRUE,
      'activity_type_name' => self::TEAM_5000_ACTIVITY_TYPE,
      'pipeline_label' => 'Team 5000',
      'team_5000_recur_ids' => $team5000RecurIds,
    ];

    foreach ($recurs as $recur) {
      try {
        self::processSubscription($recur, $now, $from, $config);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Team 5000: Error processing reminder', [
          'recur_id' => $recur['id'],
          'error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Pipeline 2 — Generic recurring donations.
   *
   * Finds all active recurs that are NOT linked to the Team 5000 page,
   * then sends reminders for any that are due.
   */
  private static function processGenericPipeline(\DateTimeImmutable $now, string $from): void {
    $team5000RecurIds = self::getTeam5000RecurIds();

    $query = ContributionRecur::get(FALSE)
      ->addSelect('id', 'contact_id', 'start_date', 'installments', 'frequency_interval', 'frequency_unit', 'amount')
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->addWhere('installments', 'IS NOT NULL')
      ->addWhere('is_test', '=', TRUE);

    if (!empty($team5000RecurIds)) {
      $query->addWhere('id', 'NOT IN', $team5000RecurIds);
    }

    $recurs = $query->execute();

    \Civi::log()->info('Recurring: Active recurs to process: ' . $recurs->count());

    $config = [
      'is_team_5000' => FALSE,
      'activity_type_name' => self::GENERIC_ACTIVITY_TYPE,
      'pipeline_label' => 'Recurring',
      'team_5000_recur_ids' => $team5000RecurIds,
    ];

    foreach ($recurs as $recur) {
      try {
        self::processSubscription($recur, $now, $from, $config);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Recurring: Error processing reminder', [
          'recur_id' => $recur['id'],
          'error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Helper used by the generic pipeline to know which recurs to exclude.
   */
  private static function getTeam5000RecurIds(): array {
    $contributions = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contribution_page_id:name', '=', self::TEAM_5000_PAGE_NAME)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->addWhere('is_test', '=', TRUE)
      ->execute();

    return array_values(array_unique(array_column((array) $contributions, 'contribution_recur_id')));
  }

  /**
   * Checks if any reminder is due for the given subscription and sends it.
   */
  private static function processSubscription(array $recur, \DateTimeImmutable $now, string $from, array $config): void {
    if (empty($recur['start_date']) || empty($recur['installments'])) {
      \Civi::log()->info($config['pipeline_label'] . ': Skipping recur — missing start_date or installments', [
        'recur_id' => $recur['id'],
      ]);
      return;
    }

    $endDate = self::calculateEndDate(
      $recur['start_date'],
      (int) $recur['installments'],
      (int) $recur['frequency_interval'],
      $recur['frequency_unit']
    );

    $today = $now->format('Y-m-d');

    foreach (self::REMINDER_DAYS as $daysBefore) {
      $triggerDate = (new \DateTimeImmutable($endDate))->modify("-{$daysBefore} days")->format('Y-m-d');

      if ($today !== $triggerDate) {
        continue;
      }

      if (self::hasReminderBeenSent($recur['id'], $recur['contact_id'], $daysBefore, $config['activity_type_name'])) {
        \Civi::log()->info($config['pipeline_label'] . ': Reminder already sent, skipping', [
          'recur_id' => $recur['id'],
          'days_before' => $daysBefore,
        ]);
        continue;
      }

      // Skip if donor has started a new subscription within the same pipeline
      // after the first reminder was sent.
      if (self::hasRenewed($recur['contact_id'], $recur['id'], $config)) {
        \Civi::log()->info($config['pipeline_label'] . ': Donor renewed, skipping', [
          'recur_id' => $recur['id'],
          'days_before' => $daysBefore,
        ]);
        continue;
      }

      self::sendReminderAndLog($recur, $endDate, $daysBefore, $from, $config);
    }
  }

  /**
   * Calculates the subscription end date from recur fields.
   *
   * Formula: start_date + (installments * frequency_interval * frequency_unit)
   */
  public static function calculateEndDate(
    string $startDate,
    int $installments,
    int $frequencyInterval,
    string $frequencyUnit
  ): string {
    $start = new \DateTimeImmutable($startDate);
    $totalUnits = $installments * $frequencyInterval;

    $unitMap = [
      'day' => 'D',
      'week' => 'W',
      'month' => 'M',
      'year' => 'Y',
    ];

    $intervalUnit = $unitMap[$frequencyUnit] ?? 'M';
    $interval = new \DateInterval("P{$totalUnits}{$intervalUnit}");

    return $start->add($interval)->format('Y-m-d');
  }

  /**
   * Checks if a reminder for this recur and day window was already sent.
   */
  private static function hasReminderBeenSent(int $recurId, int $contactId, int $daysBefore, string $activityTypeName): bool {
    $existing = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('activity_type_id:name', '=', $activityTypeName)
      ->addWhere('target_contact_id', '=', $contactId)
      ->addWhere('subject', 'LIKE', '%recur_id:' . $recurId . '%')
      ->addWhere('subject', 'LIKE', '%- ' . $daysBefore . ' days%')
      ->execute()
      ->first();

    return !empty($existing);
  }

  /**
   * Checks if the donor has another active subscription within the same
   * pipeline (Team 5000 OR generic). If yes, no reminder is sent for the
   * expiring recur — the donor is already covered by the other one.
   */
  private static function hasRenewed(int $contactId, int $currentRecurId, array $config): bool {
    $query = ContributionRecur::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('id', '!=', $currentRecurId)
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->addWhere('is_test', '=', TRUE);

    if ($config['is_team_5000']) {
      $otherTeam5000Ids = array_values(array_diff($config['team_5000_recur_ids'], [$currentRecurId]));
      if (empty($otherTeam5000Ids)) {
        return FALSE;
      }
      $query->addWhere('id', 'IN', $otherTeam5000Ids);
    }
    else {
      if (!empty($config['team_5000_recur_ids'])) {
        $query->addWhere('id', 'NOT IN', $config['team_5000_recur_ids']);
      }
    }

    $newRecur = $query->execute()->first();
    return !empty($newRecur);
  }

  /**
   * Fetches contact details, sends the reminder email, and logs the activity.
   */
  private static function sendReminderAndLog(array $recur, string $endDate, int $daysBefore, string $from, array $config): void {
    $contactId = $recur['contact_id'];

    $contact = Contact::get(FALSE)
      ->addSelect('display_name', 'email.email')
      ->addJoin('Email AS email', 'LEFT', ['email.is_primary', '=', TRUE])
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    if (empty($contact['email.email'])) {
      \Civi::log()->info($config['pipeline_label'] . ': Skipping — no email for contact', [
        'contact_id' => $contactId,
        'recur_id' => $recur['id'],
        'days_before' => $daysBefore,
      ]);
      return;
    }

    $donorName = $contact['display_name'] ?? 'Valued Supporter';
    $donorEmail = $contact['email.email'];

    $mailParams = [
      'subject' => self::getEmailSubject($daysBefore, $config['is_team_5000']),
      'from' => $from,
      'toEmail' => $donorEmail,
      'toName' => $donorName,
      'replyTo' => $from,
      'cc' => self::CC_RECIPIENTS,
      'html' => $config['is_team_5000']
        ? self::getTeam5000EmailHtml($endDate)
        : self::getGenericEmailHtml($endDate),
    ];

    $emailResult = \CRM_Utils_Mail::send($mailParams);

    if (!$emailResult) {
      \Civi::log()->error($config['pipeline_label'] . ': Failed to send reminder email', [
        'contact_id' => $contactId,
        'email' => $donorEmail,
        'recur_id' => $recur['id'],
        'days_before' => $daysBefore,
      ]);
      return;
    }

    \Civi::log()->info($config['pipeline_label'] . ': Reminder email sent', [
      'recur_id' => $recur['id'],
      'email' => $donorEmail,
      'days_before' => $daysBefore,
    ]);

    self::createReminderActivity($contactId, $recur['id'], $daysBefore, $config);
  }

  /**
   * Creates a CiviCRM activity to record that the reminder was sent.
   *
   * Subject prefix matches the original Team 5000 format ("Subscription
   * Expiry Reminder") so existing activities and new ones stay consistent.
   * Generic pipeline uses "Recurring Donation Reminder" as the subject prefix.
   */
  private static function createReminderActivity(int $contactId, int $recurId, int $daysBefore, array $config): void {
    $subjectPrefix = $config['is_team_5000']
      ? 'Team 5000 Subscription Expiry Reminder'
      : 'Recurring Donation Reminder';

    Activity::create(FALSE)
      ->addValue('activity_type_id:name', $config['activity_type_name'])
      ->addValue('status_id:name', 'Completed')
      ->addValue('source_contact_id', $contactId)
      ->addValue('target_contact_id', $contactId)
      ->addValue('subject', $subjectPrefix . ' - ' . $daysBefore . ' days (recur_id:' . $recurId . ')')
      ->addValue('details', 'Automated reminder sent ' . $daysBefore . ' day(s) before subscription expiry.')
      ->execute();
  }

  /**
   * Returns the email subject line based on how many days remain.
   */
  private static function getEmailSubject(int $daysBefore, bool $isTeam5000): string {
    $labels = [
      30 => '1 Month',
      7  => '7 Days',
      2  => '2 Days',
    ];
    $label = $labels[$daysBefore] ?? "{$daysBefore} Days";
    $product = $isTeam5000 ? 'Team 5000 Membership' : 'Recurring Donation';
    return "Reminder: Your {$product} Expires in {$label}";
  }

  /**
   * Builds the HTML email body for Team 5000 reminders.
   *
   * Template content is a placeholder — final copy to be confirmed with the client.
   */
  private static function getTeam5000EmailHtml(string $endDate): string {
    $formattedDate = (new \DateTime($endDate))->format('F j, Y');

    return "
      <p>Greetings from Goonj!</p>
      <p>Just a gentle reminder that your Team 5000 membership is set to expire in a few days (on <strong>{$formattedDate}</strong>).</p>
      <p>We truly hope you'll continue this journey with us.</p>
      <p>Your contribution has been making a meaningful difference, helping us reach communities that need it most.</p>
      <p>We warmly invite you to renew your Team 5000 membership at your earliest convenience by clicking this link <a href='https://goonj.org/donate/campaign/team-5000-new'>https://goonj.org/donate/campaign/team-5000-new</a></p>
      <p>Kindly ignore if you have already renewed your contribution. If you have any questions or need assistance, please feel free to write to us at <a href='mailto:priyanka@goonj.org'>priyanka@goonj.org</a>.</p>
      <p>Thank you for being such an important part of Team 5000. Together, we are creating real impact.</p>
      <p>Warm regards<br>Team Goonj</p>
    ";
  }

  /**
   * Builds the HTML email body for generic recurring donation reminders.
   *
   * Template content is a placeholder — final copy to be confirmed with the client.
   */
  private static function getGenericEmailHtml(string $endDate): string {
    $formattedDate = (new \DateTime($endDate))->format('F j, Y');

    return "
      <p>Greetings from Goonj!</p>
      <p>Just a gentle reminder that your recurring donation is set to end in a few days (on <strong>{$formattedDate}</strong>).</p>
      <p>We truly hope you'll continue this journey with us.</p>
      <p>Your contribution has been making a meaningful difference, helping us reach communities that need it most.</p>
      <p>We warmly invite you to renew your contribution at your earliest convenience.</p>
      <p>Kindly ignore if you have already renewed your contribution. If you have any questions or need assistance, please feel free to write to us at <a href='mailto:accounts@goonj.org'>accounts@goonj.org</a>.</p>
      <p>Thank you for being a part of the Goonj family. Together, we are creating real impact.</p>
      <p>Warm regards<br>Team Goonj</p>
    ";
  }

}
