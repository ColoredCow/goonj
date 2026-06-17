<?php

use Psr\Log\LogLevel;

/**
 * Drop-in replacement for CiviCRM's PSR-3 logger (CRM_Core_Error_Log) that also
 * forwards high-severity entries to Sentry.
 *
 * Civi::log()->error(...) normally only writes to ConfigAndLog/*.log and never
 * reaches Sentry (the code logged the problem, it did not throw). This decorator
 * surfaces those error/critical/alert/emergency entries on the dashboard while
 * leaving CiviCRM's own file logging completely intact.
 *
 * Wired up in goonjcustom_civicrm_container() by overriding the `psr_log`
 * service class. It is a thin, fail-safe subclass: parent::log() always runs
 * first, and every Sentry call is guarded, so it can never break CiviCRM
 * logging even if Sentry is unavailable.
 */
class CRM_Goonjcustom_SentryLog extends CRM_Core_Error_Log {

  /**
   * Severities forwarded to Sentry. warning/notice/info/debug are intentionally
   * excluded to keep the dashboard signal-only and protect the quota.
   *
   * @var string[]
   */
  private const SENTRY_LEVELS = [
    LogLevel::ERROR,
    LogLevel::CRITICAL,
    LogLevel::ALERT,
    LogLevel::EMERGENCY,
  ];

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Preserve CiviCRM's normal file logging FIRST — never let Sentry break it.
    parent::log($level, $message, $context);

    if (!function_exists('Sentry\captureMessage') || !in_array($level, self::SENTRY_LEVELS, TRUE)) {
      return;
    }

    // Forwarding to Sentry is best-effort only: any failure here must never
    // bubble up and break the caller that was just trying to log something.
    try {
      // If a real exception is attached, send it (gives a full stack trace).
      if (!empty($context['exception']) && $context['exception'] instanceof \Throwable) {
        \Sentry\captureException($context['exception']);
        return;
      }

      \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($level, $message): void {
        $scope->setTag('civicrm.component', 'log');
        $scope->setTag('civicrm.log_level', (string) $level);
        \Sentry\captureMessage((string) $message, \Sentry\Severity::error());
      });
    }
    catch (\Throwable $sentryFailure) {
      // Swallow — CiviCRM logging already succeeded above.
    }
  }

}
