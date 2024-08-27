<?php

class CRM_GoonjCustom_Civirules_CollectionCampTrigger extends CRM_Civirules_Trigger_Post {

    protected function getAdditionalEntities() {
        try {
            error_log("CollectionCampTrigger: getAdditionalEntities() called.");
            
            $entities = parent::getAdditionalEntities();
            error_log("CollectionCampTrigger: Entities - " . print_r($entities, TRUE));

            return $entities;
        } catch (\Exception $e) {
            error_log("Error in getAdditionalEntities: " . $e->getMessage());
        }
    }

    public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
        try {
            error_log("CollectionCampTrigger: alterTriggerData() called.");
            $collectionCamp = $triggerData->getEntityData('Collection_Camp');
            error_log("CollectionCampTrigger: Collection Camp Data - " . print_r($collectionCamp, TRUE));
        } catch (\Exception $e) {
            error_log("Error in alterTriggerData: " . $e->getMessage());
        }
        
        parent::alterTriggerData($triggerData);
    }
}

