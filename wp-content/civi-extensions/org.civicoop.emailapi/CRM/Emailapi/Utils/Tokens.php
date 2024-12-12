<?php

use Civi\Token\TokenProcessor;

/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_Emailapi_Utils_Tokens {

  /**
   * Returns a processed message. Meaning that all tokens are replaced with their value.
   * This message could then be used to generate the PDF.
   *
   * Re: non-contact entities to be used for tokens, this requires, for an entity called 'x'
   *
   * 1. $contactData['extra_data']['x'] => ['id' => 123, ... ]
   * 2. $contactData['x_id'] = 123
   *
   * From which it will extract arguments for TokenProcessor as follows:
   *
   * 1. 'xId' as an item in the $schema for TokenProcessor
   * 2. A key 'x' in the $context for TokenProcessor (the row data) with ['id' => 123, ...]
   * 3. A key 'xId' in the $context for TokenProcessor (the row data) => 123
   *
   * @param int $contactId
   * @param array $message
   * @param array $contactData
   *
   * @return string[]
   */
  public static function replaceTokens(int $contactId, array $message, array $contactData=[]): array {
    // Add the entities we want rendered into the schema, and record their primary keys.
    $schema['contactId'] = 'contactId';
    $context['contactId'] = $contactId;
    foreach ($contactData['extra_data'] ?? [] as $entity => $entityData) {
      $schema["{$entity}Id"] = "{$entity}Id";
      $context["{$entity}Id"] = $contactData["{$entity}_id"];
      $context[$entity] = $entityData;
    }
    foreach ($contactData as $contactDataKey => $entityID) {
      if (substr($contactDataKey, -3) === '_id') {
        $entity = substr($contactDataKey, 0, -3);
        $schema["{$entity}Id"] = "{$entity}Id";
        $context["{$entity}Id"] = $entityID;
      }
    }

    // Whether to enable Smarty evaluation.
    $useSmarty = ($params['disable_smarty'] ?? FALSE)
      ? FALSE
      : (defined('CIVICRM_MAIL_SMARTY') && CIVICRM_MAIL_SMARTY);

    $tokenProcessor = new TokenProcessor(\Civi::dispatcher(), [
      'controller' => __CLASS__,
      'schema' => $schema,
      'smarty' => $useSmarty,
    ]);

    // Populate the token processor.
    $tokenProcessor->addMessage('messageSubject', $message['messageSubject'], 'text/plain');
    $tokenProcessor->addMessage('html', $message['html'], 'text/html');
    $tokenProcessor->addMessage('text', $message['text'], 'text/plain');
    $row = $tokenProcessor->addRow($context);
    // Evaluate and render.
    $tokenProcessor->evaluate();
    foreach (['messageSubject', 'html', 'text'] as $component) {
      $rendered[$component] = $row->render($component);
    }

    return $rendered;
  }

  /**
   * Get the categories required for rendering tokens.
   *
   * @return array
   */
  protected static function getTokenCategories() {
    if (!isset(\Civi::$statics[__CLASS__]['token_categories'])) {
      $tokens = [];
      \CRM_Utils_Hook::tokens($tokens);
      \Civi::$statics[__CLASS__]['token_categories'] = array_keys($tokens);
    }
    return \Civi::$statics[__CLASS__]['token_categories'];
  }

  /**
   * @param $contact_id
   * @param $returnProperties
   *
   * @return mixed
   * @throws \CRM_Core_Exception
   */
  protected static function getTokenDetails($contact_id, $returnProperties=NULL) {
    $params = [];
    $params[] = [
      \CRM_Core_Form::CB_PREFIX . $contact_id,
      '=',
      1,
      0,
      0,
    ];
    if (empty($returnProperties)) {
      $fields = array_merge(array_keys(\CRM_Contact_BAO_Contact::exportableFields()),
        ['display_name', 'checksum', 'contact_id']
      );
      foreach ($fields as $val) {
        // The unavailable fields are not available as tokens, do not have a one-2-one relationship
        // with contacts and are expensive to resolve.
        // @todo see CRM-17253 - there are some other fields (e.g note) that should be excluded
        // and upstream calls to this should populate return properties.
        $unavailableFields = ['group', 'tag'];
        if (!in_array($val, $unavailableFields)) {
          $returnProperties[$val] = 1;
        }
      }
    }

    $custom = [];
    foreach ($returnProperties as $name => $dontCare) {
      $cfID = \CRM_Core_BAO_CustomField::getKeyID($name);
      if ($cfID) {
        $custom[] = $cfID;
      }
    }

    $details = \CRM_Contact_BAO_Query::apiQuery($params, $returnProperties, NULL, NULL, 0, 1, TRUE, FALSE, TRUE, \CRM_Contact_BAO_Query::MODE_CONTACTS, NULL, TRUE);
    $contactDetails = &$details[0];
    if (array_key_exists($contact_id, $contactDetails)) {
      if (!empty($contactDetails[$contact_id]['preferred_communication_method'])
      ) {
        $communicationPreferences = [];
        foreach ($contactDetails[$contact_id]['preferred_communication_method'] as $val) {
          if ($val) {
            $communicationPreferences[$val] = \CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'preferred_communication_method', $val);
          }
        }
        $contactDetails[$contact_id]['preferred_communication_method'] = implode(', ', $communicationPreferences);
      }

      foreach ($custom as $cfID) {
        if (isset($contactDetails[$contact_id]["custom_{$cfID}"])) {
          $contactDetails[$contact_id]["custom_{$cfID}"] = \CRM_Core_BAO_CustomField::displayValue($contactDetails[$contact_id]["custom_{$cfID}"], $cfID);
        }
      }

      // special case for greeting replacement
      foreach ([
                 'email_greeting',
                 'postal_greeting',
                 'addressee',
               ] as $val) {
        if (!empty($contactDetails[$contact_id][$val])) {
          $contactDetails[$contact_id][$val] = $contactDetails[$contact_id]["{$val}_display"];
        }
      }
    }
    return $contactDetails[$contact_id];
  }

}
