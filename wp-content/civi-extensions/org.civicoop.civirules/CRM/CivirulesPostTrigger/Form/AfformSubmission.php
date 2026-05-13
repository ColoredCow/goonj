<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_Form_AfformSubmission extends CRM_CivirulesTrigger_Form_Form {

  /**
   * Overridden parent method to build form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');

    $afforms = \Civi\Api4\Afform::get(FALSE)
      ->addWhere('type:name', '=', 'form')
      ->addSelect('name', 'description', 'title')
      ->execute();
    foreach ($afforms as $afformDetail) {
      $selectList[] = [
        'id' => $afformDetail['name'],
        'text' => $afformDetail['title'],
        'description' => $afformDetail['description'],
      ];
    }

    $this->add('select2', 'rule_afform_select', E::ts('Select Form(s)'), $selectList, TRUE, ['class' => 'huge', 'multiple' => 'multiple']);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    if (isset($this->rule->trigger_params)) {
      $data = unserialize($this->rule->trigger_params);
      // Default to all record types. This creates backwards compatibility.
      $defaultValues['rule_afform_select'] = $data['rule_afform_select'] ?? 0;
    }

    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $this->triggerParams['rule_afform_select'] = $this->getSubmittedValue('rule_afform_select');
    parent::postProcess();
  }

}
