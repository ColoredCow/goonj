<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_CivirulesConditions_AfformSubmission_Status extends CRM_CivirulesConditions_Generic_Status {

  /**
   * The entity name (eg. Membership)
   * @return string
   */
  protected function getEntity() {
    return 'AfformSubmission';
  }

  /**
   * The aliased (human readable) entity name (eg. FormBuilder Submission)
   *
   * @return string
   */
  protected function getEntityAlias(): string {
    return 'FormBuilder Submission';
  }

  /**
   * The entity status field (eg. membership_status_id)
   * @return string
   */
  public function getEntityStatusFieldName() {
    return 'status_id';
  }

  /**
   * Returns an array of statuses as [ id => label ]
   * @param bool $active
   * @param bool $inactive
   *
   * @return array
   */
  public static function getEntityStatusList($active = TRUE, $inactive = FALSE) {
    return array_column(Civi::entity('AfformSubmission')->getOptions('status_id'), 'label', 'id');
  }

}
