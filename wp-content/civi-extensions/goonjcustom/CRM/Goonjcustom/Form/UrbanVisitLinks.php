<?php

/**
 * @file
 * Form controller class.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */

/**
 *
 */
class CRM_Goonjcustom_Form_UrbanVisitLinks extends CRM_Core_Form {

  /**
   * Urban Visit Id.
   *
   * @var int
   */
  public $_urbanVisitId;

  /**
   * Goonj visit Id.
   *
   * @var int
   */
  public $_contactId;

  /**
   * Preprocess .
   */
  public function preProcess() {
    $this->_urbanVisitId = CRM_Utils_Request::retrieve('ccid', 'Positive', $this);
    $this->_contactId = CRM_Utils_Request::retrieve('gcid', 'Positive', $this);

    $this->setTitle('Urban Visit Links');
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
   * Function to generate urban visit related links.
   *
   * @param int $contactId
   *   Contact Id.
   */
  public function generateLinks($contactId): void {
    $links = [
      [
        'label' => 'Visit Outcome',
        'url' => self::createUrl(
          '/visit-outcome',
          "Eck_Institution_Visit1={$this->_urbanVisitId}&Urban_Visit_Outcome.Visit_Id={$this->_urbanVisitId}&Urban_Visit_Outcome.Filled_By={$contactId}",
          $contactId
        ),
      ],
      [
        'label' => 'Visit Feedback',
        'url' => self::createUrl(
          '/visit-feedback',
          "Eck_Institution_Visit1={$this->_urbanVisitId}",
          $contactId
        ),
      ],
    ];

    $this->assign('UrbanVisitLinks', $links);
  }

  /**
   * Generate an authenticated URL for viewing this form.
   *
   * @param string $path
   * @param string $params
   * @param int $contactId
   *
   * @return string
   */
  public static function createUrl($path, $params, $contactId): string {
    $config = CRM_Core_Config::singleton();
    $url = $config->userFrameworkBaseURL . $path . '#?' . $params;

    return $url;
  }

  /**
   * Build the form.
   */
  public function buildQuickForm(): void {
    // Add a contact selector (dropdown).
    $this->addEntityRef('contact_id', ts('Contact'), ['entity' => 'contact'], TRUE);

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
   * Post-process the form submission.
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
