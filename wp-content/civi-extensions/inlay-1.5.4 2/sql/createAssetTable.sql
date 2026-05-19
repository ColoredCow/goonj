CREATE TABLE IF NOT EXISTS civicrm_inlay_asset (
  identifier VARCHAR(255) NOT NULL PRIMARY KEY,
  suffix VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'Random code to identify latest version, plus extension e.g. aabbccddee.jpg'
);
