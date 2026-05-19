<?php

use Civi\Api4\Inlay;
use CRM_Inlay_ExtensionUtil as E;

class CRM_Inlay_Page_Demo extends CRM_Core_Page {

  public function run() {

    $adminMode = ($_GET['adminMode'] ?? FALSE) ? 'data-admin-mode=1' : '';
    $id = (int) ($_GET['id'] ?? NULL);
    if (!$id) {
      CRM_Core_Session::setStatus("Invalid URL", "Inlay Demo", 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/a', [], FALSE, '/inlays'));
      // exit
    }

    // Rebuild inlay bundle; this helps if you have updated the js file, for example in development.
    Inlay::createBundle(FALSE)->addWhere('id', '=', $id)->execute();

    // Load inlay.
    try {
      /** @var \Civi\Inlay\Type */
      $inlay = \Civi\Inlay\Type::fromId($id);
    }
    catch (\Exception $e) {
      CRM_Core_Session::setStatus("Invalid Inlay ID", "Inlay Demo", 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/a', [], FALSE, '/inlays'));
    }

    // If in admin mode, don't prefix with "Demo"
    if ($adminMode) {
      CRM_Utils_System::setTitle($inlay->getName());
    }
    else {
      CRM_Utils_System::setTitle(E::ts('Demo: %1', [1 => $inlay->getName()]));
    }
    $editUrl = str_replace('{id}', $inlay->getID(), $inlay->getInstanceEditURLTemplate());
    $this->assign('editUrl', CRM_Utils_System::url($editUrl));
    $cacheBuster = time();
    $this->assign('scriptTag', "<script $adminMode defer src=\"{$inlay->getBundleUrl()}?nocache=$cacheBuster\" data-inlay-id=\"{$inlay->getPublicID()}\" ></script>");

    parent::run();
  }

}
