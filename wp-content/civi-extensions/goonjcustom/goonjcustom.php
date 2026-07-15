<?php

/**
 * @file
 */

require_once 'goonjcustom.civix.php';

use Civi\InductionService;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\UserJob;
use Civi\Token\Event\TokenRegisterEvent;
use Civi\Token\Event\TokenValueEvent;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/api/v3/ContributionFilter.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function goonjcustom_civicrm_config(&$config): void {
  _goonjcustom_civix_civicrm_config($config);

  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_CollectionCamp());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_DroppingCenter());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_IndividualGoonjActivities());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_InstitutionCollectionCamp());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_InstitutionDroppingCenter());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_InstitutionGoonjActivities());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_IndividualGoonjActivities());

  // Forward CiviCRM API/cron exceptions to Sentry (the WP "Sentry for
  // WordPress" plugin already initialises the SDK). Many CiviCRM exceptions —
  // especially in scheduled jobs — are caught internally and only written to
  // ConfigAndLog, so they never reach Sentry on their own. This surfaces them.
  \Civi::dispatcher()->addListener('civi.api.exception', '_goonjcustom_sentry_capture_api_exception');

  // Catch page/form fatals. CiviCRM swallows these in
  // CRM_Core_Error::handleUnhandledException() (renders an error page), so they
  // never reach PHP's handler or the API event above — this is the only place
  // they surface. These are the "$Fatal Error Details" entries in ConfigAndLog.
  \Civi::dispatcher()->addListener('hook_civicrm_unhandled_exception', '_goonjcustom_sentry_capture_unhandled_exception');
}

/**
 * Report a CiviCRM page/form unhandled exception to Sentry.
 *
 * No-op when the Sentry SDK is not loaded, so it is safe on any environment.
 */
function _goonjcustom_sentry_capture_unhandled_exception($event): void {
  if (!function_exists('Sentry\captureException')) {
    return;
  }

  $exception = $event->exception ?? NULL;
  if (!$exception instanceof \Throwable) {
    return;
  }

  try {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($exception): void {
      $scope->setTag('civicrm.component', 'page');
      \Sentry\captureException($exception);
    });
  }
  catch (\Throwable $sentryFailure) {
    // Best-effort: never let Sentry forwarding mask the original error page.
    error_log('[goonjcustom][sentry] page-exception forward failed: ' . $sentryFailure->getMessage());
  }
}

/**
 * Report a CiviCRM API exception to Sentry, tagged with the failing entity/action.
 *
 * No-op when the Sentry SDK is not loaded, so it is safe in any context
 * (CLI, cron, web) and on environments where Sentry is not configured.
 */
function _goonjcustom_sentry_capture_api_exception($event): void {
  if (!function_exists('Sentry\captureException')) {
    return;
  }

  $exception = method_exists($event, 'getException') ? $event->getException() : NULL;
  if (!$exception instanceof \Throwable) {
    return;
  }

  $request = method_exists($event, 'getApiRequest') ? $event->getApiRequest() : [];
  $entity = $request['entity'] ?? 'unknown';
  $action = $request['action'] ?? 'unknown';

  try {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($exception, $entity, $action): void {
      $scope->setTag('civicrm.component', 'api');
      $scope->setTag('civicrm.entity', (string) $entity);
      $scope->setTag('civicrm.action', (string) $action);
      \Sentry\captureException($exception);
    });
  }
  catch (\Throwable $sentryFailure) {
    // Best-effort: never let Sentry forwarding interfere with the API call.
    error_log('[goonjcustom][sentry] api-exception forward failed: ' . $sentryFailure->getMessage());
  }
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function goonjcustom_civicrm_install(): void {
  _goonjcustom_civix_civicrm_install();
}

/**
 * 
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function goonjcustom_civicrm_enable(): void {
  _goonjcustom_civix_civicrm_enable();
}

/**
 * Add token services to the container.
 *
 * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
 */
