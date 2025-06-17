<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 * @property string $id 
 * @property string $name 
 * @property string $label 
 * @property string $trigger_id 
 * @property string $trigger_params 
 * @property bool|string $is_active 
 * @property string $description 
 * @property string $help_text 
 * @property string $created_date 
 * @property string $created_user_id 
 * @property string $modified_date 
 * @property string $modified_user_id 
 * @property bool|string $is_debug 
 */
class CRM_Civirules_DAO_CiviRulesRule extends CRM_Civirules_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civirule_rule';

}
