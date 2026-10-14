<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\AfformSubmission;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Core\Service\AutoSubscriber;

/**
 * Turns a Goonj Engagement Intent (JSM) submission into the matching intents.
 *
 * The activities picked under "Activities with Goonj" are created as the same
 * Collection Camp / Dropping Center / Goonj Activities intents the public
 * intent forms create, so the team no longer re-enters them by hand.
 *
 * Intents are written straight through APIv4, not through the intent forms.
 * The defaults those forms carry in their Form Builder config (title, sub
 * type, In Review status) are therefore set here, and the steps that only the
 * form submission triggers are called explicitly.
 */
class EngagementIntentTransferService extends AutoSubscriber {

  const AFFORM_NAME = 'afformGoonjEngagementIntentForm';
  const AFFORM_ENTITY_NAME = 'Eck_Engagement_Intent1';
  const JSM_FIELDS = 'Engagement_Intent_Form';

  const PARTNERSHIP_ORGANISE_ACTIVITIES = 'Activities_with_Goonj';
  const REGISTER_AS_INDIVIDUAL = 'Individual';

  const ACTIVITY_COLLECTION_DRIVE = 'Collection_Drive';
  const ACTIVITY_DISASTER_PHOTO_EXHIBITION = 'Disaster_Photo_Exhibition';
  const ACTIVITY_PROVIDE_SPACE = 'Provide_space_for_material_coll';

  /**
   * Activities that are run as part of a collection camp when one is asked for.
   */
  const CAMP_CHILD_ACTIVITIES = [
    'Fundraising',
    'Awarness_Session',
    'Upcycled_Product_Stall',
    'Book_Fair',
  ];

  const INTENT_COLLECTION_CAMP = 'collection_camp';
  const INTENT_DROPPING_CENTER = 'dropping_center';
  const INTENT_GOONJ_ACTIVITIES = 'goonj_activities';

  const STATUS_TRANSFERRED = 'Intent_Transferred';
  const STATUS_TRANSFERRED_TO_CAMP = 'Intent_Transferred_to_Collection_Camp';

  const SOURCE_ENGAGEMENT_FORM = 'Engagement_Form';
  const SOURCE_PUBLIC_FORM = 'Public_Form';

  /**
   * Timing used for every auto-created intent that has a start and end.
   */
  const INTENT_START_TIME = '10:00:00';
  const INTENT_END_TIME = '13:00:00';

  /**
   * Collection_Camp_Intent_Details option values, as the camp forms set them.
   */
  const CAMP_REGISTER_AS_INDIVIDUAL = '7';
  const CAMP_OPEN_TO_PUBLIC_YES = 2;
  const CAMP_CREATIVE_ACTIVITY_NO = 2;

  /**
   * Dropping_Centre option values, as the dropping center form sets them.
   */
  const DROPPING_CENTER_REGISTER_AS_INDIVIDUAL = '6';
  const DROPPING_CENTER_NO = '2';
  const DROPPING_CENTER_TIMING = '10:00 AM to 1:00 PM';

  /**
   * Goonj_Activities "You wish to register as" value for An individual.
   */
  const GOONJ_ACTIVITIES_REGISTER_AS_INDIVIDUAL = '7';

  /**
   * Maps "Activities with Goonj" options to Goonj Activities engagement values.
   *
   * Awareness Session has no option of its own on the Goonj Activities
   * forms; Knowing Goonj Session is the closest match (agreed 14 Oct 2026).
   */
  const GOONJ_ACTIVITY_VALUES = [
    'Fundraising' => 'Fundraising Activity',
    'Awarness_Session' => 'Organise a Knowing Goonj Session',
    'Upcycled_Product_Stall' => 'Organise an Upcycled Product Stall',
    'Book_Fair' => 'Organise a Book Fair',
    'Disaster_Photo_Exhibition' => 'Organise a Goonj Disaster Photo Exhibition',
  ];

  /**
   * Maps the engagement form's "Register as" to institution intent values.
   *
   * All three institution intents share one "You wish to register as" option
   * list. Values are used, not names: on production NGO and Foundation both
   * carry the name "Foundation".
   */
  const INSTITUTION_REGISTER_AS = [
    'Corporate' => '1',
    'School' => '2',
    'Colleges_Universities' => '3',
    'Association' => '4',
    'NGO' => '6',
    'Foundation' => '8',
    'Other' => '5',
  ];
  const INSTITUTION_REGISTER_AS_OTHER = 'Other';

  /**
   * Institution intents filled through the engagement form come from outside.
   */
  const INSTITUTION_INITIATED_BY_EXTERNAL = '1';

