<?php
/**
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_Form_Generic extends CRM_CivirulesTrigger_Form_Form {

  /**
   * Overridden parent method to build form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
  }

}
