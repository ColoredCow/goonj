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
    QrCodeable::handleCampRedirect($id);
    CRM_Utils_System::civiExit();
  }

}
