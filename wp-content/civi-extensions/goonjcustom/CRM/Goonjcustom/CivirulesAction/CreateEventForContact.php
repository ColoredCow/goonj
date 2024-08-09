<?php
class CRM_Goonjcustom_CivirulesAction_CreateEventForContact extends CRM_Civirules_Action {
	/**
	 * Method processAction to execute the action
	 *
	 * @param CRM_Civirules_TriggerData_TriggerData $triggerData
	 * @access public
	 */
	public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
		$contactId = $triggerData->getContactId();
		$originalData = $triggerData->getOriginalData();

		// Extract fields
		$startDate = $originalData['custom_73'] ?? null;
		$endDate = $originalData['custom_74'] ?? null;
		$contactId = $originalData['contact_id'] ?? null;
		$activityId = $originalData['activity_id'] ?? null;
		$location = $originalData['custom_69'] ?? null;
		$district = $originalData['custom_72'] ?? null;
		$state = $originalData['custom_71'] ?? null;
		$country = $originalData['custom_70'] ?? null;

		// Save an address for the contact
		try {
			$addressResult = \Civi\Api4\Address::create(FALSE)
				->addValue('contact_id', $contactId)
				->addValue('street_address', $location)
				->addValue('city', $district)
				->addValue('state_province_id', $state)
				->addValue('country_id', $country)
				->setFixAddress(FALSE)
				->execute();

			$addressData = $addressResult->first();
			$addressId = $addressData['id'] ?? null;

			if ($addressId === null) {
				throw new Exception('Address ID not found.');
			}
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}

		// Create a location block with the address ID
		try {
			$locBlockResult = \Civi\Api4\LocBlock::create(FALSE)
				->addValue('address_id', $addressId)
				->execute();

			// Fetch the location block ID from the result
			$locBlockData = $locBlockResult->first();
			$locBlockId = $locBlockData['id'] ?? null;

			if ($locBlockId === null) {
				throw new Exception('Location Block ID not found.');
			}

		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}

		$eventParams = [
			'title' => 'Collection Camp',
			'event_type_id' => 7,
			'start_date' => $startDate,
			'end_date' => $endDate,
			'is_active' => 1,
			'is_public' => 1,
			'default_role_id' => 1,
			'created_id' => $contactId,
			'custom_68' => $activityId,
			'loc_block_id' => $locBlockId,
		];

		try {
			// Create the event
			$eventResult = civicrm_api3('Event', 'create', $eventParams);
			$newEventId = $eventResult['id'];
			// Add the participant to the newly created event
			$participantParams = [
				'event_id' => $newEventId,
				'contact_id' => $contactId,
				'status_id' => 1
			];

			civicrm_api3('Participant', 'create', $participantParams);
		} catch (CiviCRM_API3_Exception $e) {
			throw new Exception($e->getMessage());
		}
	}

	/**
	 * Method to return the url for additional form processing for action
	 * and return false if none is needed
	 *
	 * @param int $ruleActionId
	 * @return bool
	 * @access public
	 */
	public function getExtraDataInputUrl( $ruleActionId ) {
		return false;
	}
}
