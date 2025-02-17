<?php

require_once 'prettyworkflowmessages.civix.php';
// phpcs:disable
use CRM_Prettyworkflowmessages_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function prettyworkflowmessages_civicrm_config(&$config) {
  _prettyworkflowmessages_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function prettyworkflowmessages_civicrm_install() {
  _prettyworkflowmessages_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function prettyworkflowmessages_civicrm_enable() {
  _prettyworkflowmessages_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function prettyworkflowmessages_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function prettyworkflowmessages_civicrm_navigationMenu(&$menu) {
  _prettyworkflowmessages_civix_insert_navigation_menu($menu, 'Administer/Communications', array(
    'label' => E::ts('Pretty Workflow Messages'),
    'name' => 'pretty_workflow_messages_settings',
    'url' => 'civicrm/pretty-workflow-messages-settings',
    'permission' => 'administer CiviCRM',
    'separator' => 1,
  ));
  _prettyworkflowmessages_civix_navigationMenu($menu);
}

/**
 * Implement hook_civicrm_tokens
 *
 * Let's add the system workflow message tokens to the token list
 *
 * @param $tokens list of tokens
 *
 * @return void
 */
function prettyworkflowmessages_civicrm_tokens(&$tokens) {
  $tokens['system'] = [
    'system.workflow_message_subject' =>
    'System Workflow Message Subject',
    'system.workflow_message_html' =>
    'System Workflow Message HTML Content',
    'system.workflow_message_text' => 'System Workflow Message Text Content',
  ];
}

/**
 * Implement hook_civicrm_alterMailContent
 *
 * Let's add client branding for the system workflow messages that are sent
 *
 * @param $content content object that contains message object that's been sent
 *
 * @return void
 */
function prettyworkflowmessages_civicrm_alterMailContent(&$content) {
  // let's check if we are sending system message, if not then return early
  // $content['workflow_name'] is set for all system messages except automated messages
  // $content['template_type'] is empty for all system messages and automated messages
  // also skip for civimail mailings
  if (!empty($content['mailingID']) || (empty($content['workflow_name']) && !empty($content['template_type'])) || $content['workflow_name'] == 'UNKNOWN') {
    // return early if it's not a system message
    return;
  }

  // get the branding template from the settings
  // Note that settings api 4 is not working for custom settings hence directly using
  // recommended funtion
  $brandingTemplateId =
    Civi::settings()->get('pretty_workflow_template');;

  // if no branding template is set, do nothing and return early
  if (!$brandingTemplateId) {
    return;
  }

  // get the branding template details
  $messageTemplates = \Civi\Api4\MessageTemplate::get(FALSE)
    ->addSelect('msg_subject', 'msg_html', 'msg_text')
    ->addWhere('id', '=', $brandingTemplateId)
    ->execute();

  $prettySubject = $messageTemplates[0]['msg_subject'];
  $prettyHTML = $messageTemplates[0]['msg_html'];
  $prettyText = $messageTemplates[0]['msg_text'];

  // let's make the boring system workflow messages pretty :)
  // replace subject
  $content['subject'] =
    replaceWorkflowMessage($prettySubject, $content['subject'], 'workflow_message_subject');

  // plain text content
  $content['text'] =
  replaceWorkflowMessage($prettyText, $content['text'], 'workflow_message_text');

  // for html content we should only get body as CiviCRM system messages include <html> & <body> tag
  preg_match("/<body[^>]*>(.*?)<\/body>/is",
    $content['html'],
    $matches
  );

  // if html content does not contain the body tag, we use the content directly
  if (!$matches) {
    $matches = [$content['html'], $content['html']];
  }

  // add literal tags for style tag, this is to prevent smarty errors
  // mosaico template adds style tag to the html content
  // if should escape only if not already escaped
  if (strpos($prettyHTML, '{literal}<style') === FALSE && strpos($prettyHTML, '<!--{literal}-->') === FALSE) {
    $prettyHTML = str_replace('<style type="text/css">', '<!--{literal}--><style type="text/css">', $prettyHTML);
    $prettyHTML = str_replace('</style>', '</style><!--{/literal}-->', $prettyHTML);
  }

  $content['html'] =
    replaceWorkflowMessage($prettyHTML, $matches[1], 'workflow_message_html');

  // check if pretty workflow includes html tags
  preg_match("/<html[^>]*>(.*?)<\/html>/is",
    $prettyHTML,
    $htmlExists
  );

  // add html wrapper only if it's missing
  if (!$htmlExists) {
    // let's add html and body tag so we generate valid html
    $content['html'] = convertToHTMLDocument($content['html']);
  }
}

/**
 * Replace special pretty token for workflow messages
 *
 * @param string $content string with tokens to be replaced
 * @param string $tokenValue token value
 * @param string $tokenString token that needs to be replaced
 *
 * @return string $processedString processed string
 */
function replaceWorkflowMessage(&$content, $tokenValue, $tokenString) {
  // set the return string as workflow message content
  $processedString = $tokenValue;
  if (CRM_Utils_Token::token_match('system', $tokenString, $content)) {
    CRM_Utils_Token::token_replace('system', $tokenString, $tokenValue, $content);

    // if branding tokens where defined then let's return branded content
    $processedString = $content;
  }
  return $processedString;
}

/**
 * Function to enclose the html content inside <html></html>
 *
 * @param string $htmlContent html content
 *
 * @return string $htmlContent valid html document
 */
function convertToHTMLDocument($htmlContent) {
  $topElements = '
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
      <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title></title>
      </head>
      <body>
      ';
  $bottomElements = '
      </body>
    </html>
  ';

  $htmlContent = $topElements . $htmlContent . $bottomElements;

  return $htmlContent;
}
