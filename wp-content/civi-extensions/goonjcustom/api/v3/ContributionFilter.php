<?php

require_once 'api/Wrapper.php';

use Civi\Api4\Campaign;
use Civi\Api4\Generic\AbstractAction;

/**
 *
 */
class CRM_Goonjcustom_APIWrappers_ContributionFilter implements API_Wrapper {

  private $allowAccess = TRUE;

  /**
   *
   */
  public function fromApiInput($apiRequest) {
    if (!($apiRequest instanceof AbstractAction)) {
      throw new \InvalidArgumentException('Expected Civi\Api4\Generic\AbstractAction');
    }

    $params = $apiRequest->getParams();

    $id = NULL;
    if (isset($params['where']) && is_array($params['where'])) {
      foreach ($params['where'] as $whereClause) {
        if (is_array($whereClause) && count($whereClause) === 3 && $whereClause[0] === 'id' && $whereClause[1] === '=') {
          $id = $whereClause[2];
          break;
        }
      }
    }

    if ($id !== NULL) {
      $campaigns = Campaign::get(FALSE)
        ->addSelect('Api_Access_Control.Allow_Api_Access')
        ->addWhere('id', '=', $id)
        ->execute()->first();

      if (empty($campaigns) || $campaigns['Api_Access_Control.Allow_Api_Access'] != 1) {
        $this->allowAccess = FALSE;
      }
    }
    else {
      $this->allowAccess = FALSE;
    }

    return $apiRequest;
  }

  /**
   *
   */
  public function toApiOutput($apiRequest, $result) {
    if (!$this->allowAccess) {
      $message = 'You do not have permission to view the details for this campaign ID.';
      foreach ($result as &$row) {
        unset($row['id']);
        $row['Contribution_Data.Total_Contribution_Amount'] = $message;
        $row['Contribution_Data.Total_Number_of_Contributors'] = $message;
      }
    }
    return $result;
  }

}
