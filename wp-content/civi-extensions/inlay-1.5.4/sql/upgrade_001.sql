-- Copied from auto_install.sql at 21 Feb 2022 14:43
-- /*******************************************************
-- *
-- * civicrm_inlay_config_set
-- *
-- * Holds sets of config defined against arbitrary schemas provided by inlay type extensions.
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_inlay_config_set` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique InlayConfigSet ID',
  `schema_name` varchar(64) NOT NULL COMMENT 'Machine name of schema that owns this, typically prefixed with the inlay extension shortname, e.g. inlaypay_stylesets',
  `set_name` varchar(128) NOT NULL COMMENT 'Machine name of this config item, where needed, must be unique within schema.',
  `label` varchar(255) COMMENT 'Human friendly admin name for the set',
  `config` longtext NOT NULL COMMENT 'JSON blob of config.',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `index_schema_setname`(schema_name, set_name)
)
;
