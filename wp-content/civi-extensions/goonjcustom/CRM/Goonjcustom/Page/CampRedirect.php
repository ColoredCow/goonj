<?php
class CRM_Goonjcustom_Page_CampRedirect extends CRM_Core_Page {
  public function run() {
    $id = CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE);
    error_log("CampRedirect RUN with id=$id"); // DEBUG LOG
    \Civi\Traits\QrCodeable::handleCampRedirect($id);
    CRM_Utils_System::civiExit();
  }
}
