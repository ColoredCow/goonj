<?php

require_once 'api/Wrapper.php';

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Campaign;
use Civi\Api4\Generic\AbstractAction;

/**
 *
 */
class CRM_Goonjcustom_APIWrappers_ContributionFilter implements API_Wrapper {

  /**
   * Process input parameters before the API call.
   * Throws an exception if the campaign’s access flag is not set to allow access.
   *
   * @param \Civi\Api4\Generic\AbstractAction $apiRequest
   *   The API request object.
   *
   * @return \Civi\Api4\Generic\AbstractAction The modified API request object
   *
   * @throws \Civi\Api4\Exception\APIException If access is denied or campaign is not found
   */
  public function fromApiInput($apiRequest) {
    // Validate that we have an AbstractAction object.
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

      if (!empty($campaigns) && isset($campaigns['Api_Access_Control.Allow_Api_Access'])) {
        $flag = $campaigns['Api_Access_Control.Allow_Api_Access'];

        if ($flag != 1) {
          throw new UnauthorizedException('You don’t have access to this campaign.');
        }
      }
      else {
        throw new UnauthorizedException('Campaign not found or access flag not set.');
      }
    }

    return $apiRequest;
  }

  /**
   * Pass the API output through unmodified.
   *
   * @param \Civi\Api4\Generic\AbstractAction $apiRequest
   *   The API request object.
   * @param mixed $result
   *   The API result.
   *
   * @return mixed The unmodified result
   */
  public function toApiOutput($apiRequest, $result) {
    return $result;
  }

}
