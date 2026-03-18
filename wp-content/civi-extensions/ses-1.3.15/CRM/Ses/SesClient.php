<?php

use Aws\Ses\SesClient;
use Aws\Credentials\Credentials;

/**
 * Singleton class for using a single instance of
 * Aws\Ses\SesClient
 *
 * Using a single persistent instance improves performance
 * as the client is not recreated for each email sent.
 */
class CRM_Ses_SesClient {

  /**
   * The SES Client instance is stored in this
   * static private variable
   */
  private static ?SesClient $instance;

  private function __construct() {}

  private function __clone() {}

  public static function getInstance(): SesClient {
    if (!isset(self::$instance)) {
      $ses_access_key = Civi::settings()->get('ses_access_key');
      $ses_secret_key = Civi::settings()->get('ses_secret_key');
      $ses_region = Civi::settings()->get('ses_region');
      if (empty($ses_access_key) || empty($ses_secret_key) || empty($ses_region)) {
        throw new Exception("Missing required Amazon SES configuration. Please configure the extention in Administer > CiviMail > SES settings.");
      }

      $credentials = new Credentials($ses_access_key, $ses_secret_key);
      self::$instance = new SesClient([
        'version' => 'latest',
        'region' => $ses_region,
        'credentials' => $credentials,
      ]);
    }
    return self::$instance;
  }

}
