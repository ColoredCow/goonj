<?php

namespace Civi;

use Civi\Api4\Event;
use Civi\Api4\EckEntity;
use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Core\Service\AutoSubscriber;

/**
 * PROTOTYPE: monetary ask after a material contribution.
 *
 * - Builds the personalised contribution-page link for a material contribution
 *   activity (thank-you page card + acknowledgement email token).
 * - Pre-selects the amount on the contribution page when the link carries ?amt=.
 */
class MaterialMonetaryAskService extends AutoSubscriber {

  const OPT_IN_FIELD = 'Material_Contribution.Do_You_wish_to_do_Monetary_Money_Contribution_';
  const AMOUNT_PARAM = 'amt';
  const SOURCE_TAG_PARAM = 'src';
  const ASK_AMOUNTS = [200, 500, 1000];
  const DEFAULT_ASK_AMOUNT = 500;

  /**
   * Contribution page (by name) and public path of the material-journey form.
   */
  const MATERIAL_JOURNEY_PAGE_NAME = 'Material_Journey_Contribution';
  const CONTRIBUTE_PATH = '/contribute/material-journey/';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_buildForm' => [
        ['prefillAmountFromUrl'],
        ['setUpMaterialJourneyForm'],
      ],
    ];
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * Material-journey contribution page only: banner naming the camp / office /
   * event the material went to, plus the page's own JS and CSS.
   */
  public static function setUpMaterialJourneyForm($formName, &$form) {
    if ($formName !== 'CRM_Contribute_Form_Contribution_Main'
      || ($form->_values['name'] ?? '') !== self::MATERIAL_JOURNEY_PAGE_NAME) {
      return;
    }

    $form->updateAttributes(['class' => trim(($form->getAttribute('class') ?? '') . ' goonj-mj')]);

    $sourceName = self::getSourceNameFromUrl();
    $banner = '<div class="goonj-mj-banner">'
      . '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#b8733a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3.3 7.5L12 12l8.7-4.5M12 22V12"/></svg>'
      . '<div><b>' . ts('Supporting the sorting, packing and transport of your material contribution') . '</b>'
      . '<small>' . ts('Linked to the material you just gave') . ($sourceName ? ' · ' . htmlspecialchars($sourceName) : '') . '</small></div>'
      . '</div>';
    \CRM_Core_Region::instance('contribution-main-not-you-block')->add([
      'markup' => $banner,
      'weight' => -10,
    ]);

    \Civi::resources()
      ->addVars('goonjMaterialJourney', [
        // Rupees per kg sorted, packed and shipped. NULL until Goonj shares it.
        'ratePerKg' => NULL,
        'consentLabel' => ts('I confirm the details are correct and I am an Indian citizen. I have read the privacy notice and consent as per the DPDP Act.'),
        'addressNote' => ts('Address is needed to issue the 80G certificate.'),
      ])
      ->addScriptFile('goonjcustom', 'js/material-journey.js')
      ->addStyleFile('goonjcustom', 'css/material-journey.css');
  }

  /**
   * Name of the camp / office / event in the link's source parameter.
   */
  private static function getSourceNameFromUrl(): ?string {
    $fields = CustomField::get(FALSE)
      ->addSelect('id', 'name')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', 'IN', ['Source', 'PU_Source', 'Events'])
      ->execute()->indexBy('name');

    $value = fn($name) => isset($fields[$name]) ? \CRM_Utils_Request::retrieveValue('custom_' . $fields[$name]['id'], 'Positive', NULL, FALSE, 'GET') : NULL;

    if ($officeId = $value('PU_Source')) {
      return Contact::get(FALSE)->addSelect('display_name')->addWhere('id', '=', $officeId)->execute()->first()['display_name'] ?? NULL;
    }
    if ($campId = $value('Source')) {
      return EckEntity::get('Collection_Camp', FALSE)->addSelect('title')->addWhere('id', '=', $campId)->execute()->first()['title'] ?? NULL;
    }
    if ($eventId = $value('Events')) {
      return Event::get(FALSE)->addSelect('title')->addWhere('id', '=', $eventId)->execute()->first()['title'] ?? NULL;
    }
    return NULL;
  }

  /**
   * Did the contributor tick the opt-in checkbox on the material form?
   */
  public static function hasOptedIn(int $activityId): bool {
    $activity = Activity::get(FALSE)
      ->addSelect(self::OPT_IN_FIELD)
      ->addWhere('id', '=', $activityId)
      ->execute()->first();

    return !empty($activity[self::OPT_IN_FIELD]);
  }

  /**
   * Contribution-page link for the contributor of a material contribution.
   *
   * Same shape as the thank-you page "Monetary Contribution" button:
   * /contribute/?<source field>=<source id>&cid=<contact>&cs=<checksum>.
   */
  public static function getMonetaryUrl(int $activityId, ?int $amount = NULL, ?string $sourceTag = NULL, int $checksumTtlDays = 7): ?string {
    $activity = Activity::get(FALSE)
      ->addSelect(
        'source_contact_id',
        'Material_Contribution.Collection_Camp',
        'Material_Contribution.Institution_Collection_Camp',
        'Material_Contribution.Dropping_Center',
        'Material_Contribution.Institution_Dropping_Center',
        'Material_Contribution.Goonj_Office',
        'Material_Contribution.Event'
      )
      ->addWhere('id', '=', $activityId)
      ->addWhere('activity_type_id:name', '=', 'Material Contribution')
      ->execute()->first();

    if (empty($activity['source_contact_id'])) {
      return NULL;
    }

    [$sourceFieldName, $sourceId] = self::resolveSource($activity);
    if (!$sourceFieldName || !$sourceId) {
      return NULL;
    }

    $sourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', $sourceFieldName)
      ->execute()->first();
    if (empty($sourceField['id'])) {
      return NULL;
    }

    $contactId = (int) $activity['source_contact_id'];
    $checksum = Contact::getChecksum(FALSE)
      ->setContactId($contactId)
      ->setTtl($checksumTtlDays * 24 * 60 * 60)
      ->execute()->first()['checksum'];

    $params = [
      'custom_' . $sourceField['id'] => $sourceId,
      'cid' => $contactId,
      'cs' => $checksum,
    ];
    if ($amount) {
      $params[self::AMOUNT_PARAM] = $amount;
    }
    if ($sourceTag) {
      $params[self::SOURCE_TAG_PARAM] = $sourceTag;
    }

    return self::publicBaseUrl() . self::CONTRIBUTE_PATH . '?' . http_build_query($params);
  }

  /**
   * Which Contribution_Details source field + id to stamp. Same precedence as
   * getCollectionCampIdFromActivity() behind today's thank-you button:
   * camp/DC (Source) > Goonj office (PU_Source) > event (Events).
   */
  private static function resolveSource(array $activity): array {
    foreach (['Collection_Camp', 'Institution_Collection_Camp', 'Dropping_Center', 'Institution_Dropping_Center'] as $field) {
      if (!empty($activity["Material_Contribution.$field"])) {
        return ['Source', $activity["Material_Contribution.$field"]];
      }
    }
    if (!empty($activity['Material_Contribution.Goonj_Office'])) {
      return ['PU_Source', $activity['Material_Contribution.Goonj_Office']];
    }
    if (!empty($activity['Material_Contribution.Event'])) {
      return ['Events', $activity['Material_Contribution.Event']];
    }
    return [NULL, NULL];
  }

  /**
   *
   */
  private static function publicBaseUrl(): string {
    if (defined('GOONJ_PUBLIC_BASE_URL') && GOONJ_PUBLIC_BASE_URL) {
      return rtrim(GOONJ_PUBLIC_BASE_URL, '/');
    }
    return rtrim(\CRM_Utils_System::baseURL(), '/');
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * On the public contribution page, ?amt=500 selects the matching amount
   * option, or fills "Other Amount" when no option matches. No-op without ?amt.
   */
  public static function prefillAmountFromUrl($formName, &$form) {
    if ($formName !== 'CRM_Contribute_Form_Contribution_Main' || $form->isSubmitted()) {
      return;
    }

    $amount = \CRM_Utils_Request::retrieveValue(self::AMOUNT_PARAM, 'Positive', NULL, FALSE, 'GET');
    if (!$amount || $amount > 1000000) {
      return;
    }

    $priceFields = $form->_priceSet['fields'] ?? [];
    $amountFieldId = $otherFieldId = NULL;
    foreach ($priceFields as $fieldId => $field) {
      if (($field['name'] ?? '') === 'contribution_amount') {
        $amountFieldId = $fieldId;
      }
      elseif (($field['name'] ?? '') === 'other_amount') {
        $otherFieldId = $fieldId;
      }
    }
    if (!$amountFieldId) {
      return;
    }

    foreach ($priceFields[$amountFieldId]['options'] ?? [] as $optionId => $option) {
      if ((float) $option['amount'] === (float) $amount) {
        $form->setDefaults(["price_$amountFieldId" => $optionId]);
        return;
      }
    }

    if ($otherFieldId && $form->elementExists("price_$otherFieldId")) {
      $form->setDefaults([
        // 0 = the "Other Amount" radio CiviCRM adds to quick-config amounts.
        "price_$amountFieldId" => 0,
        "price_$otherFieldId" => $amount,
      ]);
    }
  }

}
