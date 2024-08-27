<?php

class CRM_goonjcustom_Civirules_CollectionCampTrigger extends CRM_Civirules_Trigger_Post {
	/**
	 * Returns an array of additional entities provided in this trigger
	*
	* @return array of CRM_Civirules_TriggerData_EntityDefinition
	*/

	protected function getAdditionalEntities() {
		error_log("CollectionCampTrigger: getAdditionalEntities() called.");

		$entities = parent::getAdditionalEntities();
		error_log("CollectionCampTrigger: Entities - " . print_r($entities, TRUE));

		$entities[] = new CRM_Civirules_TriggerData_EntityDefinition(
		'Collection_Camp',
		'Collection_Camp',
		'CRM_Eck_DAO_CollectionCamp',
		'Collection_Camp'
		);

		return $entities;
	}

	/**
	 * Alter the trigger data with extra data
	 *
	 * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
	 */

	public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
		error_log("CollectionCampTrigger: alterTriggerData() called.");
		$collectionCamp = $triggerData->getEntityData('Collection_Camp');
		error_log("CollectionCampTrigger: Collection Camp Data - " . print_r($collectionCamp, TRUE));

		parent::alterTriggerData($triggerData);
	}
}
