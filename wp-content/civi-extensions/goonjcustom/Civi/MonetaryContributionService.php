<?php

namespace Civi;

use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\GroupContact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;

/**
 *
 */
class MonetaryContributionService extends AutoSubscriber {
  use CollectionSource;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['assignChapterGroupToIndividualForContribution'],
        ['updateCampaignForCollectionSourceContribution'],
        ['generateInvoiceIdForContribution'],
      ],
      '&hook_civicrm_buildForm' => [
        ['autofillMonetaryFormSource'],
        ['autofillFinancialType'],
        ['autofillReceiptFrom'],
      ],
    ];
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividualForContribution(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Contribution') {
      return FALSE;
    }

    if (self::$individualId !== $objectRef->contact_id || !$objectRef->contact_id) {
      return FALSE;
    }

    $contactId = $objectRef->contact_id;

    $address = Address::get(FALSE)
      ->addSelect('state_province_id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute()->first();

    $stateId = $address['state_province_id'] ?? NULL;

    if ($stateId) {
      $groupId = self::getChapterGroupForState($stateId);

      if ($groupId & self::$individualId) {
        GroupContact::create(FALSE)
          ->addValue('contact_id', self::$individualId)
          ->addValue('group_id', $groupId)
          ->addValue('status', 'Added')
          ->execute();
      }
    }
  }

  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  public static function updateCampaignForCollectionSourceContribution(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'Contribution' || !$objectRef->id || $op !== 'edit') {
      return;
    }

    try {
      $contributionId = $objectRef->id;
      if (!$contributionId) {
        return;
      }

      $contribution = Contribution::get(FALSE)
        ->addSelect('Contribution_Details.Source')
        ->addWhere('id', '=', $contributionId)
        ->execute()->first();

      if (!$contribution) {
        return;
      }

      $sourceID = $contribution['Contribution_Details.Source'];

      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Collection_Camp_Intent_Details.Campaign')
        ->addWhere('id', '=', $sourceID)
        ->execute()->single();

      if (!$collectionCamp) {
        return;
      }

      $campaignId = $collectionCamp['Collection_Camp_Intent_Details.Campaign'];

      if (!$campaignId) {
        return;
      }

      if (isset($objectRef->campaign_id) && $objectRef->campaign_id == $campaignId) {
        return;
      }

      Contribution::update(FALSE)
        ->addValue('campaign_id', $campaignId)
        ->addWhere('id', '=', $contributionId)
        ->execute();

    }

    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred in updateCampaignForCollectionSourceContribution.", [
        'Message' => $e->getMessage(),
        'Stack Trace' => $e->getTraceAsString(),
      ]);
    }

  }

  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  public static function generateInvoiceIdForContribution(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'Contribution' || !$objectRef->id) {
      return;
    }

    try {
      $contributionId = $objectRef->id;
      if (!$contributionId) {
        return;
      }

      if (!empty($objectRef->invoice_id)) {
        return;
      }

      // Generate a unique invoice ID.
      // Current timestamp.
      $timestamp = time();
      // Generate a unique ID based on the current time in microseconds.
      $uniqueId = uniqid();
      $invoiceId = hash('sha256', $timestamp . $uniqueId);

      Contribution::update(TRUE)
        ->addValue('invoice_id', $invoiceId)
        ->addWhere('id', '=', $contributionId)
        ->execute();

    }
    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred in generateInvoiceIdForContribution.", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
    }
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * Auto-fills custom fields in the form based on the provided parameters.
   *
   * @param string $formName
   *   The name of the form being built.
   * @param object $form
   *   The form object.
   */
  public function autofillMonetaryFormSource($formName, &$form) {
    if (!in_array($formName, ['CRM_Contribute_Form_Contribution', 'CRM_Custom_Form_CustomDataByType'])) {
      return;
    }

    $campSource = NULL;
    $puSource = NULL;

    // Fetching custom field for collection source.
    $sourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'Source')
      ->execute()->single();

    $sourceFieldId = 'custom_' . $sourceField['id'];

    // Fetching custom field for goonj office.
    $puSourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'PU_Source')
      ->execute()->single();

    $puSourceFieldId = 'custom_' . $puSourceField['id'];

    $eventSourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'Events')
      ->execute()->single();

    $eventSourceFieldId = 'custom_' . $eventSourceField['id'];

    // Determine the parameter to use based on the form and query parameters.
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      // If the query parameter is present, update session and clear the other session value.
      if (isset($_GET[$sourceFieldId])) {
        $campSource = $_GET[$sourceFieldId];
        $_SESSION['camp_source'] = $campSource;
        // Ensure only one session value is active.
        unset($_SESSION['pu_source']);
        unset($_SESSION['eventSource']);
      }
      elseif (isset($_GET[$puSourceFieldId])) {
        $puSource = $_GET[$puSourceFieldId];
        $_SESSION['pu_source'] = $puSource;
        // Ensure only one session value is active.
        unset($_SESSION['camp_source']);
        unset($_SESSION['eventSource']);
      }
      elseif (isset($_GET[$eventSourceFieldId])) {
        $eventSource = $_GET[$eventSourceFieldId];
        $_SESSION['eventSource'] = $eventSource;
        // Ensure only one session value is active.
        unset($_SESSION['camp_source']);
        unset($_SESSION['pu_source']);
      }
      else {
        // Clear session if neither parameter is present.
        unset($_SESSION['camp_source'], $_SESSION['pu_source'], $_SESSION['eventSource']);
      }
    }
    else {
      // For other forms, retrieve from session if it exists.
      $campSource = $_SESSION['camp_source'] ?? NULL;
      $puSource = $_SESSION['pu_source'] ?? NULL;
      $eventSource = $_SESSION['eventSource'] ?? NULL;
    }

    // Autofill logic for the custom fields.
    if ($formName === 'CRM_Custom_Form_CustomDataByType') {
      $autoFillData = [];
      if (!empty($campSource)) {
        $autoFillData[$sourceFieldId] = $campSource;
      }
      elseif (!empty($puSource)) {
        $autoFillData[$puSourceFieldId] = $puSource;
      }
      elseif (!empty($eventSource)) {
        $autoFillData[$eventSourceFieldId] = $eventSource;
      }
      else {
        // Clear values explicitly if neither source is found.
        $autoFillData[$sourceFieldId] = NULL;
        $autoFillData[$puSourceFieldId] = NULL;
        $autoFillData[$eventSourceFieldId] = NULL;
      }

      // Set default values for the specified fields.
      foreach ($autoFillData as $fieldName => $value) {
        if (isset($form->_elements) && is_array($form->_elements)) {
          foreach ($form->_elements as $element) {
            if (!isset($element->_attributes['data-api-params'])) {
              continue;
            }
            $apiParams = json_decode($element->_attributes['data-api-params'], TRUE);
            if ($apiParams['fieldName'] === 'Contribution.Contribution_Details.Source') {
              $formFieldName = $fieldName . '_-1';
              $form->setDefaults([$formFieldName => $value ?? '']);
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * Auto-fills custom fields in the form based on the provided parameters.
   *
   * @param string $formName
   *   The name of the form being built.
   * @param object $form
   *   The form object.
   */
  public function autofillFinancialType($formName, &$form) {
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      if ($form->getAction() == \CRM_Core_Action::ADD) {
        // Set the default value for 'financial_type_id'.
        $defaults = [];
        // Example: 'Donation' (adjust ID as per your requirement)
        $defaults['financial_type_id'] = self::DEFAULT_FINANCIAL_TYPE_ID;
        $form->setDefaults($defaults);
      }
    }
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * Auto-fills custom fields in the form based on the provided parameters.
   *
   * @param string $formName
   *   The name of the form being built.
   * @param object $form
   *   The form object.
   */
  public function autofillReceiptFrom($formName, &$form) {
    // Check if the form is the Contribution form.
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      if ($form->getAction() == \CRM_Core_Action::ADD) {
        // Set the default value for 'Receipt From'.
        $defaults = [];
        $defaults['from_email_address'] = self::ACCOUNTS_TEAM_EMAIL;
        $form->setDefaults($defaults);
      }
    }
  }

}
