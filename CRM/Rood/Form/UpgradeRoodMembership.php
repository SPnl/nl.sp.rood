<?php

class CRM_Rood_Form_UpgradeRoodMembership extends CRM_Core_Form {

  protected $_membershipType;

  protected $_membershipStatus;

  function buildQuickForm() {
    $mtypes = CRM_Member_PseudoConstant::membershipType();
    $this->add('select', 'rood_mtype', ts('Rood lidmaatschapstype'), $mtypes, true);
    $this->add('select', 'sp_mtype', ts('SP lidmaatschapstype'), $mtypes, true);
    $this->add('select', 'rood_mstatus', ts('Beeindig Rood met lidmaatschapstatus'), CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label'), true);

    foreach (CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label') as $sId => $sName) {
      $this->_membershipStatus = $this->addElement('checkbox', "member_status_id[$sId]", NULL, $sName);
    }

    $this->addDate('birth_date_from', ts('Birth date from'), false, array('formatType' => 'activityDate'));
    $this->addDate('birth_date_to', ts('Birth Date to'), false, array('formatType' => 'activityDate'));

    $this->add('text', 'minimum_fee', ts('Minimum Fee'),
      CRM_Core_DAO::getAttribute('CRM_Member_DAO_MembershipType', 'minimum_fee')
    );
    $this->addRule('minimum_fee', ts('Please enter a monetary value for the Minimum Fee.'), 'money');

    // add buttons
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Upgrade ROOD lidmaatschappen'),
        'isDefault' => TRUE,
      ),
    ));
  }

  public function setDefaultValues() {
    $defaults = array();
    try {
      $defaults['rood_mtype'] = civicrm_api3('MembershipType', 'getvalue', array(
        'return' => 'id',
        'name' => 'Lid SP en ROOD'
      ));
    } catch (Exception $e) {
      //do nothing
    }
    try {
      $defaults['rood_mstatus'] = civicrm_api3('MembershipStatus', 'getvalue', array(
          'return' => 'id',
          'name' => 'Correctie'
      ));
    } catch (Exception $e) {
      //do nothing
    }
    try {
      $defaults['sp_mtype'] = civicrm_api3('MembershipType', 'getvalue', array(
        'return' => 'id',
        'name' => 'Lid SP'
      ));
    } catch (Exception $e) {
      //do nothing
    }
    try {
      $status = civicrm_api3('MembershipStatus', 'getvalue', array(
        'return' => 'id',
        'name' => 'current'
      ));
      $defaults['member_status_id'][$status] = $status;
    } catch (Exception $e) {
      //do nothing
    }

    $date = new DateTime();
    $date->modify('-26 years');
    $date->modify('first day of this year');
    list($defaults['birth_date_from']) = CRM_Utils_Date::setDateDefaults($date->format('Y-m-d'));
    $date->modify('last day of this year');
    list($defaults['birth_date_to']) = CRM_Utils_Date::setDateDefaults($date->format('Y-m-d'));

    $minimum_fee = CRM_Core_BAO_Setting::getItem('nl.sp.rood', 'minimum_fee', null, '6.00');
    $defaults['minimum_fee'] = $minimum_fee;

    return $defaults;
  }

  function postProcess() {
    $current_status_ids = array();
    $dao = CRM_Core_DAO::executeQuery("SELECT id from civicrm_membership_status where is_current_member = 1");
    while($dao->fetch()) {
      $current_status_ids[] = $dao->id;
    }



    $formValues = $this->exportValues();
    if (!isset($formValues['member_status_id'])) {
      $formValues['member_status_id'] = array();
    }

    $birth_date_from = CRM_Utils_Date::processDate($formValues['birth_date_from']);
    $birth_date_to = CRM_Utils_Date::processDate($formValues['birth_date_to']);

    $selector = new CRM_Rood_UpgradeSelector();
    $original_where = $selector->getWhere();
    $selector->setData($formValues['rood_mtype'], array_keys($formValues['member_status_id']), $birth_date_from, $birth_date_to);
    $selector->store();
    $where = $selector->getWhere();

    $count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*)
      FROM civicrm_membership
      INNER JOIN civicrm_contact ON civicrm_membership.contact_id = civicrm_contact.id
      LEFT JOIN civicrm_membership sp_membership ON civicrm_contact.id = sp_membership.contact_id AND sp_membership.membership_type_id = '{$formValues['sp_mtype']}' AND sp_membership.status_id IN (".implode(', ', $current_status_ids).")
      ".$where." AND sp_membership.id is null");
    $this->assign('found', $count);

    if ($where == $original_where && isset($_POST['continue']) && !empty($_POST['continue'])) {
      $queue = CRM_Queue_Service::singleton()->create(array(
        'type' => 'Sql',
        'name' => 'nl.sp.rood.upgrade',
        'reset' => TRUE, //do not flush queue upon creation
      ));

      $dao = CRM_Core_DAO::executeQuery("SELECT civicrm_membership.id
       FROM civicrm_membership
      INNER JOIN civicrm_contact ON civicrm_membership.contact_id = civicrm_contact.id
      LEFT JOIN civicrm_membership sp_membership ON civicrm_contact.id = sp_membership.contact_id AND sp_membership.membership_type_id = '{$formValues['sp_mtype']}' AND sp_membership.status_id IN (".implode(', ', $current_status_ids).")
      ".$where." AND sp_membership.id is null
      ");
      $i = 1;
      while ($dao->fetch()) {
        $title = ts('Upgrade Rood lidmaatschappen %1 van %2', array(
          1 => $i,
          2 => $count,
        ));

        //create a task without parameters
        $task = new CRM_Queue_Task(
          array(
            'CRM_Rood_MembershipUpgrade',
            'UpgradeFromQueue'
          ), //call back method
          array($dao->id, $formValues), //parameters,
          $title
        );
        //now add this task to the queue
        $queue->createItem($task);
        $i++;
      }

      $runner = new CRM_Queue_Runner(array(
        'title' => ts('Upgrade rood lidmaatschappen'), //title fo the queue
        'queue' => $queue, //the queue object
        'errorMode'=> CRM_Queue_Runner::ERROR_ABORT, //abort upon error and keep task in queue
        'onEnd' => array('CRM_Rood_Form_UpgradeRoodMembership', 'onEnd'), //method which is called as soon as the queue is finished
        'onEndUrl' => CRM_Utils_System::url('civicrm/member/upgrade_rood', 'reset=1'), //go to page after all tasks are finished
      ));

      $runner->runAllViaWeb(); // does not return
    }
  }

  static function onEnd(CRM_Queue_TaskContext $ctx) {
    //set a status message for the user
    CRM_Core_Session::setStatus('Lidmaatschappen upgrade voltooid', '', 'success');
  }


}