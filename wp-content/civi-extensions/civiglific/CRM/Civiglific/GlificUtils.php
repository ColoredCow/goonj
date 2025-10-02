<?php

namespace CRM\Civiglific;

/**
 *
 */
if (!class_exists('CRM\Civiglific\GlificUtils')) {
class GlificUtils {

  /**
   * Normalize phone numbers by removing all non-digit characters.
   *
   * @param string $phone
   *   The phone number to normalize.
   *
   * @return string
   *   The normalized phone number.
   */
  public static function normalizePhone($phone) {
    return preg_replace('/\D+/', '', $phone);
  }

}

}
