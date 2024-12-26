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
   * Preprocess .
   */
  public function preProcess() {
    $this->_urbanVisitId = CRM_Utils_Request::retrieve('ccid', 'Positive', $this);

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
    return [];
  }

  /**
   * Function to generate urban visit related links.
   *
   * @return void
   */
  public function generateLinks(): void {
    // Generate urban visit links without contact dependency.
    $links = [
      [
        'label' => 'Visit Outcome',
        'url' => self::createUrl(
          '/visit-outcome',
          "Eck_Institution_Visit1={$this->_urbanVisitId}"
        ),
      ],
      [
        'label' => 'Visit Feedback',
        'url' => self::createUrl(
          '/visit-feedback',
          "Eck_Institution_Visit1={$this->_urbanVisitId}"
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
   *
   * @return string
   */
  public static function createUrl($path, $params): string {
    $config = CRM_Core_Config::singleton();
    $url = $config->userFrameworkBaseURL . $path . '#?' . $params;

    return $url;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    // Add form elements.
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
    $this->generateLinks();

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
