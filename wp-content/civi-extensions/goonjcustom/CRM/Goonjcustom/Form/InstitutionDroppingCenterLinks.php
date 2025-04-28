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
class CRM_Goonjcustom_Form_InstitutionDroppingCenterLinks extends CRM_Core_Form {

  /**
   * Dropping center Id.
   *
   * @var int
   */
  public $_institutionDroppingCenterId;

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
    $this->_institutionDroppingCenterId = CRM_Utils_Request::retrieve('ccid', 'Positive', $this);
    $this->_contactId = CRM_Utils_Request::retrieve('gcid', 'Positive', $this);
    $this->_processingCenterId = CRM_Utils_Request::retrieve('puid', 'Positive', $this);

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Institution_Dropping_Center_Intent.Organization_Name', 'Institution_Dropping_Center_Intent.Institution_POC')
      ->addWhere('id', '=', $this->_institutionDroppingCenterId)
      ->execute()->single();

    $organizationId = $collectionCamps['Institution_Dropping_Center_Intent.Organization_Name'];
    $institutionPOCId = $collectionCamps['Institution_Dropping_Center_Intent.Institution_POC'];

    $this->_organization = Organization::get(FALSE)
      ->addSelect('display_name', 'email_primary.email', 'phone_primary.phone', 'address_primary.street_address')
      ->addWhere('id', '=', $organizationId)
      ->execute()->single();

    $this->_contact = Contact::get(FALSE)
      ->addSelect('email.email', 'phone.phone')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $institutionPOCId)
      ->execute()->single();

    $this->setTitle('Institution Dropping Center Dispatch Link');
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
   * Function to generate dropping center related links.
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
    $address = $organization['address_primary.street_address'];
    $pocEmail = $contact['email.email'];
    $pocContactNumber = $contact['phone.phone'];

    // Generate dropping center links.
    $links = [
        [
          'label' => 'Vehicle Dispatch',
          'url' => self::createUrl(
                '/institution-dropping-center-vehicle-dispatch',
                "Camp_Vehicle_Dispatch.Institution_Dropping_Center={$this->_institutionDroppingCenterId}" .
                "&Eck_Collection_Camp1={$this->_institutionDroppingCenterId}" .
                "&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent={$this->_processingCenterId}" .
                "&Camp_Vehicle_Dispatch.Filled_by={$contactId}" .
                "&Camp_Institution_Data.Name_of_the_institution={$nameOfInstitution}" .
                "&Camp_Institution_Data.Address=" . urlencode($address) .
                "&Camp_Institution_Data.Email={$pocEmail}" .
                "&Camp_Institution_Data.Contact_Number={$pocContactNumber}",
                $contactId
          ),
        ],
    ];

    $this->assign('institutionDroppingCenterLinks', $links);
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
