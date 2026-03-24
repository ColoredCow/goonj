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
   *
   */
  public static function getSubscribedEvents() {
    return [];
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
