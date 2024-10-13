<?php

namespace Civi;

/**
 * Collection Camp Outcome Service.
 */
class HelperService {

  /**
   * Get default from email.
   */
  public static function getDefaultFromEmail() {
    [$defaultFromName, $defaultFromEmail] = \CRM_Core_BAO_Domain::getNameAndEmail();
    return "\"$defaultFromName\" <$defaultFromEmail>";
  }

}
