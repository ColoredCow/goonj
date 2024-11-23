<?php
use CRM_AwsSes_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_AwsSes_Upgrader extends CRM_Extension_Upgrader_Base {

  public function postInstall() {

    $this->createBounceTypes();
    $this->generateSecret();
  }

  protected function createBounceTypes() {
    // Below ALTER query should be removed once core PR is merged: https://github.com/civicrm/civicrm-core/pull/23658
    CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_mailing_bounce_type` CHANGE `name` `name` VARCHAR(256) NOT NULL COMMENT 'Type of bounce', CHANGE `description` `description` VARCHAR(2048) NULL DEFAULT NULL COMMENT 'A description of this bounce type'");

    // update log tables
    $schema = new CRM_Logging_Schema();
    $schema->fixSchemaDifferences();

    foreach (\Civi\SES\BounceType::$types as $name => $fields) {
      $query = "SELECT id FROM civicrm_mailing_bounce_type WHERE name = %0";
      $searchParams = [[$name, 'String']];
      $result = CRM_Core_DAO::executeQuery($query, $searchParams);
      $saveParams = [
        [$name, 'String'],
        [$fields['description'], 'String'],
        [$fields['hold_threshold'], 'Integer'],
      ];

      if (!$result->fetch()) {
        $query = "INSERT INTO civicrm_mailing_bounce_type SET name = %0, description = %1, hold_threshold = %2";
      }
      else {
        $query = "UPDATE civicrm_mailing_bounce_type SET name = %0, description = %1, hold_threshold = %2 WHERE id = %3";
        $saveParams[3] = [$result->id, 'Integer'];
      }

      $result = CRM_Core_DAO::executeQuery($query, $saveParams);
    }
  }

  protected function generateSecret() {
    $secret = \Civi::settings()->get('aws_ses_secret');
    if (!$secret) {
      $secret = md5(uniqid(rand(), TRUE));
      \Civi::settings()->set('aws_ses_secret', $secret);
    }
  }

}
