<?php

/**
 * @file
 */

use CRM\Civiglific\Service\GlificContactSyncService;

/**
 * Define API spec.
 */
function _civicrm_api3_civiglific_civicrm_glific_contact_sync_cron_spec(&$spec) {}

/**
 * Cron job to sync contacts between CiviCRM group and Glific group.
 */
function civicrm_api3_civiglific_civicrm_glific_contact_sync_cron($params) {
  $returnValues = [];
  $syncService = new GlificContactSyncService();
  $syncService->sync();

  return civicrm_api3_create_success($returnValues, $params, 'Civiglific', 'civicrm_glific_contact_sync_cron');
}
