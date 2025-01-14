<?php

/**
 * @file
 * Form controller class.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Organization;

/**
 *
 */
class CRM_Goonjcustom_Form_InstitutionCollectionCampLinks extends CRM_Core_Form {

  /**
   * Collection Camp Id.
   *
   * @var int
   */
  public $_collectionCampId;

  /**
   * Goonj coordinator contact Id.
   *
   * @var int
   */
  public $_contactId;

  /**
   * Goonj processing unit center id.
   *
   * @var int
   */
  public $_processingCenterId;

  /**
   * Preprocess .
   */
  public function preProcess() {
    $this->_collectionCampId = CRM_Utils_Request::retrieve('ccid', 'Positive', $this);
    $this->_contactId = CRM_Utils_Request::retrieve('gcid', 'Positive', $this);
    $this->_processingCenterId = CRM_Utils_Request::retrieve('puid', 'Positive', $this);

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Institution_Collection_Camp_Intent.Organization_Name', 'Institution_Collection_Camp_Intent.Institution_POC')
      ->addWhere('id', '=', $this->_collectionCampId)
      ->execute()->single();
    
    $organizationId = $collectionCamps['Institution_Collection_Camp_Intent.Organization_Name'];
    $institutionPOCId = $collectionCamps['Institution_Collection_Camp_Intent.Institution_POC'];
    
    $this->_organization = Organization::get(FALSE)
      ->addSelect('display_name', 'Institute_Registration.Email_of_Institute', 'Institute_Registration.Contact_number_of_Institution', 'Institute_Registration.Address')
      ->addWhere('id', '=', $organizationId)
      ->execute()->single();

    $this->_contact = Contact::get(FALSE)
      ->addSelect('email.email', 'phone.phone')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $institutionPOCId)
      ->execute()->single();

    $this->setTitle('Institution Collection Camp Links');
    parent::preProcess();
  }

  /**
   * Set default values for the form.
   *
   * For edit/view mode the default values are retrieved from the database.
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaults = [];

    if (!empty($this->_contactId)) {
      $defaults['contact_id'] = $this->_contactId;
      $this->generateLinks($this->_contactId);
    }

    return $defaults;
  }

  /**
   * Function to generate collection camp related links.
   *
   * @param int $contactId
   *   Contact Id.
   *
   * @return void
   */
  public function generateLinks($contactId): void {

    $organization = $this->_organization;
    $contact = $this->_contact;

    $nameOfInstitution = $organization['display_name'];
    $address = $organization['Institute_Registration.Address'];
    $pocEmail = $contact['email.email'];
    $pocContactNumber = $contact['phone.phone'];

    // Generate collection camp links.
    $links = [
      [
        'label' => 'Vehicle Dispatch',
        'url' => self::createUrl(
          '/institution-camp-vehicle-dispatch-form',
          "Camp_Vehicle_Dispatch.Institution_Collection_Camp={$this->_collectionCampId}" .
          "&Eck_Collection_Camp1={$this->_collectionCampId}" .
          "&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent={$this->_processingCenterId}" .
          "&Camp_Vehicle_Dispatch.Filled_by={$contactId}" .
          "&Camp_Institution_Data.Name_of_the_institution={$nameOfInstitution}" .
          "&Camp_Institution_Data.Address={$address}" .
          "&Camp_Institution_Data.Email={$pocEmail}" .
          "&Camp_Institution_Data.Contact_Number={$pocContactNumber}",
          $contactId
        ),
      ],
      [
        'label' => 'Camp Outcome',
        'url' => self::createUrl(
          '/institution-camp-outcome-form',
          "Eck_Collection_Camp1={$this->_collectionCampId}" .
          "&Camp_Outcome.Filled_By={$contactId}",
          $contactId
        ),
      ],
    ];

    $this->assign('institutionCollectionCampLinks', $links);
  }

  /**
   * Generate an authenticated URL for viewing this form.
   *
   * @param string $path
   * @param string $params
   * @param int $contactId
   *
   * @return string
   *
   * @throws \Civi\Crypto\Exception\CryptoException
   */
  public static function createUrl($path, $params, $contactId): string {
    $expires = \CRM_Utils_Time::time() +
      (\Civi::settings()->get('checksum_timeout') * 24 * 60 * 60);

    /** @var \Civi\Crypto\CryptoJwt $jwt */
    $jwt = \Civi::service('crypto.jwt');

    $bearerToken = "Bearer " . $jwt->encode([
      'exp' => $expires,
      'sub' => "cid:" . $contactId,
      'scope' => 'authx',
    ]);

    $params = "{$params}&_authx={$bearerToken}&_authxSes=1";
    // $url = \CRM_Utils_System::url($path, $params, TRUE, NULL, FALSE, TRUE);
    $config = CRM_Core_Config::singleton();
    $url = $config->userFrameworkBaseURL . $path . '#?' . $params;

    return $url;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {

    // Add form elements.
    $this->addEntityRef('contact_id', ts('Contact to send'), [], TRUE);

    $this->addButtons([
    [
      'type' => 'submit',
      'name' => \CRM_Goonjcustom_ExtensionUtil::ts('Generate Links'),
      'isDefault' => TRUE,
    ],
    ]);

    // Export form elements.
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   *
   */
  public function postProcess(): void {
    $values = $this->exportValues();

    if (!empty($values['contact_id'])) {
      $this->generateLinks($values['contact_id']);
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames(): array {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
