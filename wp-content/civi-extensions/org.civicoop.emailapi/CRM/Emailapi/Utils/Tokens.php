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
   * @param string $preferred_language
   *
   * @return string[]
   */
  public static function replaceTokens(int $contactId, array $message, array $contactData=[], string $preferred_language = NULL): array {
    // Add the entities we want rendered into the schema, and record their primary keys.
    $schema['contactId'] = 'contactId';
    $context['contactId'] = $contactId;
    $contactData = CRM_Emailapi_Utils_ContributionData::enhanceWithContributionData($contactData);
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

    // Set language on row if necessary
    $swapLocale = NULL;
    if ($preferred_language) {
      $row->context('locale', $preferred_language);
      $swapLocale = \CRM_Utils_AutoClean::swapLocale($preferred_language);
    }

    // Evaluate and render.
    $tokenProcessor->evaluate();
    foreach (['messageSubject', 'html', 'text'] as $component) {
      $rendered[$component] = $row->render($component);
    }

    unset($swapLocale);
    return $rendered;
  }

}
