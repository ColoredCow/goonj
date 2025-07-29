-- Cities by State/Province
CREATE TABLE `civicrm_city` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'City ID',
  `name` varchar(255) COMMENT 'Name of City',
  `code` varchar(64) NULL COMMENT 'City code',
  `state_province_id` int unsigned NOT NULL COMMENT 'ID of State/Province that City belongs',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `UI_name_state_province_id`(name, state_province_id),
  CONSTRAINT FK_civicrm_city_state_province_id FOREIGN KEY (`state_province_id`) REFERENCES `civicrm_state_province`(`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
