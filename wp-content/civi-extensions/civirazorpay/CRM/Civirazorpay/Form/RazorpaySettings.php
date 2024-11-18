<?php

/**
 * Form controller class.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Civirazorpay_Form_RazorpaySettings extends CRM_Core_Form {

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {

    $existingPlans = Civi::settings()->get('razorpay_subscription_plans');
    $existingPlansJson = json_encode($existingPlans, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $this->add('textarea', 'razorpay_subscription_plans', ts('Razorpay Subscription Plans'), [
      'rows' => 10,
      'cols' => 80,
    ]);
    $this->setDefaults([
      'razorpay_subscription_plans' => $existingPlansJson,
    ]);

    $this->addButtons([
                [
                  'type' => 'submit',
                  'name' => ts('Save'),
                  'isDefault' => TRUE,
                ],
                [
                  'type' => 'cancel',
                  'name' => ts('Cancel'),
                ],
    ]);

    parent::buildQuickForm();
  }

  /**
   *
   */
  public function postProcess(): void {
    $values = $this->exportValues();

    $plansJson = $values['razorpay_subscription_plans'];
    $plans = json_decode($plansJson, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      CRM_Core_Error::statusBounce(ts('Invalid JSON format for subscription plans.'));
    }

    Civi::settings()->set('razorpay_subscription_plans', $plans);

    CRM_Core_Session::setStatus(ts('Razorpay subscription plans saved successfully.'), ts('Saved'), 'success');
  }

}
