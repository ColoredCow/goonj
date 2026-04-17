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
  const REMINDER_DAYS = [30, 7, 2];
  const CC_RECIPIENTS = 'priyanka@goonj.org, accounts@goonj.org';

  /**
   * Entry point called by the cron.
   *
   * Fetches all active Team 5000 recurring contributions and processes
   * each one for due reminders.
   */
  public static function processReminders(\DateTimeImmutable $now, string $from): void {

    // Get unique recur IDs via the first contribution of each Team 5000 subscription.
    // The first contribution always has contribution_page_id = 7.
    // Subsequent Razorpay payments have contribution_page_id = null, but we only
    // need one match per recur — after that we work directly on civicrm_contribution_recur.
    $contributions = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contribution_page_id:name', '=', self::CONTRIBUTION_PAGE_NAME)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->execute();

    $recurIds = array_unique(array_column((array) $contributions, 'contribution_recur_id'));

    if (empty($recurIds)) {
      \Civi::log()->warning('Team 5000: No recurring contributions found for contribution page: ' . self::CONTRIBUTION_PAGE_NAME);
      return;
    }

    // Fetch active recurring contributions.
    $recurringContributions = ContributionRecur::get(FALSE)
      ->addSelect('id', 'contact_id', 'start_date', 'installments', 'frequency_interval', 'frequency_unit', 'amount', 'contribution_status_id')
      ->addWhere('id', 'IN', $recurIds)
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->execute();

    \Civi::log()->info('Team 5000: Active recurs to process: ' . $recurringContributions->count());

    foreach ($recurringContributions as $recur) {
      try {
        self::processSubscription($recur, $now, $from);
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
   * Checks if any reminder is due for the given subscription and sends it.
   */
  private static function processSubscription(array $recur, \DateTimeImmutable $now, string $from): void {
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

      // Skip if donor has already renewed their Team 5000 subscription.
      if (self::hasRenewed($recur['contact_id'], $recur['id'])) {
        \Civi::log()->info('Team 5000: Donor has already renewed, skipping reminder', [
          'recur_id' => $recur['id'],
          'days_before' => $daysBefore,
        ]);
        continue;
      }

      self::sendReminderAndLog($recur, $endDate, $daysBefore, $from);
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
   * Checks if the donor has started a new Team 5000 subscription (renewal).
   *
   * Looks for another active recurring contribution on the Team 5000 page
   * for the same contact, excluding the current expiring recur.
   */
  private static function hasRenewed(int $contactId, int $currentRecurId): bool {
    $contributions = Contribution::get(FALSE)
      ->addSelect('contribution_recur_id')
      ->addWhere('contribution_page_id:name', '=', self::CONTRIBUTION_PAGE_NAME)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('contribution_recur_id', 'IS NOT NULL')
      ->addWhere('contribution_recur_id', '!=', $currentRecurId)
      ->execute();

    $recurIds = array_unique(array_column((array) $contributions, 'contribution_recur_id'));

    if (empty($recurIds)) {
      return FALSE;
    }

    $newActiveRecur = ContributionRecur::get(FALSE)
      ->addSelect('id')
      ->addWhere('id', 'IN', $recurIds)
      ->addWhere('contribution_status_id:name', '=', 'In Progress')
      ->execute()
      ->first();

    return !empty($newActiveRecur);
  }

  /**
   * Fetches contact details, sends the reminder email, and logs the activity.
   */
  private static function sendReminderAndLog(array $recur, string $endDate, int $daysBefore, string $from): void {
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
      'html' => self::getReminderEmailHtml($endDate),
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
    $labels = [
      30 => '1 Month',
      7  => '7 Days',
      2  => '2 Days',
    ];
    $label = $labels[$daysBefore] ?? "{$daysBefore} Days";
    return "Reminder: Your Team 5000 Membership Expires in {$label}";
  }

  /**
   * Builds the HTML email body for the reminder.
   *
   * Note: Template content is a placeholder — final copy to be confirmed with the client.
   */
  private static function getReminderEmailHtml(string $endDate): string {
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

}
