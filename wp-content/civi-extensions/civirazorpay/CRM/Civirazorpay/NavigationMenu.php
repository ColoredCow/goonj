<?php

/**
 * @file
 */

/**
 * Implements hook_civicrm_navigationMenu.
 */
function civirazorpay_civicrm_navigationMenu(&$menu) {
  // Define a unique key for the new menu item.
  $parentId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Administer', 'id', 'name');
  $menu['civirazorpay_settings'] = [
    'attributes' => [
      'label' => ts('Razorpay Settings'),
      'name' => 'Razorpay Settings',
      'url' => 'civicrm/admin/razorpay/settings',
      'permission' => 'administer CiviCRM',
      'operator' => NULL,
      'separator' => NULL,
      'parentID' => $parentId,
      'active' => 1,
    ],
  ];
}