  /**
   * Institution_Collection_Camp_Intent option values (1 = Yes, 2 = No).
   */
  const INSTITUTION_CAMP_OPEN_TO_PUBLIC_NO = 2;
  const INSTITUTION_CAMP_CREATIVE_ACTIVITY_NO = 2;
  const COUNTRY_INDIA = '1101';

  /**
   * Institution_Dropping_Center_Intent option values (1 = Yes, 2 = No).
   */
  const INSTITUTION_DROPPING_CENTER_YES = '1';
  const INSTITUTION_DROPPING_CENTER_NO = '2';

  /**
   * The same activities as Institution Goonj Activities engagement values.
   */
  const INSTITUTION_GOONJ_ACTIVITY_VALUES = [
    'Fundraising' => '7',
    'Awarness_Session' => '4',
    'Upcycled_Product_Stall' => '2',
    'Book_Fair' => '1',
    'Disaster_Photo_Exhibition' => '5',
  ];

  /**
   * Volunteer details the volunteer-with-intent form collects, defaulted here.
   *
   * Values: 23 = Organize a Material Sharing Camp in Society/Residence/
   * Office/Institution, 5 = Coordination, 2 = Give back to society,
   * 5 = Support the cause, 81 = Indian (the form's hidden default).
   */
  const VOLUNTEER_DEFAULTS = [
    'Volunteer_fields.Which_activities_are_you_interested_in_' => ['23'],
    'Volunteer_fields.Volunteer_Skills' => ['5'],
    'Volunteer_fields.Motivates_you_to_Volunteer' => ['2', '5'],
    'Volunteer_fields.Nationality' => '81',
    'Individual_fields.Created_Date' => 'now',
  ];

