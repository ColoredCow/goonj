<?php
/**
 * BAO RuleAction for CiviRule Rule Action
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_BAO_CiviRulesRuleAction extends CRM_Civirules_DAO_CiviRulesRuleAction implements \Civi\Core\HookInterface {

  /**
   * Function to unserialize the CiviRulesRuleAction action_params
   *
   * @return array
   */
  public function unserializeParams(): array {
    if (!empty($this->action_params) && !is_array($this->action_params)) {
      return unserialize($this->action_params);
    }
    return [];
  }

  /**
   * Callback for hook_civicrm_post().
   * @param \Civi\Core\Event\PostEvent $event
   */
  public static function self_hook_civicrm_post(\Civi\Core\Event\PostEvent $event) {
    if (in_array($event->action, ['create' , 'edit'])) {
      CRM_Utils_Weight::correctDuplicateWeights('CRM_Civirules_DAO_CiviRulesRuleAction');
    }
  }

}
