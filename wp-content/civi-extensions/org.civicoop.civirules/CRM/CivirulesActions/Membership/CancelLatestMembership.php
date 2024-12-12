<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * Specific SMC action to cancel membership (allowed with membership and group entity)
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 27 Aug 2024
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_CivirulesActions_Membership_CancelLatestMembership extends CRM_Civirules_Action {

  /**
   * Method to perform the actual action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return void
   * @throws
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData): void {
    $contactId = $triggerData->getContactId();
    if ($contactId) {
      $actionParams = $this->getActionParameters();
      try {
        $membership = \Civi\Api4\Membership::get(FALSE)
          ->addSelect('id')
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('membership_type_id', 'IN', $actionParams['membership_type_id'])
          ->addWhere('status_id', 'IN', $actionParams['membership_status_id'])
          ->addOrderBy('start_date', 'DESC')
          ->setLimit(1)->execute()->first();
        if ($membership['id']) {
          \Civi\Api4\Membership::update(FALSE)
            ->addWhere('id', '=', $membership['id'])
            ->addValue('status_id', CRM_Civirules_Utils::getCancelledMembershipStatusId())
            ->execute();
        }
      }
      catch (\API_Exception $ex) {
      }
    }
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return string
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId): string {
    return CRM_Utils_System::url('civicrm/civirule/form/action/cancellatestmembership', 'rule_action_id='
      . $ruleActionId);
  }

  /**
   * This function validates whether this action works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether an action is possible in the current setup.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule): bool {
    return $trigger->doesProvideEntities(['GroupContact']);
  }

  /**
   * Overridden parent method to set user friendly action text in form
   *
   * @return string
   */
  public function userFriendlyConditionParams(): string {
    $friendlyText = "Cancel Membership ";
    $actionParams = $this->getActionParameters();
    $statusElements = [];
    $typeElements = [];
    if ($actionParams['membership_type_id']) {
      foreach ($actionParams['membership_type_id'] as $typeId) {
        $typeElements[] = $this->getMembershipTypeName($typeId);
      }
      $friendlyText .= "of type(s): " . implode(", ", $typeElements);
    }
    if ($actionParams['membership_status_id']) {
      foreach ($actionParams['membership_status_id'] as $statusId) {
        $statusElements[] = $this->getMembershipStatusLabel($statusId);
      }
      $friendlyText .= " and of status(es): " . implode(", ", $statusElements);
    }
    return $friendlyText;
  }

  /**
   * Method to get the label of the membership status
   *
   * @param int $statusId
   * @return string
   */
  public function getMembershipStatusLabel(int $statusId): string {
    $label = [];
    try {
      $membershipStatus = \Civi\Api4\MembershipStatus::get(FALSE)
        ->addSelect('label')
        ->addWhere('id', '=', $statusId)
        ->execute()->first();
      if ($membershipStatus['label']) {
        $label = $membershipStatus['label'];
      }
    }
    catch (API_Exception $ex) {
    }
    return $label;
  }

  /**
   * Method to get the name of the membership type
   *
   * @param int $membershipTypeId
   * @return string
   */
  public function getMembershipTypeName(int $membershipTypeId): string {
    $name = [];
    try {
      $membershipType = \Civi\Api4\MembershipType::get(FALSE)
        ->addSelect('name')
        ->addWhere('id', '=', $membershipTypeId)
        ->execute()->first();
      if ($membershipType['name']) {
        $name = $membershipType['name'];
      }
    }
    catch (API_Exception $ex) {
    }
    return $name;
  }

}
