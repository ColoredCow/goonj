<?php

require_once 'cityselector.civix.php';
// phpcs:disable
use CRM_Cityselector_ExtensionUtil as E;
// phpcs:enable

/**
 * Implementation of hook_civicrm_check
 *
 */
function cityselector_civicrm_check(&$messages) {
  $cityselector_parent = Civi::settings()->get('cityselector_parent');
  if (empty($cityselector_parent)) {
    $messages[] = new CRM_Utils_Check_Message(
      'cityselector_settings',
      ts('City Selector is not properly configured. Please access <a href="' . CRM_Utils_System::url('civicrm/admin/setting/cityselector') . '">here</a>'),
      ts('City Selector Configuration'),
      \Psr\Log\LogLevel::WARNING,
      'fa-flag'
    );
  }
}

/**
 * Implementation of hook_civicrm_buildForm
 *
 */
function cityselector_civicrm_buildForm($formName, &$form) {
  if (in_array($formName, ['CRM_Contact_Form_Contact', 'CRM_Contact_Form_Inline_Address'])) {
    $parent = \Civi::settings()->get('cityselector_parent');
    if (!$parent) {
      CRM_Core_Session::setStatus(E::ts('The cityselector settings has not been configured.'), '', 'error');
      return;
    }
    $parent_fieldname = $parent . "_id";
    $parent_name = ($parent == "state_province") ? "state" : $parent;

    // Add extra props to County select2
    foreach ($form->_elements as $index => &$element) {
      if (isset($element->_attributes['name']) && ($element->_attributes['name'] == "address[1][{$parent_fieldname}]")) {
        $element->_attributes['class'] .= ' crm-chain-select-control';
        $element->_attributes['data-target'] = "address[1][city]";
        $parentValue = is_array($element->_values) ? $element->_values[0] : NULL;
        break;
      }
    }
    // ## ToDo:  apply this for n addresses ##
    // Convert City textbox in chained select2
    if ($form->elementExists('address[1][city]')) {
      $form->removeElement('address[1][city]');
      $props = [
        'controlfield' => "$parent_fieldname",
        'data-callback' => 'civicrm/ajax/jqCity',
        'label' => ts('City'),
        'data-empty-prompt' => ts('Choose %1 first', [1 => $parent_name]),
        'data-none-prompt' => ts('- N/A -'),
        'multiple' => FALSE,
        'required' => FALSE,
      ];
      $props['class'] = 'crm-select2 crm-chain-select-target';
      $props['data-select-prompt'] = $props['placeholder'];
      $props['data-name'] = 'address[1][city]';

      $options = [];
      if (!empty($parentValue)) {
        $options = CRM_Cityselector_BAO_Location::getChainCityValues($parentValue, TRUE);
        if (!$options) {
          $props['placeholder'] = $props['data-none-prompt'];
        }
      }
      else {
        $props['placeholder'] = $props['data-empty-prompt'];
        $props['disabled'] = 'disabled';
      }

      $citySelect = $form->add('select', 'address[1][city]', ts('City'), NULL, FALSE, $props);
      $citySelect->removeAttribute('placeholder');
      $citySelect->_options = [];

      // select ERROR if Contact's city not in cities list (coming from an import or DB edition)
      if (isset($citySelect->_values[0])) {
        $city = $citySelect->_values[0];
        if (!in_array($city, $options)) {
          $options = [$city => ts('- Error -')] + $options;
        }
      }
      $citySelect->loadArray($options);
    }

    CRM_Core_Region::instance('form-bottom')->add([
      'template' => "CRM/Cityselector/Contact/Form/Contact.tpl"
    ]);
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu/
 */
function cityselector_civicrm_navigationMenu(&$menu) {
  _cityselector_civix_insert_navigation_menu($menu, 'Administer/Localization', [
    'label' => E::ts('City Selector Settings'),
    'name' => 'cityselector',
    'url' => 'civicrm/admin/setting/cityselector?reset=1',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
}

/**
 * Implementation of hook_civicrm_validateForm
 *
 */
function cityselector_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName == 'CRM_Admin_Form_Preferences_Address') {
    $addressOptions = CRM_Core_OptionGroup::values('address_options', TRUE, FALSE, FALSE, NULL, 'name');
    $is_active_county = !empty($fields['address_options'][$addressOptions['county']]);
    $is_active_state = !empty($fields['address_options'][$addressOptions['state_province']]);
    $cityselector_parent = \Civi::settings()->get('cityselector_parent');

    // validate mandatory address fields to get the right chaining for cityselector
    if ($cityselector_parent == 'state_province') {
      if ($is_active_county) {
        $errors['address_options'] = E::ts('City Selector is configured using State/Province as parent, you must disable County field to make it work properly');
      }
      elseif (!$is_active_state) {
        $errors['address_options'] = E::ts('City Selector is configured using State/Province as parent, you must enable State/Province field to make it work properly');
      }
    }
    elseif ($cityselector_parent == 'county') {
      if (!$is_active_county) {
        $errors['address_options'] = E::ts('City Selector is configured using County as parent, you must enable County field to make it work properly');
      }
      elseif (!$is_active_state) {
        $errors['address_options'] = E::ts('City Selector is configured using County as parent, you must enable State/Province field to make it work properly');
      }
    }
    return;
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cityselector_civicrm_config(&$config) {
  _cityselector_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cityselector_civicrm_install() {
  _cityselector_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cityselector_civicrm_enable() {
  _cityselector_civix_civicrm_enable();
}
