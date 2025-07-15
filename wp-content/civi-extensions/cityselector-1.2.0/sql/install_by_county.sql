-- Cities by State/Province
CREATE TABLE `civicrm_city` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'City ID',
  `name` varchar(255) COMMENT 'Name of City',
  `code` varchar(64) NULL COMMENT 'City code',
  `county_id` int unsigned NOT NULL COMMENT 'ID of County that City belongs',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `UI_name_county_id`(name, county_id),
  CONSTRAINT FK_civicrm_city_county_id FOREIGN KEY (`county_id`) REFERENCES `civicrm_county`(`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
