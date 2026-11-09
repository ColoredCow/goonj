<?php

namespace Civi;

/**
 * Goonj registered office address printed in the footer of material receipts.
 *
 * Goonj moved its registered office in November 2026. Receipts for
 * contributions entered in the CRM (created date) before the cutoff keep the
 * old address and phone numbers, also when they are regenerated later.
 * Contributions entered from the cutoff onward show the new ones.
 *
 * The cutoff is set per environment in wp-config.php:
 *   define('GOONJ_ADDRESS_CUTOFF_DATE', 'YYYY-MM-DD');
 * Until it is defined, every receipt keeps the old address.
 */
class RegisteredOfficeAddress {

  const OLD_ADDRESS = 'Goonj, C-544, 1st Floor, C-Pocket, Sarita Vihar,<br>New Delhi-110076';

  const NEW_ADDRESS = 'Goonj, A-22, Block B-1, Mohan Co-operative Industrial Estate,<br>New Delhi - 110044';

  const NEW_PHONE = '18002029012/ 011-41401216';

  /**
   * Returns the footer address and phone for a receipt.
   *
   * @param string|null $createdDate
   *   The created date of the activity or record the receipt is for.
   * @param string $oldPhone
   *   The phone numbers this receipt printed before the change.
   *
   * @return array
   *   ['address' => string, 'phone' => string]
   */
  public static function forReceipt(?string $createdDate, string $oldPhone): array {
    if (self::isNewAddress($createdDate)) {
      return ['address' => self::NEW_ADDRESS, 'phone' => self::NEW_PHONE];
    }

    return ['address' => self::OLD_ADDRESS, 'phone' => $oldPhone];
  }

  /**
   * Whether a contribution entered on the given date shows the new address.
   */
  public static function isNewAddress(?string $createdDate): bool {
    if (!defined('GOONJ_ADDRESS_CUTOFF_DATE') || !GOONJ_ADDRESS_CUTOFF_DATE || empty($createdDate)) {
      return FALSE;
    }

    $createdTime = strtotime($createdDate);
    $cutoffTime = strtotime(GOONJ_ADDRESS_CUTOFF_DATE);
    if ($createdTime === FALSE || $cutoffTime === FALSE) {
      return FALSE;
    }

    return date('Y-m-d', $createdTime) >= date('Y-m-d', $cutoffTime);
  }

}
