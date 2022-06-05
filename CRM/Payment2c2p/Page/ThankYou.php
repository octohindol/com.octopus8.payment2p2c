<?php
use CRM_Payment2c2p_ExtensionUtil as E;

class CRM_Payment2c2p_Page_ThankYou extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('ThankYou'));
      $query = "delete from  civicrm_managed where 1=1";
      CRM_Core_DAO::executeQuery($query);
    $this->assign('currentTime', date('Y-m-d H:i:s'));

    parent::run();
  }

}
