<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;

/**
 * Handles subscription expiry reminder emails for Team 5000 donors.
 *
 * Sends reminders at 7, 3, and 1 day(s) before the calculated subscription
 * end date. Logs a CiviCRM activity after each send to prevent duplicates.
 */
class Team5000SubscriptionReminderService {

  const ACTIVITY_TYPE_NAME = 'Team 5000 Subscription Reminder';
  const CONTRIBUTION_PAGE_NAME = 'Team_5000';
  const REMINDER_DAYS = [7, 3, 1];
  const CC_RECIPIENTS = 'priyanka@goonj.org, accounts@goonj.org';

  /**
   * Entry point called by the cron.
   *
   * Processes reminders for:
   * 1. Team 5000 recurring contributions (identified by contribution_page_id).
   * 2. All other recurring contributions (processed independently).
   */
  public static function processReminders(\DateTimeImmutable $now, string $from): void {
    self::processTeam5000Reminders($now, $from);
    self::processGenericRecurringReminders($now, $from);
  }

  /**
   * Processes expiry reminders for Team 5000 subscriptions.
   */
  private static function processTeam5000Reminders(\DateTimeImmutable $now, string $from): void {
    $contributions = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contribution_page_id:name', '=', self::CONTRIBUTION_PAGE_NAME)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->addWhere('is_test', '=', TRUE)
      ->execute();

    $recurIds = array_unique(array_column((array) $contributions, 'contribution_recur_id'));

    if (empty($recurIds)) {
      \Civi::log()->warning('Team 5000: No recurring contributions found for contribution page: ' . self::CONTRIBUTION_PAGE_NAME);
      return;
    }

    $recurringContributions = ContributionRecur::get(FALSE)
      ->addSelect('id', 'contact_id', 'start_date', 'installments', 'frequency_interval', 'frequency_unit', 'amount', 'contribution_status_id')
      ->addWhere('id', 'IN', $recurIds)
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->addWhere('is_test', '=', TRUE)
      ->execute();

    \Civi::log()->info('Team 5000: Active recurs to process: ' . $recurringContributions->count());

    foreach ($recurringContributions as $recur) {
      try {
        self::processSubscription($recur, $now, $from, self::CONTRIBUTION_PAGE_NAME, TRUE);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Team 5000: Error processing subscription reminder', [
          'recur_id' => $recur['id'],
          'error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Processes expiry reminders for all non-Team-5000 recurring contributions.
   *
   * Excludes recur IDs already covered by the Team 5000 flow to prevent
   * duplicate reminders for contacts who have both.
   */
  private static function processGenericRecurringReminders(\DateTimeImmutable $now, string $from): void {
    // Get Team 5000 recur IDs to exclude.
    $team5000Contributions = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contribution_page_id:name', '=', self::CONTRIBUTION_PAGE_NAME)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->execute();

    $team5000RecurIds = array_unique(array_column((array) $team5000Contributions, 'contribution_recur_id'));

    $query = ContributionRecur::get(FALSE)
      ->addSelect('id', 'contact_id', 'start_date', 'installments', 'frequency_interval', 'frequency_unit', 'amount', 'contribution_status_id')
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->addWhere('installments', 'IS NOT NULL')
      ->addWhere('installments', '>', 0);

    if (!empty($team5000RecurIds)) {
      $query->addWhere('id', 'NOT IN', $team5000RecurIds);
    }

    $recurringContributions = $query->execute();

    \Civi::log()->info('Recurring reminders: Active recurs to process: ' . $recurringContributions->count());

    foreach ($recurringContributions as $recur) {
      try {
        self::processSubscription($recur, $now, $from, NULL, FALSE);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Recurring reminders: Error processing subscription reminder', [
          'recur_id' => $recur['id'],
          'error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Checks if any reminder is due for the given subscription and sends it.
   *
   * @param array $recur The recurring contribution record.
   * @param \DateTimeImmutable $now Current datetime.
   * @param string $from From email address.
   * @param string|null $contributionPageName Page name for renewal detection (null for generic recurs).
   * @param bool $isTeam5000 Whether to use Team 5000 email template.
   */
  private static function processSubscription(array $recur, \DateTimeImmutable $now, string $from, ?string $contributionPageName, bool $isTeam5000): void {
    if (empty($recur['start_date']) || empty($recur['installments'])) {
      \Civi::log()->info('Team 5000: Skipping recur {id} — missing start_date or installments', [
        'id' => $recur['id'],
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

      if (self::hasReminderBeenSent($recur['id'], $recur['contact_id'], $daysBefore)) {
        \Civi::log()->info('Team 5000: Reminder already sent, skipping', [
          'recur_id' => $recur['id'],
          'days_before' => $daysBefore,
        ]);
        continue;
      }

      if (self::hasPaymentReceivedAfterReminder($recur['id'], $recur['contact_id'])) {
        \Civi::log()->info('Team 5000: Payment received after earlier reminder, skipping remaining reminders', [
          'recur_id' => $recur['id'],
          'days_before' => $daysBefore,
        ]);
        continue;
      }

      if (self::hasRenewed($recur['contact_id'], $recur['id'], $recur['start_date'], $contributionPageName)) {
        \Civi::log()->info('Team 5000: Donor has already renewed, skipping reminder', [
          'recur_id' => $recur['id'],
          'days_before' => $daysBefore,
        ]);
        continue;
      }

      self::sendReminderAndLog($recur, $endDate, $daysBefore, $from, $isTeam5000);
    }
  }

  /**
   * Checks if a payment was received on this recur after an earlier reminder was sent.
   *
   * If the first reminder activity exists and a Completed contribution was created
   * after that activity date, the remaining reminders should be suppressed.
   */
  private static function hasPaymentReceivedAfterReminder(int $recurId, int $contactId): bool {
    $firstReminder = Activity::get(FALSE)
      ->addSelect('activity_date_time')
      ->addWhere('activity_type_id:name', '=', self::ACTIVITY_TYPE_NAME)
      ->addWhere('target_contact_id', '=', $contactId)
      ->addWhere('subject', 'LIKE', '%recur_id:' . $recurId . '%')
      ->addOrderBy('activity_date_time', 'ASC')
      ->setLimit(1)
      ->execute()
      ->first();

    if (empty($firstReminder)) {
      return FALSE;
    }

    $reminderDate = $firstReminder['activity_date_time'];

    $payment = Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('contribution_recur_id', '=', $recurId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->addWhere('receive_date', '>', $reminderDate)
      ->setLimit(1)
      ->execute()
      ->first();

    return !empty($payment);
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
   * Checks if a reminder for the given recur ID and day window was already sent.
   *
   * Idempotency is based on a matching activity record for this contact,
   * recur_id, and days_before value encoded in the subject.
   */
  private static function hasReminderBeenSent(int $recurId, int $contactId, int $daysBefore): bool {
    $existing = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('activity_type_id:name', '=', self::ACTIVITY_TYPE_NAME)
      ->addWhere('target_contact_id', '=', $contactId)
      ->addWhere('subject', 'LIKE', '%recur_id:' . $recurId . '%')
      ->addWhere('subject', 'LIKE', '%- ' . $daysBefore . ' days%')
      ->execute()
      ->first();

    return !empty($existing);
  }

  /**
   * Checks if the donor has started a new subscription of the same type (renewal).
   *
   * A renewal only counts if another active recur exists with a later start_date
   * than the current expiring one — prevents old test recurs from being treated
   * as renewals.
   */
  private static function hasRenewed(int $contactId, int $currentRecurId, string $currentStartDate, ?string $contributionPageName): bool {
    $query = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->addWhere('contribution_recur_id', '!=', $currentRecurId);

    if ($contributionPageName) {
      $query->addWhere('contribution_page_id:name', '=', $contributionPageName);
    }
    else {
      $query->addWhere('contribution_page_id', 'IS NULL');
    }

    $contributions = $query->execute();
    $recurIds = array_unique(array_column((array) $contributions, 'contribution_recur_id'));

    if (empty($recurIds)) {
      return FALSE;
    }

    $newActiveRecur = ContributionRecur::get(FALSE)
      ->addSelect('id')
      ->addWhere('id', 'IN', $recurIds)
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->addWhere('start_date', '>', $currentStartDate)
      ->execute()
      ->first();

    return !empty($newActiveRecur);
  }

  /**
   * Fetches contact details, sends the reminder email, and logs the activity.
   */
  private static function sendReminderAndLog(array $recur, string $endDate, int $daysBefore, string $from, bool $isTeam5000 = TRUE): void {
    $contactId = $recur['contact_id'];

    $contact = Contact::get(FALSE)
      ->addSelect('display_name', 'email.email')
      ->addJoin('Email AS email', 'LEFT', ['email.is_primary', '=', TRUE])
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    if (empty($contact['email.email'])) {
      \Civi::log()->info('Team 5000: Skipping reminder — no email for contact {id}', [
        'id' => $contactId,
        'recur_id' => $recur['id'],
        'days_before' => $daysBefore,
      ]);
      return;
    }

    $donorName = $contact['display_name'] ?? 'Valued Supporter';
    $donorEmail = $contact['email.email'];

    $mailParams = [
      'subject' => self::getEmailSubject($daysBefore),
      'from' => $from,
      'toEmail' => $donorEmail,
      'toName' => $donorName,
      'replyTo' => $from,
      'cc' => self::CC_RECIPIENTS,
      'html' => $isTeam5000
        ? self::getReminderEmailHtml($donorName, $endDate, $daysBefore, $recur['amount'] ?? NULL)
        : self::getGenericReminderEmailHtml($donorName, $endDate, $daysBefore, $recur['amount'] ?? NULL),
    ];

    $emailResult = \CRM_Utils_Mail::send($mailParams);

    if (!$emailResult) {
      \Civi::log()->error('Team 5000: Failed to send reminder email', [
        'contact_id' => $contactId,
        'email' => $donorEmail,
        'recur_id' => $recur['id'],
        'days_before' => $daysBefore,
      ]);
      return;
    }

    \Civi::log()->info('Team 5000: Reminder email sent', [
      'recur_id' => $recur['id'],
      'email' => $donorEmail,
      'days_before' => $daysBefore,
    ]);

    self::createReminderActivity($contactId, $recur['id'], $daysBefore);
  }

  /**
   * Creates a CiviCRM activity to record that the reminder was sent.
   */
  private static function createReminderActivity(int $contactId, int $recurId, int $daysBefore): void {
    Activity::create(FALSE)
      ->addValue('activity_type_id:name', self::ACTIVITY_TYPE_NAME)
      ->addValue('status_id:name', 'Completed')
      ->addValue('source_contact_id', $contactId)
      ->addValue('target_contact_id', $contactId)
      ->addValue('subject', 'Team 5000 Subscription Expiry Reminder - ' . $daysBefore . ' days (recur_id:' . $recurId . ')')
      ->addValue('details', 'Automated reminder sent ' . $daysBefore . ' day(s) before subscription expiry.')
      ->execute();
  }

  /**
   * Returns the email subject line based on how many days remain.
   */
  private static function getEmailSubject(int $daysBefore): string {
    if ($daysBefore === 1) {
      return 'Last Reminder: Your Team 5000 Membership Expires Tomorrow';
    }
    return "Reminder: Your Team 5000 Membership Expires in {$daysBefore} Days";
  }

  /**
   * Builds the HTML email body for the reminder.
   *
   * Note: Template content is a placeholder — final copy to be confirmed with the client.
   */
  private static function getReminderEmailHtml(string $donorName, string $endDate, int $daysBefore, ?float $amount): string {
    $formattedDate = (new \DateTime($endDate))->format('F j, Y');

    $amountLine = '';
    if ($amount) {
      $amountLine = '<p>Your monthly contribution of <strong>₹' . number_format($amount, 0) . '</strong> has been making a real difference — helping us reach communities that need it most.</p>';
    }

    if ($daysBefore === 7) {
      $urgencyLine = 'We wanted to give you an early heads-up so you have plenty of time to plan your renewal.';
    }
    elseif ($daysBefore === 3) {
      $urgencyLine = 'Just a gentle nudge — your membership is expiring in 3 days and we\'d love for you to stay with us.';
    }
    else {
      $urgencyLine = 'This is your final reminder — your membership expires tomorrow and we wouldn\'t want you to miss a beat.';
    }

    return "
      <p>Dear <strong>{$donorName}</strong>,</p>
      <p>Greetings from Goonj!</p>
      <p>{$urgencyLine}</p>
      <p>Your Team 5000 membership is set to expire on <strong>{$formattedDate}</strong>.</p>
      {$amountLine}
      <p>Your generosity has been a cornerstone of our work — empowering communities, restoring dignity, and creating lasting change. We truly value your commitment to this journey.</p>
      <p>To continue your support and ensure your membership stays active, we warmly invite you to renew your Team 5000 membership at your earliest convenience.</p>
      <p>For any questions or assistance, please feel free to write to us at <a href='mailto:accounts@goonj.org'>accounts@goonj.org</a>.</p>
      <p>Thank you for being a vital part of Team 5000 — together, we are making a difference!</p>
      <p>Warm regards,<br>Team Goonj</p>
    ";
  }

  /**
   * Builds the HTML reminder email for generic recurring donations.
   */
  private static function getGenericReminderEmailHtml(string $donorName, string $endDate, int $daysBefore, ?float $amount): string {
    $formattedDate = (new \DateTime($endDate))->format('F j, Y');
    $amountLine = $amount ? '<p>Your monthly contribution of <strong>₹' . number_format($amount, 0) . '</strong> has been making a real difference.</p>' : '';

    return "
      <p>Dear <strong>{$donorName}</strong>,</p>
      <p>Greetings from Goonj!</p>
      <p>Your recurring donation is set to expire on <strong>{$formattedDate}</strong> ({$daysBefore} day(s) remaining).</p>
      {$amountLine}
      <p>Please renew your contribution to continue supporting our work. For assistance, write to <a href='mailto:accounts@goonj.org'>accounts@goonj.org</a>.</p>
      <p>Thank you,<br>Team Goonj</p>
    ";
  }

}
