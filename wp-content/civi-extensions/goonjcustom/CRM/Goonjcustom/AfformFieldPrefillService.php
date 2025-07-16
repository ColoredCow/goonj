<?php

use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoService;

/**
 * Provides ability to prefill afform fields from encrypted token.
 *
 * @service goonjcustom.afform_prefill
 */
class CRM_Goonjcustom_AfformFieldPrefillService extends AutoService {

 public static function preprocess(\Civi\Angular\Manager $angular) {
  Civi::log()->debug('[AfformPrefill] Preprocess started.');

  $request = \CRM_Utils_Request::retrieve('token', 'String', CRM_Core_DAO::$_nullObject, FALSE, NULL, 'GET');

  if (!$request) {
    Civi::log()->warning('[AfformPrefill] No token found in URL.');
    return;
  }

  try {
    $decrypted = CRM_Utils_Crypt::decrypt($request);
    $data = json_decode($decrypted, true);
    Civi::log()->debug('[AfformPrefill] Token decrypted: ' . print_r($data, true));
  } catch (\Exception $e) {
    Civi::log()->error('[AfformPrefill] Token decryption failed: ' . $e->getMessage());
    return;
  }

  if (!is_array($data)) {
    Civi::log()->error('[AfformPrefill] Decrypted token is not valid JSON.');
    return;
  }

  $changeSet = \Civi\Angular\ChangeSet::create('injectPrefillValues')
    ->alterHtml(';\\.aff\\.html$;', function ($doc, $path) use ($data) {
      Civi::log()->debug("[AfformPrefill] Altering HTML template: {$path}");

      foreach (pq('af-field', $doc) as $afField) {
        $fieldName = $afField->getAttribute('name');

        if (!$fieldName) {
          continue;
        }

        if (array_key_exists($fieldName, $data)) {
          $fieldDefn = self::getFieldDefinition($afField);

          if (!$fieldDefn) {
            Civi::log()->warning("[AfformPrefill] No field definition for: {$fieldName}");
            continue;
          }

          $fieldDefn['afform_default'] = $data[$fieldName];
          $fieldDefn['readonly'] = true;

          pq($afField)->attr('defn', htmlspecialchars(\CRM_Utils_JS::writeObject($fieldDefn), ENT_COMPAT));
          Civi::log()->info("[AfformPrefill] Prefilled field '{$fieldName}' with value: " . json_encode($data[$fieldName]));
        } else {
          Civi::log()->debug("[AfformPrefill] Field '{$fieldName}' not in token data, skipping.");
        }
      }
    });

  $angular->add($changeSet);
  Civi::log()->debug('[AfformPrefill] ChangeSet injected successfully.');
}

  public static function getFieldDefinition($afField) {
    $existingFieldDefn = trim(pq($afField)->attr('defn') ?: '');

    if ($existingFieldDefn && $existingFieldDefn[0] !== '{') {
      return NULL;
    }

    return $existingFieldDefn ? \CRM_Utils_JS::getRawProps($existingFieldDefn) : [];
  }
}