<?php

require_once 'api/Wrapper.php';

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Exception\APIException;
use Civi\Api4\Campaign;
use Civi\API\Exception\UnauthorizedException;

class CRM_Goonjcustom_APIWrappers_ContributionFilter implements API_Wrapper {

  /**
   * Process input parameters before the API call.
   * Throws an exception if the campaign’s access flag is not set to allow access.
   *
   * @param AbstractAction $apiRequest The API request object
   * @return AbstractAction The modified API request object
   * @throws APIException If access is denied or campaign is not found
   */
  public function fromApiInput($apiRequest) {
    // Validate that we have an AbstractAction object
    if (!($apiRequest instanceof AbstractAction)) {
      throw new \InvalidArgumentException('Expected Civi\Api4\Generic\AbstractAction');
    }

    // Get the request parameters
    $params = $apiRequest->getParams();

    // Extract the campaign ID from the 'where' clause
    $id = null;
    if (isset($params['where']) && is_array($params['where'])) {
      foreach ($params['where'] as $whereClause) {
        if (is_array($whereClause) && count($whereClause) === 3 && $whereClause[0] === 'id' && $whereClause[1] === '=') {
          $id = $whereClause[2];
          break;
        }
      }
    }

    // If an ID is provided, check the campaign’s access flag
    if ($id !== null) {
      $campaigns = Campaign::get(FALSE)
        ->addSelect('Api_Access_Control.Allow_Api_Access')
        ->addWhere('id', '=', $id)
        ->execute()->first();

      if (!empty($campaigns) && isset($campaigns['Api_Access_Control.Allow_Api_Access'])) {
        $flag = $campaigns['Api_Access_Control.Allow_Api_Access'];

        // Deny access if the flag is not "1"
        if ($flag != 1) {
          throw new UnauthorizedException('You don’t have access to this campaign.');
        }
      } else {
        // Campaign not found or flag not set
        throw new UnauthorizedException('Campaign not found or access flag not set.');
      }
    }

    // If access is allowed or no ID is provided, proceed with the request
    return $apiRequest;
  }

  /**
   * Pass the API output through unmodified.
   *
   * @param AbstractAction $apiRequest The API request object
   * @param mixed $result The API result
   * @return mixed The unmodified result
   */
  public function toApiOutput($apiRequest, $result) {
    return $result;
  }
}