  /**
   * Induction statuses for which the camp form re-sends the induction email.
   */
  const INDUCTION_PENDING_STATUSES = ['To be scheduled', 'No_show', 'Not Visited'];

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['transferEngagementIntent'],
      ],
      '&hook_civicrm_custom' => [
        ['setPublicFormSource'],
      ],
    ];
  }

  /**
   * Marks an intent created from any other form as coming from a Public Form.
   *
   * Runs when an intent's Core Details are first saved, which every intent
   * form does (it always sets the In Review status). An auto-created intent
   * already carries "Engagement Form" in that same save and is left alone, as
   * is any later edit.
   */
  public static function setPublicFormSource($op, $groupID, $entityID, &$params) {
    if ($op !== 'create' || !$entityID) {
      return;
    }

    $sourceField = self::getIntentSourceField();
    if (!$sourceField || (int) $groupID !== (int) $sourceField['custom_group_id']) {
      return;
    }

    foreach ($params as $param) {
      if (($param['entity_table'] ?? NULL) !== 'civicrm_eck_collection_camp') {
        return;
      }

      if ((int) ($param['custom_field_id'] ?? 0) === (int) $sourceField['id'] && !empty($param['value'])) {
        return;
      }
    }

    try {
      EckEntity::update('Collection_Camp', FALSE)
        ->addValue('Collection_Camp_Core_Details.Intent_Source:name', self::SOURCE_PUBLIC_FORM)
        ->addWhere('id', '=', $entityID)
        ->execute();
    }
    catch (\Throwable $e) {
      \Civi::log()->error("[EngagementIntentTransfer] Could not set the intent source on $entityID: " . $e->getMessage());
    }
  }

  /**
   * The Intent Source custom field (id and group), looked up once.
   */
  private static function getIntentSourceField() {
    static $field;

    if (!isset($field)) {
      $field = CustomField::get(FALSE)
        ->addSelect('id', 'custom_group_id')
        ->addWhere('custom_group_id:name', '=', 'Collection_Camp_Core_Details')
        ->addWhere('name', '=', 'Intent_Source')
        ->execute()->first() ?: FALSE;
    }

    return $field;
  }

  /**
   * Runs once the JSM submission has saved all its records.
   *
   * Afform marks the submission Processed (an edit) only after the intent and
   * the contact are written, so their ids are available here.
   */
  public static function transferEngagementIntent(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission' || !$objectId) {
      return;
    }

    if (!empty($objectRef->afform_name) && $objectRef->afform_name !== self::AFFORM_NAME) {
      return;
    }

    try {
      $submission = AfformSubmission::get(FALSE)
        ->addSelect('afform_name', 'data', 'status_id:name')
        ->addWhere('id', '=', $objectId)
        ->execute()->first();

      if (!$submission || $submission['afform_name'] !== self::AFFORM_NAME || $submission['status_id:name'] !== 'Processed') {
        return;
      }

      $engagementIntentId = $submission['data'][self::AFFORM_ENTITY_NAME][0]['id'] ?? NULL;
      $contactId = $submission['data']['Individual1'][0]['id'] ?? NULL;

      if (!$engagementIntentId || !$contactId) {
        \Civi::log()->warning('[EngagementIntentTransfer] Submission ' . $objectId . ' has no engagement intent or contact id, skipping.');
        return;
      }

      self::transfer((int) $engagementIntentId, (int) $contactId);
    }
    catch (\Throwable $e) {
      \Civi::log()->error('[EngagementIntentTransfer] Transfer failed for submission ' . $objectId . ': ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
      ]);
    }
  }

  /**
   * Creates the intents for one engagement intent and updates its status.
   */
  public static function transfer(int $engagementIntentId, int $contactId) {
    if (self::isAlreadyTransferred($engagementIntentId)) {
      \Civi::log()->info("[EngagementIntentTransfer] Engagement intent $engagementIntentId already transferred, skipping.");
      return;
    }

    $engagementIntent = self::getEngagementIntent($engagementIntentId);
    if (!$engagementIntent) {
      return;
    }

    $registerAs = $engagementIntent[self::JSM_FIELDS . '.Register_as:name'] ?? NULL;
    if (!$registerAs) {
      return;
    }

    $isIndividual = $registerAs === self::REGISTER_AS_INDIVIDUAL;
    $isInducted = $isIndividual && self::isInductionCompleted($contactId);

    $plan = self::planIntents($engagementIntent, $isIndividual, $isInducted);
    if (empty($plan['intents']) && empty($plan['skipped'])) {
      return;
    }

    $created = [];
    $remarks = $plan['skipped'];

    foreach ($plan['intents'] as $intentType => $details) {
      try {
        $intentId = self::createIntent($intentType, $details, $engagementIntent, $contactId, $isIndividual);
        if ($intentId) {
          $created[$intentType] = $intentId;
        }
      }
      catch (\Throwable $e) {
        $remarks[] = 'Auto-transfer could not create the ' . self::intentLabel($intentType, $isIndividual) . ' intent, please create it manually.';
        \Civi::log()->error("[EngagementIntentTransfer] Could not create $intentType for engagement intent $engagementIntentId: " . $e->getMessage(), [
          'trace' => $e->getTraceAsString(),
        ]);
      }
    }

    self::updateEngagementIntent($engagementIntent, $created, $plan, $remarks);

    \Civi::log()->info("[EngagementIntentTransfer] Engagement intent $engagementIntentId transferred.", [
      'created' => $created,
      'remarks' => $remarks,
    ]);
  }

  /**
   * Decides which intents an engagement intent turns into.
   *
   * - Collection Drive creates a collection camp; Fundraising, Awareness
   *   Session, Upcycled Product Stall and Book Fair are folded into it.
   * - Without a Collection Drive, those activities and the Disaster Photo
   *   Exhibition become one combined Goonj Activities intent.
   * - With a Collection Drive, a Disaster Photo Exhibition still gets its own
   *   Goonj Activities intent.
   * - "Provide space…" creates a dropping center.
   * - Individuals get dropping center and Goonj Activities intents only once
   *   their induction is Completed.
   *
   * @return array
   *   'intents' keyed by intent type, and 'skipped' remarks for the record.
   */
  public static function planIntents(array $engagementIntent, bool $isIndividual, bool $isInducted): array {
    $plan = ['intents' => [], 'skipped' => []];

    $partnership = (array) ($engagementIntent[self::JSM_FIELDS . '.Partnership_Collaboration_with_Goonj_on:name'] ?? []);
    $activities = (array) ($engagementIntent[self::JSM_FIELDS . '.Activities_with_Goonj:name'] ?? []);

    if (!in_array(self::PARTNERSHIP_ORGANISE_ACTIVITIES, $partnership) || empty($activities)) {
      return $plan;
    }

    $hasCollectionDrive = in_array(self::ACTIVITY_COLLECTION_DRIVE, $activities);
    $hasExhibition = in_array(self::ACTIVITY_DISASTER_PHOTO_EXHIBITION, $activities);
    $childActivities = array_values(array_intersect($activities, self::CAMP_CHILD_ACTIVITIES));

    if ($hasCollectionDrive) {
      $plan['intents'][self::INTENT_COLLECTION_CAMP] = [
        'other_activities' => $childActivities,
      ];
      $goonjActivities = $hasExhibition ? [self::ACTIVITY_DISASTER_PHOTO_EXHIBITION] : [];
    }
    else {
      $goonjActivities = $hasExhibition ? array_merge($childActivities, [self::ACTIVITY_DISASTER_PHOTO_EXHIBITION]) : $childActivities;
    }

    if ($goonjActivities) {
      $plan['intents'][self::INTENT_GOONJ_ACTIVITIES] = ['activities' => $goonjActivities];
    }

    if (in_array(self::ACTIVITY_PROVIDE_SPACE, $activities)) {
      $plan['intents'][self::INTENT_DROPPING_CENTER] = [];
    }

    if ($isIndividual && !$isInducted) {
      foreach ([self::INTENT_DROPPING_CENTER, self::INTENT_GOONJ_ACTIVITIES] as $intentType) {
        if (isset($plan['intents'][$intentType])) {
          unset($plan['intents'][$intentType]);
          $plan['skipped'][] = 'The ' . self::intentLabel($intentType, TRUE) . ' intent was not created because the induction status is not Completed.';
        }
      }
    }

    return $plan;
  }

  /**
   * Creates one planned intent and returns its id.
   */
  private static function createIntent(string $intentType, array $details, array $engagementIntent, int $contactId, bool $isIndividual) {
    if ($isIndividual && $intentType === self::INTENT_COLLECTION_CAMP) {
      return self::createIndividualCollectionCamp($details, $engagementIntent, $contactId);
    }

    if ($isIndividual && $intentType === self::INTENT_DROPPING_CENTER) {
      return self::createIndividualDroppingCenter($engagementIntent, $contactId);
    }

    if ($isIndividual && $intentType === self::INTENT_GOONJ_ACTIVITIES) {
      return self::createIndividualGoonjActivities($details, $engagementIntent, $contactId);
    }

    if (!$isIndividual && $intentType === self::INTENT_COLLECTION_CAMP) {
      return self::createInstitutionCollectionCamp($details, $engagementIntent, $contactId);
    }

    if (!$isIndividual && $intentType === self::INTENT_DROPPING_CENTER) {
      return self::createInstitutionDroppingCenter($engagementIntent, $contactId);
    }

    if (!$isIndividual && $intentType === self::INTENT_GOONJ_ACTIVITIES) {
      return self::createInstitutionGoonjActivities($details, $engagementIntent, $contactId);
    }

    return NULL;
  }

  /**
   * Creates an Individual Collection Camp intent like the camp volunteer form.
   *
   * The initiator is made a volunteer first, which fires the existing
   * induction and registration email, and the induction then gets linked to
   * the camp on create.
   */
  private static function createIndividualCollectionCamp(array $details, array $engagementIntent, int $contactId) {
    self::ensureVolunteer($contactId);

    $date = self::getActivityDate($engagementIntent);

    $campId = self::createIntentRecord('Collection Camp', 'Collection_Camp', $engagementIntent, [
      'Collection_Camp_Core_Details.Contact_Id' => $contactId,
      'Collection_Camp_Core_Details.Other_Activities' => self::getActivityLabels($engagementIntent, $details['other_activities']),
      'Collection_Camp_Intent_Details.You_wish_to_register_as' => self::CAMP_REGISTER_AS_INDIVIDUAL,
      'Collection_Camp_Intent_Details.Location_Area_of_camp' => $engagementIntent[self::JSM_FIELDS . '.Address'],
      'Collection_Camp_Intent_Details.State' => $engagementIntent[self::JSM_FIELDS . '.State'],
      'Collection_Camp_Intent_Details.City' => $engagementIntent[self::JSM_FIELDS . '.City'],
      'Collection_Camp_Intent_Details.Start_Date' => $date ? $date . ' ' . self::INTENT_START_TIME : NULL,
      'Collection_Camp_Intent_Details.End_Date' => $date ? $date . ' ' . self::INTENT_END_TIME : NULL,
      'Collection_Camp_Intent_Details.Will_your_collection_drive_be_open_for_general_public' => self::CAMP_OPEN_TO_PUBLIC_YES,
      'Collection_Camp_Intent_Details.Do_you_want_to_plan_a_creative_and_engaging_activity_where_resid' => self::CAMP_CREATIVE_ACTIVITY_NO,
    ]);

    self::sendCampInitiatorInductionEmail($contactId);

    return $campId;
  }

  /**
   * Creates an Individual Dropping Center intent like the dropping center form.
   *
   * Only reached for inducted volunteers, the same people the website lets
   * through to that form. The volunteer's name and number are filled the way
   * the check-user page prefills them.
   */
  private static function createIndividualDroppingCenter(array $engagementIntent, int $contactId) {
    $contact = self::getContactDetails($contactId);

    return self::createIntentRecord('Dropping Center', 'Dropping_Center', $engagementIntent, [
      'Collection_Camp_Core_Details.Contact_Id' => $contactId,
      'Dropping_Centre.You_wish_to_register_as' => self::DROPPING_CENTER_REGISTER_AS_INDIVIDUAL,
      'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_' => $engagementIntent[self::JSM_FIELDS . '.Address'],
      'Dropping_Centre.State' => $engagementIntent[self::JSM_FIELDS . '.State'],
      'Dropping_Centre.District_City' => $engagementIntent[self::JSM_FIELDS . '.City'],
      'Dropping_Centre.When_do_you_wish_to_open_center_Date_' => self::getActivityDate($engagementIntent),
      'Dropping_Centre.Timing' => self::DROPPING_CENTER_TIMING,
      'Dropping_Centre.Can_we_keep_donation_box_in_center_For_Monetary_Contribution_' => self::DROPPING_CENTER_NO,
      'Dropping_Centre.Will_your_dropping_center_be_open_for_general_public_as_well_out' => self::DROPPING_CENTER_NO,
      'Dropping_Centre.Name' => $contact['display_name'] ?? NULL,
      'Dropping_Centre.Contact_Number' => $contact['phone_primary.phone'] ?? NULL,
    ]);
  }

  /**
   * Creates one Individual Goonj Activities intent for all picked activities.
   *
   * Only reached for inducted volunteers, as on the website. The volunteer's
   * name and number are filled the way the check-user page prefills them.
   */
  private static function createIndividualGoonjActivities(array $details, array $engagementIntent, int $contactId) {
    $contact = self::getContactDetails($contactId);
    $date = self::getActivityDate($engagementIntent);

    return self::createIntentRecord('Goonj Activities', 'Goonj_Activities', $engagementIntent, [
      'Collection_Camp_Core_Details.Contact_Id' => $contactId,
      'Goonj_Activities.You_wish_to_register_as' => self::GOONJ_ACTIVITIES_REGISTER_AS_INDIVIDUAL,
      'Goonj_Activities.How_do_you_want_to_engage_with_Goonj_' => self::getGoonjActivityValues($details['activities']),
      'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_' => $engagementIntent[self::JSM_FIELDS . '.Address'],
      'Goonj_Activities.State' => $engagementIntent[self::JSM_FIELDS . '.State'],
      'Goonj_Activities.City' => $engagementIntent[self::JSM_FIELDS . '.City'],
      'Goonj_Activities.Start_Date' => $date ? $date . ' ' . self::INTENT_START_TIME : NULL,
      'Goonj_Activities.End_Date' => $date ? $date . ' ' . self::INTENT_END_TIME : NULL,
      'Goonj_Activities.Will_this_Goonj_Activity_be_open_for_general_public_as_well_' => TRUE,
      'Goonj_Activities.Name' => $contact['display_name'] ?? NULL,
      'Goonj_Activities.Contact_Number' => $contact['phone_primary.phone'] ?? NULL,
    ]);
  }

  /**
   * Creates an Institution Collection Camp intent like the public form does.
   *
   * The person who filled the engagement form is the institution's point of
   * contact. The organisation is kept as the typed name only: the team picks
   * the Organization contact on the verification form, as for any public
   * submission. Office, coordinator and IHC/PCC are set by the existing create
   * hook from the state, "register as" and "open to public" values.
   */
  private static function createInstitutionCollectionCamp(array $details, array $engagementIntent, int $contactId) {
    $date = self::getActivityDate($engagementIntent);

    return self::createIntentRecord('Institution Collection Camp', 'Institution_Collection_Camp', $engagementIntent, [
      'Collection_Camp_Core_Details.Other_Activities' => self::getActivityLabels($engagementIntent, $details['other_activities']),
      'Institution_collection_camp_Review.Initiated_By' => self::INSTITUTION_INITIATED_BY_EXTERNAL,
      'Institution_Collection_Camp_Intent.Institution_POC' => $contactId,
      'Institution_Collection_Camp_Intent.You_wish_to_register_as' => self::getInstitutionRegisterAs($engagementIntent),
      'Institution_Collection_Camp_Intent.Other_Type' => self::getOtherInstitutionType($engagementIntent),
      'Institution_Collection_Camp_Intent.Initiator_Organization_Name' => $engagementIntent[self::JSM_FIELDS . '.Organisation_Institution_Name'],
      'Institution_Collection_Camp_Intent.Collection_Camp_Address' => $engagementIntent[self::JSM_FIELDS . '.Address'],
      'Institution_Collection_Camp_Intent.State' => $engagementIntent[self::JSM_FIELDS . '.State'],
      'Institution_Collection_Camp_Intent.District_City' => $engagementIntent[self::JSM_FIELDS . '.City'],
      'Institution_Collection_Camp_Intent.Country' => self::COUNTRY_INDIA,
      'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_' => $date ? $date . ' ' . self::INTENT_START_TIME : NULL,
      'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_' => $date ? $date . ' ' . self::INTENT_END_TIME : NULL,
      'Institution_Collection_Camp_Intent.Will_your_collection_drive_be_open_for_general_public_as_well' => self::INSTITUTION_CAMP_OPEN_TO_PUBLIC_NO,
      'Institution_Collection_Camp_Intent.Do_you_want_to_plan_a_creative_and_engaging_activity_that_go_bey' => self::INSTITUTION_CAMP_CREATIVE_ACTIVITY_NO,
    ]);
  }

  /**
   * Creates an Institution Dropping Center intent like the institution form.
   *
   * Same split as the institution camp: the engagement form's person is the
   * point of contact and the organisation stays a typed name until the team
   * picks the Organization contact on the verification form. Office and
   * coordinator are set by the existing create hook.
   */
  private static function createInstitutionDroppingCenter(array $engagementIntent, int $contactId) {
    return self::createIntentRecord('Institution Dropping Center', 'Institution_Dropping_Center', $engagementIntent, [
      'Institution_Dropping_Center_Review.Initiated_By' => self::INSTITUTION_INITIATED_BY_EXTERNAL,
      'Institution_Dropping_Center_Intent.Institution_POC' => $contactId,
      'Institution_Dropping_Center_Intent.You_wish_to_register_as' => self::getInstitutionRegisterAs($engagementIntent),
      'Institution_Dropping_Center_Intent.Other_Type' => self::getOtherInstitutionType($engagementIntent),
      'Institution_Dropping_Center_Intent.initiator_Organization_Name' => $engagementIntent[self::JSM_FIELDS . '.Organisation_Institution_Name'],
      'Institution_Dropping_Center_Intent.Dropping_Center_Address' => $engagementIntent[self::JSM_FIELDS . '.Address'],
      'Institution_Dropping_Center_Intent.State' => $engagementIntent[self::JSM_FIELDS . '.State'],
      'Institution_Dropping_Center_Intent.District_City' => $engagementIntent[self::JSM_FIELDS . '.City'],
      'Institution_Dropping_Center_Intent.When_do_you_wish_to_open_center_Date_' => self::getActivityDate($engagementIntent),
      'Institution_Dropping_Center_Intent.Timing' => self::DROPPING_CENTER_TIMING,
      'Institution_Dropping_Center_Intent.Will_your_dropping_center_be_open_for_general_public_as_well_out' => self::INSTITUTION_DROPPING_CENTER_YES,
      'Institution_Dropping_Center_Intent.Can_we_keep_donation_box_in_center_' => self::INSTITUTION_DROPPING_CENTER_NO,
      'Institution_Dropping_Center_Intent.Some_volunteers_require_permission_letters_from_Goonj_to_get_per' => self::INSTITUTION_DROPPING_CENTER_NO,
    ]);
  }

  /**
   * Creates one Institution Goonj Activities intent for all picked activities.
   *
   * Same split as the other institution intents: the engagement form's person
   * is the point of contact and the organisation stays a typed name until the
   * team picks the Organization contact on the review form. Office and
   * coordinator are set by the existing create hook.
   */
  private static function createInstitutionGoonjActivities(array $details, array $engagementIntent, int $contactId) {
    $date = self::getActivityDate($engagementIntent);

    return self::createIntentRecord('Institution Goonj Activities', 'Institution_Goonj_Activities', $engagementIntent, [
      'Institution_Goonj_Activities.Initiated_By' => self::INSTITUTION_INITIATED_BY_EXTERNAL,
      'Institution_Goonj_Activities.Institution_POC' => $contactId,
      'Institution_Goonj_Activities.You_wish_to_register_as' => self::getInstitutionRegisterAs($engagementIntent),
      'Institution_Goonj_Activities.Other_Type' => self::getOtherInstitutionType($engagementIntent),
      'Institution_Goonj_Activities.Initiator_Organization_Name' => $engagementIntent[self::JSM_FIELDS . '.Organisation_Institution_Name'],
      'Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_' => self::getGoonjActivityValues($details['activities'], self::INSTITUTION_GOONJ_ACTIVITY_VALUES),
      'Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_' => $engagementIntent[self::JSM_FIELDS . '.Address'],
      'Institution_Goonj_Activities.State' => $engagementIntent[self::JSM_FIELDS . '.State'],
      'Institution_Goonj_Activities.City' => $engagementIntent[self::JSM_FIELDS . '.City'],
      'Institution_Goonj_Activities.Start_Date' => $date ? $date . ' ' . self::INTENT_START_TIME : NULL,
      'Institution_Goonj_Activities.End_Date' => $date ? $date . ' ' . self::INTENT_END_TIME : NULL,
    ]);
  }

  /**
   * The institution intents' "You wish to register as" value.
   */
  private static function getInstitutionRegisterAs(array $engagementIntent) {
    return self::INSTITUTION_REGISTER_AS[$engagementIntent[self::JSM_FIELDS . '.Register_as:name']] ?? NULL;
  }

  /**
   * The typed institution type, kept only when "Other" was picked.
   */
  private static function getOtherInstitutionType(array $engagementIntent) {
    if ($engagementIntent[self::JSM_FIELDS . '.Register_as:name'] !== self::INSTITUTION_REGISTER_AS_OTHER) {
      return NULL;
    }

    return $engagementIntent[self::JSM_FIELDS . '.Other_Institution'];
  }

  /**
   * Goonj Activities engagement values for the given activity options.
   *
   * Individual and institution intents keep these in different option lists,
   * so the map to use is passed in; it defaults to the individual one.
   */
  private static function getGoonjActivityValues(array $activityNames, array $valueMap = self::GOONJ_ACTIVITY_VALUES) {
    $values = array_map(fn($name) => $valueMap[$name] ?? NULL, $activityNames);
    return array_values(array_filter($values));
  }

  /**
   * Creates an intent with what every intent form sets, plus its own values.
   *
   * Title, sub type and the In Review status are what the intent forms carry
   * in their Form Builder config; source and the link back to the engagement
   * intent mark it as auto-created. Empty values are left out, as on a form.
   */
  private static function createIntentRecord(string $title, string $subtypeName, array $engagementIntent, array $values) {
    $values = [
      'title' => $title,
      'subtype:name' => $subtypeName,
      'Collection_Camp_Core_Details.Status' => 'under_authorization',
      'Collection_Camp_Core_Details.Intent_Source:name' => self::SOURCE_ENGAGEMENT_FORM,
      'Collection_Camp_Core_Details.Engagement_Intent' => $engagementIntent['id'],
    ] + $values;

    return EckEntity::create('Collection_Camp', FALSE)
      ->setValues(array_filter($values, fn($value) => $value !== NULL && $value !== ''))
      ->execute()->first()['id'];
  }

  /**
   * The contact's name and primary phone.
   */
  private static function getContactDetails(int $contactId) {
    return Contact::get(FALSE)
      ->addSelect('display_name', 'phone_primary.phone')
      ->addWhere('id', '=', $contactId)
      ->execute()->first();
  }

  /**
   * Makes the contact a volunteer, filling the volunteer details left empty.
   *
   * Adding the Volunteer sub type fires the existing InductionService flow,
   * which creates the induction and sends the registration email once.
   */
  private static function ensureVolunteer(int $contactId) {
    $contact = Contact::get(FALSE)
      ->addSelect('contact_sub_type', ...array_keys(self::VOLUNTEER_DEFAULTS))
      ->addWhere('id', '=', $contactId)
      ->execute()->first();

    $subTypes = $contact['contact_sub_type'] ?? [];
    if (in_array('Volunteer', $subTypes)) {
      return FALSE;
    }

    $update = Contact::update(FALSE)
      ->addWhere('id', '=', $contactId)
      ->addValue('contact_sub_type', array_merge($subTypes, ['Volunteer']));

    foreach (self::VOLUNTEER_DEFAULTS as $field => $value) {
      if (empty($contact[$field])) {
        $update->addValue($field, $value);
      }
    }

    $update->execute();
    return TRUE;
  }

  /**
   * Re-sends the induction email the way the camp intent form does.
   *
   * Mirrors CollectionCampService::sendInductionEmailToCollectionCampUser,
   * which only runs for submissions of the camp forms. The email goes out
   * once per contact; a contact who just became a volunteer already has it.
   */
  private static function sendCampInitiatorInductionEmail(int $contactId) {
    $pendingInduction = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('activity_type_id:name', '=', 'Induction')
      ->addWhere('status_id:name', 'IN', self::INDUCTION_PENDING_STATUSES)
      ->addWhere('target_contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute()->first();

    if ($pendingInduction) {
      InductionService::sendInductionEmailToCollectionCampInitiator($contactId);
    }
  }

  /**
   * Sets the transfer status and adds remarks on the engagement intent.
   */
  private static function updateEngagementIntent(array $engagementIntent, array $created, array $plan, array $remarks) {
    $update = EckEntity::update('Engagement_Intent', FALSE)
      ->addWhere('id', '=', $engagementIntent['id']);

    $hasChanges = FALSE;

    if ($created) {
      $foldedActivities = isset($created[self::INTENT_COLLECTION_CAMP])
        && !empty($plan['intents'][self::INTENT_COLLECTION_CAMP]['other_activities']);

      $update->addValue(self::JSM_FIELDS . '.Status:name', $foldedActivities ? self::STATUS_TRANSFERRED_TO_CAMP : self::STATUS_TRANSFERRED)
        ->addValue(self::JSM_FIELDS . '.Status_Updated_on', 'now');
      $hasChanges = TRUE;
    }

    if ($remarks) {
      $existingRemark = trim((string) ($engagementIntent[self::JSM_FIELDS . '.Remark'] ?? ''));
      $update->addValue(self::JSM_FIELDS . '.Remark', trim($existingRemark . "\n" . implode("\n", $remarks)));
      $hasChanges = TRUE;
    }

    if ($hasChanges) {
      $update->execute();
    }
  }

  /**
   * Whether intents were already created from this engagement intent.
   */
  private static function isAlreadyTransferred(int $engagementIntentId): bool {
    return (bool) EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id')
      ->addWhere('Collection_Camp_Core_Details.Engagement_Intent', '=', $engagementIntentId)
      ->setLimit(1)
      ->execute()->count();
  }

  /**
   * Loads the engagement intent fields the transfer needs.
   */
  private static function getEngagementIntent(int $engagementIntentId) {
    return EckEntity::get('Engagement_Intent', FALSE)
      ->addSelect(
        'id',
        self::JSM_FIELDS . '.Register_as:name',
        self::JSM_FIELDS . '.Partnership_Collaboration_with_Goonj_on:name',
        self::JSM_FIELDS . '.Activities_with_Goonj:name',
        self::JSM_FIELDS . '.Activities_with_Goonj:label',
        self::JSM_FIELDS . '.Expected_Activity_Date',
        self::JSM_FIELDS . '.State',
        self::JSM_FIELDS . '.City',
        self::JSM_FIELDS . '.Address',
        self::JSM_FIELDS . '.Organisation_Institution_Name',
        self::JSM_FIELDS . '.Other_Institution',
        self::JSM_FIELDS . '.Remark'
      )
      ->addWhere('id', '=', $engagementIntentId)
      ->execute()->first();
  }

  /**
   * Whether the contact's induction is Completed.
   */
  private static function isInductionCompleted(int $contactId): bool {
    return (bool) Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('activity_type_id:name', '=', 'Induction')
      ->addWhere('status_id:name', '=', 'Completed')
      ->addWhere('target_contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute()->count();
  }

  /**
   * The expected activity date as Y-m-d, or NULL when it was left blank.
   */
  private static function getActivityDate(array $engagementIntent) {
    $date = $engagementIntent[self::JSM_FIELDS . '.Expected_Activity_Date'] ?? NULL;
    return $date ? date('Y-m-d', strtotime($date)) : NULL;
  }

  /**
   * Comma-separated labels of the given "Activities with Goonj" options.
   */
  private static function getActivityLabels(array $engagementIntent, array $activityNames) {
    $names = (array) ($engagementIntent[self::JSM_FIELDS . '.Activities_with_Goonj:name'] ?? []);
    $labels = (array) ($engagementIntent[self::JSM_FIELDS . '.Activities_with_Goonj:label'] ?? []);
    $labelsByName = array_combine($names, $labels) ?: [];

    return implode(', ', array_map(fn($name) => $labelsByName[$name] ?? $name, $activityNames));
  }

  /**
   * Human name of an intent type, used in remarks.
   */
  private static function intentLabel(string $intentType, bool $isIndividual): string {
    $labels = [
      self::INTENT_COLLECTION_CAMP => 'Collection Camp',
      self::INTENT_DROPPING_CENTER => 'Dropping Center',
      self::INTENT_GOONJ_ACTIVITIES => 'Goonj Activities',
    ];

    return ($isIndividual ? 'Individual ' : 'Institution ') . $labels[$intentType];
  }

}
