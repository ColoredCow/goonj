<?php
// Small Angular module that provides the Event-Type cascading filter used by
// the Afform `afformGoonjEventRegistration`. The Afform itself (layout +
// SavedSearch + SearchDisplay) lives in the admin/database layer and is edited
// via FormBuilder. This module is the minimum that FormBuilder cannot express.
return [
  'js' => [
    'ang/goonjEventRegistration.js',
  ],
  'requires' => ['crmUi', 'crmUtil', 'api4'],
  'bundles' => ['bootstrap3'],
  'basePages' => [],
];
