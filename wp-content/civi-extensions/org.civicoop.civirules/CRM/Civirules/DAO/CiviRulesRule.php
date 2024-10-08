<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from org.civicoop.civirules/xml/schema/CRM/Civirules/Rule.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:4c79ce4dff3eb805d8b7015a821d3fd5)
 */
use CRM_Civirules_ExtensionUtil as E;

/**
 * Database access object for the CiviRulesRule entity.
 */
class CRM_Civirules_DAO_CiviRulesRule extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civirule_rule';

  /**
   * Field to show when displaying a record.
   *
   * @var string
   */
  public static $_labelField = 'label';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique Rule ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * @var string|null
   *   (SQL type: varchar(80))
   *   Note that values will be retrieved from the database as a string.
   */
  public $name;

  /**
   * @var string|null
   *   (SQL type: varchar(128))
   *   Note that values will be retrieved from the database as a string.
   */
  public $label;

  /**
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $trigger_id;

  /**
   * @var string|null
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $trigger_params;

  /**
   * @var bool|string
   *   (SQL type: tinyint)
   *   Note that values will be retrieved from the database as a string.
   */
  public $is_active;

  /**
   * @var string|null
   *   (SQL type: varchar(255))
   *   Note that values will be retrieved from the database as a string.
   */
  public $description;

  /**
   * @var string|null
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $help_text;

  /**
   * When was this item created
   *
   * @var string|null
   *   (SQL type: date)
   *   Note that values will be retrieved from the database as a string.
   */
  public $created_date;

  /**
   * FK to Contact ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $created_user_id;

  /**
   * When was this item modified
   *
   * @var string|null
   *   (SQL type: date)
   *   Note that values will be retrieved from the database as a string.
   */
  public $modified_date;

  /**
   * FK to Contact ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $modified_user_id;

  /**
   * @var bool|string|null
   *   (SQL type: tinyint)
   *   Note that values will be retrieved from the database as a string.
   */
  public $is_debug;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civirule_rule';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Civi Rules Rules') : E::ts('Civi Rules Rule');
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('ID'),
          'description' => E::ts('Unique Rule ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.id',
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'name' => [
          'name' => 'name',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Name'),
          'maxlength' => 80,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.name',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'label' => [
          'name' => 'label',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Label'),
          'maxlength' => 128,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.label',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'trigger_id' => [
          'name' => 'trigger_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Trigger ID'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.trigger_id',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'FKClassName' => 'CRM_Civirules_DAO_CiviRulesTrigger',
          'html' => [
            'type' => 'Select',
            'label' => E::ts("Trigger"),
          ],
          'pseudoconstant' => [
            'table' => 'civirule_trigger',
            'keyColumn' => 'id',
            'labelColumn' => 'label',
            'nameColumn' => 'name',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'trigger_params' => [
          'name' => 'trigger_params',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Trigger Params'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.trigger_params',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'add' => NULL,
        ],
        'is_active' => [
          'name' => 'is_active',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => E::ts('Enabled'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.is_active',
          'default' => '1',
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'html' => [
            'type' => 'CheckBox',
            'label' => E::ts("Enabled"),
          ],
          'add' => NULL,
        ],
        'description' => [
          'name' => 'description',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Description'),
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.description',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'help_text' => [
          'name' => 'help_text',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Help Text'),
          'rows' => 4,
          'cols' => 60,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.help_text',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'html' => [
            'type' => 'TextArea',
          ],
          'add' => NULL,
        ],
        'created_date' => [
          'name' => 'created_date',
          'type' => CRM_Utils_Type::T_DATE,
          'title' => E::ts('Created Date'),
          'description' => E::ts('When was this item created'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.created_date',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'created_user_id' => [
          'name' => 'created_user_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Created User ID'),
          'description' => E::ts('FK to Contact ID'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.created_user_id',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
          'html' => [
            'type' => 'EntityRef',
            'label' => E::ts("Created By"),
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'modified_date' => [
          'name' => 'modified_date',
          'type' => CRM_Utils_Type::T_DATE,
          'title' => E::ts('Modified Date'),
          'description' => E::ts('When was this item modified'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.modified_date',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'modified_user_id' => [
          'name' => 'modified_user_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Modified User ID'),
          'description' => E::ts('FK to Contact ID'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.modified_user_id',
          'default' => NULL,
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
          'html' => [
            'type' => 'EntityRef',
            'label' => E::ts("Modified By"),
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'is_debug' => [
          'name' => 'is_debug',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => E::ts('Is Debug'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civirule_rule.is_debug',
          'default' => '0',
          'table_name' => 'civirule_rule',
          'entity' => 'CiviRulesRule',
          'bao' => 'CRM_Civirules_DAO_CiviRulesRule',
          'localizable' => 0,
          'add' => NULL,
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, '_rule', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, '_rule', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
