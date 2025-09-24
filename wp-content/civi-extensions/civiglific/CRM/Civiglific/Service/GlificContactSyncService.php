<?php

namespace CRM\Civiglific\Service;

use Civi\Api4\Email;
use Civi\Api4\GroupContact;
use CRM\Civiglific\GlificClient;
use CRM\Civiglific\GlificUtils;
use Civi\Api4\Contact;
use Civi\Api4\Phone;
use Civi\Api4\SubscriptionHistory;
use Civi\Api4\GlificGroupMap;

/**
 *
 */
if (!class_exists('\CRM\Civiglific\Service\GlificContactSyncService')) {
class GlificContactSyncService {

  protected $glific;
  protected $batchSize;
  protected $sleepSeconds;

  public function __construct() {
    $this->glific = new GlificClient();
    $this->batchSize = 50;
    $this->sleepSeconds = 1;
  }

  /**
   *
   */
  public function sync() {
    $glificGroupMaps = GlificGroupMap::get(TRUE)
      ->addSelect('id', 'group_id', 'collection_id', 'last_sync_date')
      ->execute()
      ->jsonSerialize();

    foreach ($glificGroupMaps as $rule) {
      $this->syncGroup($rule);
    }
  }

  /**
   *
   */
  private function syncGroup($rule) {
    $civiGroupId = $rule['group_id'];
    $glificGroupId = $rule['collection_id'];
    $mapId = $rule['id'];
    $lastSyncDate = $rule['last_sync_date'];

    $contactsToAdd = empty($lastSyncDate) ?
      $this->getContactsFromGroup($civiGroupId) :
      $this->getNewContacts($civiGroupId, $lastSyncDate);

    $contactsToRemove = $this->getRemovedContacts($civiGroupId, $lastSyncDate);

    // Handle contacts to update.
    $contactsToUpdate = $this->getUpdatedContacts($civiGroupId, $lastSyncDate);

    if (!empty($contactsToRemove)) {
      $this->removeContactsFromGlific($contactsToRemove, $glificGroupId);
    }

    if (!empty($contactsToUpdate)) {
      $this->updateContactsInGlific($contactsToUpdate, $glificGroupId);
    }

    $this->addContactsToGlific($contactsToAdd, $glificGroupId);

    GlificGroupMap::update()
      ->addValue('last_sync_date', date('Y-m-d H:i:s'))
      ->addWhere('id', '=', $mapId)
      ->execute();
  }

  /**
   *
   */
  private function removeContactsFromGlific($contacts, $glificGroupId) {
    foreach ($contacts as $contact) {
      try {
        $phone = $contact['phone'];
        $glificId = $this->glific->getContactIdByPhone($phone);

        if ($glificId) {
          $this->glific->removeFromGroup($glificId, $glificGroupId);
          \Civi::log()->info('Contact removed from Glific', ['phone' => $normalizedPhone, 'glificId' => $glificId, 'groupId' => $glificGroupId]);
        }
        else {
          \Civi::log()->warning("Phone {$phone} not found in Glific; skipping removal.");
        }
      }
      catch (\Exception $e) {
        \Civi::log()->error('Error removing contact from Glific group', [
          'phone' => $contact['phone'],
          'exception_message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   *
   */
  private function addContactsToGlific($contacts, $glificGroupId) {
    if (empty($contacts)) {
      return;
    }

    $glificPhones = $this->glific->getContactsInGroup($glificGroupId);
    $total = count($contacts);

    for ($offset = 0; $offset < $total; $offset += $this->batchSize) {
      $batch = array_slice($contacts, $offset, $this->batchSize);
      foreach ($batch as $contact) {
        $phone = $contact['phone'];
        $name = $contact['name'];
        $civiContactId = $contact['contact_id'] ?? NULL;
        try {
          if (!in_array($phone, $glificPhones)) {
            $glificId = $this->glific->getContactIdByPhone($phone);
            if ($glificId) {
              // Contact exists in Glific, add to group.
              $this->glific->addToGroup($glificId, $glificGroupId);
              $glificPhones[] = $phone;
            }
            else {
              // Create new contact.
              $glificId = $this->glific->createContact($name, $phone);
              if ($glificId) {
                $this->glific->optinContact($phone, $name);
                $this->glific->addToGroup($glificId, $glificGroupId);
                $glificPhones[] = $phone;
              }
              else {
                \Civi::log()->error("Failed to create Glific contact for {$phone}", [
                  'civiContactId' => $civiContactId,
                  'name' => $name,
                ]);
              }
            }
          }
        }
        catch (\Exception $e) {
          \Civi::log()->error('Error syncing contact to Glific', [
            'civiContactId' => $civiContactId,
            'phone' => $phone,
            'name' => $name,
            'exception_message' => $e->getMessage(),
          ]);
        }
      }
      sleep($this->sleepSeconds);
    }
  }

  /**
   *
   */
  private function getContactsFromGroup($groupId) {
    $result = [];
    $contacts = GroupContact::get(TRUE)
      ->addSelect('contact_id')
      ->addWhere('group_id', '=', $groupId)
      ->addWhere('status', '=', 'Added')
      ->execute();

    foreach ($contacts as $row) {
      $contactId = $row['contact_id'];

      // Check if contact has a email.
      $email = Email::get(FALSE)
        ->addSelect('email')
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('is_primary', '=', 1)
        ->execute()
        ->first();

      if (empty($email['email'])) {
        $result[] = $this->buildContact($contactId);
      }

    }
    return array_filter($result);
  }

  /**
   *
   */
  private function getNewContacts($groupId, $lastSyncDate) {
    $result = [];
    $subs = SubscriptionHistory::get(TRUE)
      ->addSelect('contact_id')
      ->addWhere('group_id', '=', $groupId)
      ->addWhere('status', '=', 'Added')
      ->addWhere('date', '>=', $lastSyncDate)
      ->execute();

    foreach ($subs as $sub) {
      $contactId = $sub['contact_id'];

      // Check if contact has a email.
      $email = Email::get(FALSE)
        ->addSelect('email')
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('is_primary', '=', 1)
        ->execute()
        ->first();

      if (empty($email['email'])) {
        $result[] = $this->buildContact($contactId);
      }

    }
    return array_filter($result);
  }

  /**
   *
   */
  private function getRemovedContacts($groupId, $lastSyncDate) {
    $result = [];

    $removedSubs = SubscriptionHistory::get(TRUE)
      ->addSelect('contact_id', 'date')
      ->addWhere('group_id', '=', $groupId)
      ->addWhere('status', '=', 'Removed')
      ->addWhere('date', '>=', $lastSyncDate)
      ->execute();

    foreach ($removedSubs as $sub) {
      $contactId = $sub['contact_id'];

      $phones = Phone::get(TRUE)
        ->addSelect('phone')
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('is_primary', '=', 1)
        ->execute();

      $phoneNumber = count($phones) ? $phones[0]['phone'] : NULL;

      if ($phoneNumber) {
        $result[] = [
          'contact_id' => $contactId,
          'phone' => GlificUtils::normalizePhone($phoneNumber),
        ];
      }
    }

    return $result;
  }

  /**
   *
   */
  private function buildContact($contactId) {
    $contact = Contact::get(FALSE)
      ->addSelect('display_name')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    $phone = Phone::get(TRUE)
      ->addSelect('phone')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute()
      ->first();

    if (!empty($phone['phone'])) {
      return [
        'name' => $contact['display_name'] ?? '',
        'phone' => GlificUtils::normalizePhone($phone['phone']),
      ];
    }

    return NULL;
  }

  /**
   *
   */
  private function getUpdatedContacts($groupId, $lastSyncDate) {
    $result = [];
    $contacts = GroupContact::get(TRUE)
      ->addSelect('contact_id')
      ->addWhere('group_id', '=', $groupId)
      ->addWhere('status', '=', 'Added')
      ->execute();

    foreach ($contacts as $row) {
      $contactId = $row['contact_id'];
      $contact = Contact::get(FALSE)
        ->addSelect('modified_date')
        ->addWhere('id', '=', $contactId)
        ->addWhere('modified_date', '>=', $lastSyncDate)
        ->execute()
        ->first();

      if (!empty($contact)) {
        $email = Email::get(FALSE)
          ->addSelect('email')
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('is_primary', '=', 1)
          ->execute()
          ->first();

        if (empty($email['email'])) {
          $contactData = $this->buildContact($contactId);
          if ($contactData) {
            $contactData['contact_id'] = $contactId;
            \Civi::log()->debug('Fetched contact for update', [
              'civiContactId' => $contactId,
              'phone' => $contactData['phone'],
              'name' => $contactData['name'],
            ]);
            $result[] = $contactData;
          }
        }
      }
    }
    return array_filter($result);
  }

  /**
   * Updates contacts in a Glific group.
   */
  private function updateContactsInGlific($contacts, $glificGroupId) {
    foreach ($contacts as $contact) {
      try {
        $phone = $contact['phone'];
        $name = $contact['name'];
        $civiContactId = $contact['contact_id'] ?? NULL;
        $glificId = $this->glific->getContactIdByPhone($phone);

        if ($glificId) {
          $glificContact = $this->glific->getContactById($glificId);
          $glificName = $glificContact['name'] ?? '';

          // Update name if changed.
          if ($glificName !== $name) {
            $updatedId = $this->glific->updateContact($glificId, $name);
          }
        }
        else {
          \Civi::log()->warning("Contact with phone {$phone} not found in Glific; treating as new contact.", [
            'civiContactId' => $civiContactId,
            'phone' => $phone,
            'name' => $name,
          ]);
          $this->addContactsToGlific([$contact], $glificGroupId);
        }
      }
      catch (\Exception $e) {
        \Civi::log()->error('Error updating contact in Glific', [
          'civiContactId' => $civiContactId,
          'phone' => $contact['phone'],
          'exception_message' => $e->getMessage(),
        ]);
      }
    }
  }

}
}
