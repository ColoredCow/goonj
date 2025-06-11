<?php
/**
 * Copyright (C) 2025  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

class CRM_Emailapi_Utils_ContributionData {

  public static function enhanceWithContributionData(array $contactData): array {
    if (!isset($contactData['extra_data']['contribution']['id'])) {
      return $contactData;
    }
    $contributionId = $contactData['extra_data']['contribution']['id'];
    if ($contributionId && !isset($contactData['extra_data']['participant']['id'])) {
      $participantSql = "SELECT `participant_id` FROM `civicrm_participant_payment` WHERE `contribution_id` = %1";
      $participantSqlParams[1] = [$contributionId, 'Integer'];
      $participantId = CRM_Core_DAO::singleValueQuery($participantSql, $participantSqlParams);
      if ($participantId) {
        $contactData['extra_data']['participant']['id'] = $participantId;
        $contactData['participant_id'] = $participantId;
      }
    }
    if ($contributionId && !isset($contactData['extra_data']['membership']['id'])) {
      $membershipSql = "SELECT `membership_id` FROM `civicrm_membership_payment` WHERE `contribution_id` = %1";
      $membershipSqlParams[1] = [$contributionId, 'Integer'];
      $membershipId = CRM_Core_DAO::singleValueQuery($membershipSql, $membershipSqlParams);
      if ($membershipId) {
        $contactData['extra_data']['membership']['id'] = $membershipId;
        $contactData['membership_id'] = $membershipId;
      }
    }
    return $contactData;
  }

}
