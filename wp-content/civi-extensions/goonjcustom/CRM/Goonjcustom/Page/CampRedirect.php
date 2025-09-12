<?php

/**
 *
 */

use Civi\Traits\QrCodeable;

/**
 *
 */
class CRM_Goonjcustom_Page_CampRedirect extends CRM_Core_Page {

  /**
   *
   */
  public function run() {
    $id = CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE);
    try {
      QrCodeable::handleCampRedirect($id);
      CRM_Utils_System::civiExit();
    }
    catch (\Throwable $e) {
      \Civi::log()->error("CampRedirect failed for id={$id}: " . $e->getMessage());
      CRM_Core_Error::statusBounce(ts('This link is invalid or has expired.'));
    }
  }

}
