<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Core\Service\AutoSubscriber;

/**
 * Service class for PAN card verification logic.
 * Handles reading/writing PAN fields on Contact and Contribution records.
 */
class PanVerificationService extends AutoSubscriber {

  const PAN_STATUS_NOT_VERIFIED = 'Not_Verified';
  const PAN_STATUS_VERIFIED = 'Verified';

  /**
   * Stores PAN verification result between validateForm/pre and post hooks.
   * Keyed by contact_id.
   */
  private static $pendingPanVerification = [];

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_validateForm' => 'onContributionFormValidate',
      '&hook_civicrm_pre'          => 'onContributionPreSave',
      '&hook_civicrm_post'         => 'onContributionPostSave',
    ];
  }

  /**
   * Fires during form validation for the frontend contribution form.
   * Verifies PAN against the CashFree API and blocks submission if invalid.
   * Sets $pendingPanVerification so the post hook can mark the contribution.
   */
  public static function onContributionFormValidate($formName, &$fields, &$files, &$form, &$errors): void {
    if ($formName !== 'CRM_Contribute_Form_Contribution_Main') {
      return;
    }

    $pan = self::getPanFromParams($fields);
    error_log('[PanVerification] validateForm — PAN: ' . ($pan ?? 'NULL'));

    if (empty($pan)) {
      return;
    }

    $enteredPan = strtoupper(trim($pan));

    if (!self::isValidPanFormat($enteredPan)) {
      error_log('[PanVerification] validateForm — invalid format: ' . $enteredPan);
      $panFieldKey = self::getPanFieldKey();
      if ($panFieldKey) {
        $errors[$panFieldKey] = 'Invalid PAN card format. Correct format: ABCDE1234F (5 letters, 4 digits, 1 letter).';
      }
      return;
    }

    $contactId = $form->getVar('_contactID');
    error_log('[PanVerification] validateForm — contact_id: ' . ($contactId ?? 'NULL') . ', PAN: ' . $enteredPan);

    if (!$contactId) {
      error_log('[PanVerification] validateForm — could not resolve contact_id, skipping verification');
      return;
    }

    $contactPan  = self::getContactPan($contactId);
    $existingPan = strtoupper(trim($contactPan['pan_number'] ?? ''));

    error_log('[PanVerification] validateForm — contact check: entered=' . $enteredPan . ', existing=' . $existingPan . ', status=' . ($contactPan['pan_status'] ?? 'NULL'));

    // Same PAN already saved on Contact.
    if ($existingPan === $enteredPan && !empty($existingPan)) {
      if ($contactPan['pan_status'] === self::PAN_STATUS_VERIFIED) {
        error_log('[PanVerification] validateForm — already verified on contact, skipping API. contact_id: ' . $contactId);
        self::$pendingPanVerification[$contactId] = ['verified' => TRUE];
        return;
      }
      error_log('[PanVerification] validateForm — same PAN not verified, blocking. contact_id: ' . $contactId);
      $panFieldKey = self::getPanFieldKey();
      if ($panFieldKey) {
        $errors[$panFieldKey] = 'PAN card verification failed. Your PAN card could not be verified. Please enter a valid PAN card to proceed.';
      }
      return;
    }

    // New or different PAN — call API.
    error_log('[PanVerification] validateForm — calling API. contact_id: ' . $contactId . ', PAN: ' . $enteredPan);
    $result = self::verifyPanViaApi($enteredPan);

    if ($result['verified']) {
      error_log('[PanVerification] validateForm — API verified, saving to contact. contact_id: ' . $contactId);
      self::saveContactPan($contactId, $enteredPan, self::PAN_STATUS_VERIFIED);
      self::$pendingPanVerification[$contactId] = ['verified' => TRUE];
    }
    else {
      error_log('[PanVerification] validateForm — API not verified. contact_id: ' . $contactId . ', message: ' . ($result['message'] ?? ''));
      $panFieldKey = self::getPanFieldKey();
      if ($panFieldKey) {
        $errors[$panFieldKey] = 'PAN card verification failed. ' . (!empty($result['message']) ? $result['message'] : 'Please enter a valid PAN card to proceed.');
      }
    }
  }

  /**
   * Fires before a Contribution is saved (back-office / admin entry).
   * For frontend contributions, PAN is handled via validateForm — this is a no-op.
   * For back-office admin entry, PAN may appear in contribution params directly.
   */
  public static function onContributionPreSave($op, $objectName, $id, &$params): void {
    if ($objectName !== 'Contribution' || ($op !== 'create' && $op !== 'edit')) {
      return;
    }

    $pan = self::getPanFromParams($params);
    error_log('[PanVerification] pre hook — op: ' . $op . ', PAN: ' . ($pan ?? 'NULL'));

    if (empty($pan)) {
      return;
    }

    $enteredPan = strtoupper(trim($pan));

    if (!self::isValidPanFormat($enteredPan)) {
      error_log('[PanVerification] pre hook — invalid format: ' . $enteredPan);
      throw new \CRM_Core_Exception('Invalid PAN card format. Correct format: ABCDE1234F (5 letters, 4 digits, 1 letter).');
    }

    // For create: contact_id is in params. For edit: fetch from contribution.
    $contactId = $params['contact_id'] ?? NULL;
    if (!$contactId && $id) {
      $contribution = Contribution::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('id', '=', $id)
        ->setLimit(1)
        ->execute()
        ->first();
      $contactId = $contribution['contact_id'] ?? NULL;
    }

    if (!$contactId) {
      error_log('[PanVerification] pre hook — could not resolve contact_id, skipping');
      return;
    }

    $contactPan  = self::getContactPan($contactId);
    $existingPan = strtoupper(trim($contactPan['pan_number'] ?? ''));

    error_log('[PanVerification] pre hook — contact check: contact_id=' . $contactId . ', entered=' . $enteredPan . ', existing=' . $existingPan . ', status=' . ($contactPan['pan_status'] ?? 'NULL'));

    // Same PAN already saved on Contact.
    if ($existingPan === $enteredPan && !empty($existingPan)) {
      if ($contactPan['pan_status'] === self::PAN_STATUS_VERIFIED) {
        error_log('[PanVerification] pre hook — already verified on contact, skipping API. contact_id: ' . $contactId);
        self::$pendingPanVerification[$contactId] = ['verified' => TRUE];
        return;
      }
      error_log('[PanVerification] pre hook — same PAN not verified, blocking. contact_id: ' . $contactId);
      throw new \CRM_Core_Exception('PAN card verification failed. Your PAN card could not be verified. Please enter a valid PAN card to proceed.');
    }

    // New or different PAN — call API once.
    error_log('[PanVerification] pre hook — calling API. contact_id: ' . $contactId . ', PAN: ' . $enteredPan);
    $result = self::verifyPanViaApi($enteredPan);

    if ($result['verified']) {
      error_log('[PanVerification] pre hook — API verified, saving to contact. contact_id: ' . $contactId);
      self::saveContactPan($contactId, $enteredPan, self::PAN_STATUS_VERIFIED);
      self::$pendingPanVerification[$contactId] = ['verified' => TRUE];
    }
    else {
      error_log('[PanVerification] pre hook — API not verified, blocking. contact_id: ' . $contactId . ', message: ' . ($result['message'] ?? ''));
      throw new \CRM_Core_Exception('PAN card verification failed. ' . (!empty($result['message']) ? $result['message'] : 'Please enter a valid PAN card to proceed.'));
    }
  }

  /**
   * Fires after a Contribution is saved.
   * Marks the Contribution PAN_Card_Verified field based on the pending verification result.
   */
  public static function onContributionPostSave($op, $objectName, $objectId, &$objectRef): void {
    if ($objectName !== 'Contribution' || ($op !== 'create' && $op !== 'edit')) {
      return;
    }

    if (empty(self::$pendingPanVerification)) {
      return;
    }

    $contribution = Contribution::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('id', '=', $objectId)
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$contribution) {
      error_log('[PanVerification] post hook — contribution not found: contribution_id=' . $objectId);
      return;
    }

    $contactId = $contribution['contact_id'];

    if (!isset(self::$pendingPanVerification[$contactId])) {
      return;
    }

    error_log('[PanVerification] post hook — marking contribution as verified. contribution_id=' . $objectId . ', contact_id=' . $contactId);

    $verified = self::$pendingPanVerification[$contactId]['verified'];
    unset(self::$pendingPanVerification[$contactId]);
    self::updateContributionPanVerified($objectId, $verified);
  }

  /**
   * Returns the custom field key (e.g. 'custom_278') for Contribution_Details.PAN_Card_Number.
   * Result is cached statically for the request lifecycle.
   */
  private static function getPanFieldKey(): ?string {
    static $panFieldKey = NULL;

    if ($panFieldKey === NULL) {
      $field = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
        ->addWhere('name', '=', 'PAN_Card_Number')
        ->setLimit(1)
        ->execute()
        ->first();

      $panFieldKey = $field ? 'custom_' . $field['id'] : FALSE;
    }

    return $panFieldKey ?: NULL;
  }

  /**
   * Extract the PAN card number from a params/fields array using the dynamic custom field key.
   */
  private static function getPanFromParams(array $params): ?string {
    $key = self::getPanFieldKey();

    if (!$key) {
      return NULL;
    }

    return $params[$key] ?? NULL;
  }

  /**
   * Check if a PAN number matches the valid Indian PAN format.
   * Format: 5 uppercase letters + 4 digits + 1 uppercase letter (e.g. ABCDE1234F)
   */
  public static function isValidPanFormat(string $pan): bool {
    return (bool) preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper(trim($pan)));
  }

  /**
   * Fetch the PAN number and verification status stored on a Contact record.
   * Returns an array with keys 'pan_number' and 'pan_status', or null if not found.
   */
  public static function getContactPan(int $contactId): ?array {
    $result = Contact::get(FALSE)
      ->addSelect(
        'PAN_Card_Details.PAN_Card_Number',
        'PAN_Card_Details.PAN_Verification_Status:name'
      )
      ->addWhere('id', '=', $contactId)
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$result) {
      return NULL;
    }

    return [
      'pan_number' => $result['PAN_Card_Details.PAN_Card_Number'] ?? NULL,
      'pan_status' => $result['PAN_Card_Details.PAN_Verification_Status:name'] ?? NULL,
    ];
  }

  /**
   * Save or update the PAN number and verification status on a Contact record.
   * Status should be one of the PAN_STATUS_* constants.
   */
  public static function saveContactPan(int $contactId, string $pan, string $status): void {
    Contact::update(FALSE)
      ->addWhere('id', '=', $contactId)
      ->addValue('PAN_Card_Details.PAN_Card_Number', strtoupper(trim($pan)))
      ->addValue('PAN_Card_Details.PAN_Verification_Status:name', $status)
      ->execute();
  }

  /**
   * Update the PAN Card Verified field on a Contribution record.
   */
  public static function updateContributionPanVerified(int $contributionId, bool $verified): void {
    $status = $verified ? self::PAN_STATUS_VERIFIED : self::PAN_STATUS_NOT_VERIFIED;

    Contribution::update(FALSE)
      ->addWhere('id', '=', $contributionId)
      ->addValue('Contribution_Details.PAN_Card_Verified:name', $status)
      ->execute();
  }

  /**
   * Call the PAN verification API and return a standardized result.
   * Delegates to PanApiClient — no HTTP logic lives here.
   */
  public static function verifyPanViaApi(string $pan): array {
    return PanApiClient::verify($pan);
  }

  /**
   * Fetch the PAN number stored against a specific Contribution record.
   * Returns the PAN string or null if not set.
   */
  public static function getContributionPanNumber(int $contributionId): ?string {
    $result = Contribution::get(FALSE)
      ->addSelect('Contribution_Details.PAN_Card_Number')
      ->addWhere('id', '=', $contributionId)
      ->setLimit(1)
      ->execute()
      ->first();

    return $result['Contribution_Details.PAN_Card_Number'] ?? NULL;
  }

}