function goonjcustom_civicrm_container(ContainerBuilder $container) {
  $container->addResource(new FileResource(__FILE__));
  $container->findDefinition('dispatcher')->addMethodCall(
        'addListener',
        ['civi.token.list', 'goonjcustom_register_tokens']
  )->setPublic(TRUE);
  $container->findDefinition('dispatcher')->addMethodCall(
        'addListener',
        ['civi.token.eval', 'goonjcustom_evaluate_tokens']
  )->setPublic(TRUE);

  // Route CiviCRM's PSR-3 logger through our subclass so that
  // Civi::log()->error() (and higher) entries are forwarded to Sentry. File
  // logging is unchanged; see CRM_Goonjcustom_SentryLog.
  if ($container->hasDefinition('psr_log')) {
    $container->getDefinition('psr_log')->setClass('CRM_Goonjcustom_SentryLog');
  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * Background-queue imports (enableBackgroundQueue) hand the job off to the
 * queue runner and redirect the operator to the queue monitor page. The
 * default civiimport monitor template reloads the import summary as an AJAX
 * snippet every second, with no stop condition — producing a perpetually
 * "blinking" screen that never settles on the final result.
 *
 * Instead, for import jobs we send the operator straight to the full import
 * summary page (the URL core already recorded as the job's onEndUrl). While
 * the background job is still draining, the summary shows an "in progress"
 * notice asking the operator to refresh — see goonjcustom_civicrm_buildForm().
 */
function goonjcustom_civicrm_pageRun(&$page) {
  if (!($page instanceof CRM_Queue_Page_Monitor)) {
    return;
  }
  $info = _goonjcustom_import_monitor_target();
  if (!$info) {
    return;
  }
  // Redirect as early as possible (html-header) so the blinking snippet
  // never gets a chance to run.
  CRM_Core_Resources::singleton()->addScript(
    'window.location.replace(' . json_encode($info['url']) . ');',
    1,
    'html-header'
  );
}

/**
 * Resolve the queue-monitor request to an import job + its summary URL.
 *
 * Mirrors the import-job detection civiimport uses for its monitor template
 * (queue named "user_job_<id>" whose job type is a CRM_Import_Parser).
 *
 * @return array|null
 *   ['user_job_id' => int, 'url' => string] for an import job, else NULL.
 */
function _goonjcustom_import_monitor_target(): ?array {
  $queueName = CRM_Utils_Request::retrieveValue('name', 'String');
  if (!$queueName || !str_starts_with($queueName, 'user_job_')) {
    return NULL;
  }
  $userJobId = (int) str_replace('user_job_', '', $queueName);
  if (!$userJobId) {
    return NULL;
  }
  try {
    $userJob = UserJob::get(TRUE)
      ->addWhere('id', '=', $userJobId)
      ->addSelect('job_type', 'metadata')
      ->execute()
      ->first();
  }
  catch (\Exception $e) {
    return NULL;
  }
  if (empty($userJob)) {
    return NULL;
  }
  $isImport = FALSE;
  foreach (CRM_Core_BAO_UserJob::getTypes() as $type) {
    if ($type['id'] === $userJob['job_type']
      && is_subclass_of($type['class'], 'CRM_Import_Parser')
    ) {
      $isImport = TRUE;
      break;
    }
  }
  if (!$isImport) {
    return NULL;
  }
  // Prefer the completion URL core already stored; fall back to building it.
  $url = $userJob['metadata']['runner']['onEndUrl']
    ?? CRM_Utils_System::url('civicrm/import/contact/summary', [
      'reset' => 1,
      'user_job_id' => $userJobId,
    ], TRUE, NULL, FALSE);
  return ['user_job_id' => $userJobId, 'url' => $url];
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * While a background-queue import is still being drained by the queue runner
 * (coworker), the import summary shows partial / zero totals. Show a clear
 * "in progress — please refresh" notice while the job is non-terminal so the
 * operator does not mistake the partial totals for a failed import. Operators
 * refresh manually; the notice disappears once the job reaches a terminal
 * state (completed, complete_with_errors, incomplete, …).
 */
function goonjcustom_civicrm_buildForm($formName, &$form) {
  if ($formName !== 'CRM_Contact_Import_Form_Summary') {
    return;
  }
  $userJobId = $form->getUserJobID();
  if (!$userJobId) {
    return;
  }
  try {
    $status = UserJob::get(FALSE)
      ->addWhere('id', '=', $userJobId)
      ->addSelect('status_id:name')
      ->execute()
      ->first()['status_id:name'] ?? NULL;
  }
  catch (\Exception $e) {
    return;
  }
  // Only show the notice while the queue is still working; all other states
  // (completed, complete_with_errors, incomplete, …) are terminal.
  if (!in_array($status, ['scheduled', 'in_progress'], TRUE)) {
    return;
  }
  CRM_Core_Session::setStatus(
    ts('This import is still being processed in the background. Please refresh this page after a short while to see the updated results.'),
    ts('Import in progress'),
    'info'
  );
}

/**
 * Implements hook_civicrm_links().
 *
 * Hides the payment edit action when the user lacks financial type ACL
 * permission to edit the parent contribution. The core financialacls
 * extension only filters Contribution and MembershipType object links,
 * but the payment edit pencil uses objectName 'Payment' which is not handled.
 */
function goonjcustom_civicrm_links($op, $objectName, $objectID, &$links, &$mask, &$values) {
  if ($objectName !== 'Payment' || $op !== 'Payment.edit.action') {
    return;
  }

  if (!CRM_Financial_BAO_FinancialType::isACLFinancialTypeStatus()) {
    return;
  }

  $contributionId = $values['contribution_id'] ?? NULL;
  if (!$contributionId) {
    return;
  }

  $lineItems = CRM_Price_BAO_LineItem::getLineItemsByContributionID((int) $contributionId);
  foreach ($lineItems as $item) {
    $financialType = CRM_Core_PseudoConstant::getName(
      'CRM_Contribute_BAO_Contribution',
      'financial_type_id',
      $item['financial_type_id']
    );
    if (!CRM_Core_Permission::check('edit contributions of type ' . $financialType)) {
      foreach ($links as $index => $link) {
        if ($link['name'] === 'Edit Payment') {
          unset($links[$index]);
        }
      }
      return;
    }
  }
}

function goonjcustom_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  $apiUserId = \CRM_Core_Session::getLoggedInContactID();

  if ($apiUserId !== CIVICRM_ALLOWED_API_USER_ID) {
    return;
  }

  if ($apiRequest['entity'] == 'Campaign' && $apiRequest['action'] == 'get') {
    $wrappers[] = new \CRM_Goonjcustom_APIWrappers_ContributionFilter();
  }
}

/**
 *
 */
function goonjcustom_register_tokens(TokenRegisterEvent $e) {
  $e->entity('contact')
    ->register('inductionDetails', ts('Induction details'))
    ->register('inductionDate', ts('Induction Scheduled Date'))
    ->register('inductionTime', ts('Induction Scheduled Time'))
    ->register('inductionOnlineMeetlink', ts('Induction Online Meet link'))
    ->register('inductionOfficeAddress', ts('Induction Office Address'))
    ->register('inductionOfficeCity', ts('Induction Office City'))
    ->register('urbanOpsName', ts('Urban Ops Name'))
    ->register('urbanOpsPhone', ts('Urban Ops Phone'));
}

/**
 *
 */
function goonjcustom_evaluate_tokens(TokenValueEvent $e) {
  foreach ($e->getRows() as $row) {
    /** @var TokenRow $row */
    $row->format('text/html');

    $contactId = $row->context['contactId'];

    if (empty($contactId)) {
      $row->tokens('contact', 'inductionDetails', '');
      continue;
    }

    $contacts = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id')
      ->addWhere('id', '=', $contactId)
      ->execute();

    $statedata = $contacts->first();
    $stateId = $statedata['address_primary.state_province_id'];

    $processingCenters = Contact::get(FALSE)
      ->addSelect('Goonj_Office_Details.Days_for_Induction', '*', 'custom.*', 'addressee_id', 'id')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('Goonj_Office_Details.Induction_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $inductionDetailsMarkup = 'The next step in your volunteering journey is to get inducted with Goonj.';

    if ($processingCenters->rowCount > 0) {
      if (TRUE) {
        $inductionDetailsMarkup .= ' You can visit any of our following center(s) during the time specified to complete your induction:';
        $inductionDetailsMarkup .= '<ol>';

        foreach ($processingCenters as $processingCenter) {
          $centerID = $processingCenter['id'];
          // Fetch the primary address for the current center.
          $addressesData = Address::get(FALSE)
            ->addWhere('contact_id', '=', $centerID)
            ->addWhere('is_primary', '=', TRUE)
            ->setLimit(1)
            ->execute();
          $address = $addressesData->first();
          $contactAddress = $address['street_address'];

          $inductionDetailsMarkup .= '<li><strong>' . $processingCenter['organization_name'] . '</strong><br /><span style="margin-top: 10px; display: block;">' . $contactAddress . '</span> ' . $processingCenter['Goonj_Office_Details.Days_for_Induction'] . '</li>';

        }

        $inductionDetailsMarkup .= '</ol>';
      }
      else {
        $inductionDetailsMarkup .= ' Unfortunately, we don\'t currently have a processing center near to the location you have provided. Someone from our team will reach out and will share the details of induction.';
      }

      $row->tokens('contact', 'inductionDetails', $inductionDetailsMarkup);
			// Fetch induction activity details
			$inductionActivities = \Civi\Api4\Activity::get(FALSE)
				->addSelect('activity_date_time', 'Induction_Fields.Goonj_Office')
				->addWhere('activity_type_id:name', '=', 'Induction')
				->addWhere('target_contact_id', '=', $contactId)
				->execute();

			if ($inductionActivities->count() === 0) {
				$row->tokens('contact', 'inductionDate', 'Not Scheduled');
        $row->tokens('contact', 'inductionTime', 'Not Scheduled');
				$row->tokens('contact', 'inductionOnlineMeetlink', '');
				return;
			}

			$inductionActivity = $inductionActivities->first();
			$inductionDateTimeString = $inductionActivity['activity_date_time'] ?? 'Not Scheduled';

      if ($inductionDateTimeString !== 'Not Scheduled') {
        $dateTime = new DateTime($inductionDateTimeString);
        $inductionDate = $dateTime->format('Y-m-d');
        $inductionTime = $dateTime->format('g:i A');
      } else {
          $inductionDate = 'Not Scheduled';
          $inductionTime = 'Not Scheduled';
      }

			$inductionGoonjOffice = $inductionActivity['Induction_Fields.Goonj_Office'] ?? '';

			// Fetch office online meet link details if induction office is specified
			$inductionOnlineMeetlink = '';
      $completeAddress = '';
      $inductionOfficeCity = '';
      $urbanOpsName = '';
      $urbanOpsPhone = '';


			if ($inductionGoonjOffice) {
				$officeDetails = \Civi\Api4\Contact::get(FALSE)
					->addSelect('Goonj_Office_Details.Induction_Meeting_Access_Link', 'address_primary.city')
					->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
					->addWhere('id', '=', $inductionGoonjOffice)
					->execute()->single();

				$inductionOnlineMeetlink = $officeDetails['Goonj_Office_Details.Induction_Meeting_Access_Link'] ?? '';
        $inductionOfficeCity = $officeDetails['address_primary.city'] ?? '';

        $addresses = \Civi\Api4\Address::get( false )
          ->addWhere( 'contact_id', '=', $officeDetails['id'] )
          ->addWhere( 'is_primary', '=', true )
          ->setLimit( 1 )
          ->execute();

        $address = $addresses->count() > 0 ? $addresses->first() : null;
        $completeAddress = CRM_Utils_Address::format($address);

			}

      $office = InductionService::findOfficeForState($stateId);

      $coordinatorId = InductionService::findCoordinatorForOffice($office['id']);

  
      $coordinatorDetails = Contact::get(FALSE)
        ->addSelect('phone.phone', 'display_name')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $coordinatorId)
        ->execute();
      $coordinatorDetail = $coordinatorDetails->first();

      $urbanOpsName = $coordinatorDetail['display_name'] ?? '';
      $urbanOpsPhone = $coordinatorDetail['phone.phone'] ?? '';

			// Assign tokens
			$row->tokens('contact', 'inductionDate', $inductionDate);
      $row->tokens('contact', 'inductionTime', $inductionTime);
			$row->tokens('contact', 'inductionOnlineMeetlink', $inductionOnlineMeetlink);
      $row->tokens('contact', 'inductionOfficeAddress', $completeAddress);
      $row->tokens('contact', 'inductionOfficeCity', $inductionOfficeCity);
      $row->tokens('contact', 'urbanOpsName', $urbanOpsName);
      $row->tokens('contact', 'urbanOpsPhone', $urbanOpsPhone);
    }
  }
}
