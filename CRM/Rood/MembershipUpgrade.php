<?php

class CRM_Rood_MembershipUpgrade {

  public static function UpgradeFromQueue(CRM_Queue_TaskContext $ctx, $mid, $params) {
    $today = new DateTime();
    $membership = civicrm_api3('Membership', 'getsingle', array('id' => $mid));
    $oldMembershipStatus = $params['rood_mstatus'];
    $migratie_lidmaatschappen_cg = civicrm_api3('CustomGroup', 'getvalue', array(
      'return' => 'id',
      'name' => 'Migratie_Lidmaatschappen',
    ));
    $reason_fid = civicrm_api3('CustomField', 'getvalue', array(
      'return' => 'id',
      'name' => 'Reden',
      'custom_group_id' => $migratie_lidmaatschappen_cg,
    ));
    unset($membership['id']);

    $dao = CRM_Core_DAO::executeQuery("
      SELECT `c`.`id` AS `contribution_id`
FROM `civicrm_membership`
INNER JOIN `civicrm_membership_payment` `mp` ON `civicrm_membership`.`id` = `mp`.`membership_id`
INNER JOIN `civicrm_contribution` `c` ON `mp`.`contribution_id` = `c`.`id` AND c.receive_date <= civicrm_membership.end_date
WHERE civicrm_membership.id = %1
ORDER BY c.receive_date DESC
LIMIT 0, 1", array(1=>array($mid, 'Integer')));

    if($dao->fetch()) {
      $contribution = self::getRenewalPayment($dao->contribution_id);

      $renewTransaction = new CRM_Core_Transaction();

      $membership['membership_type_id'] = $params['sp_mtype'];
      $membership['start_date'] = $today->format('Ymd');
      if ($today->format('m') >= 10) {
        $newEndDate = clone $today;
        $newEndDate->modify('last day of +1 year');
        $membership['end_date'] = $newEndDate->format('Ymd');
      }
      $new_membership = civicrm_api3('Membership', 'create', $membership);


      if ($contribution) {
        $contribution['financial_type_id'] = civicrm_api3('MembershipType', 'getvalue', array('return' => 'financial_type_id', 'id' => $params['sp_mtype']));
        $result = civicrm_api3('Contribution', 'create', $contribution);
        if (((float) $contribution['total_amount']) < ((float) $params['minimum_fee'])) {
          $contribution['total_amount'] = $params['minimum_fee'];
        }

        $membershipPayment['contribution_id'] = $result['id'];
        $membershipPayment['membership_id'] = $new_membership['id'];
        civicrm_api3('MembershipPayment', 'create', $membershipPayment);
      }


      $oldMembership['id'] = $mid;
      $oldMembership['is_override'] = 1;
      $oldMembership['status_id'] = $oldMembershipStatus;
      $oldMembership['custom_'.$reason_fid] = '28 jarige';
      $oldMembership['end_date'] = $today->format('Ymd');
      civicrm_api3('Membership', 'create', $oldMembership);

      $renewTransaction->commit();
    }
    return true;
  }

  protected static function getRenewalPayment($contributionId) {
    if (!$contributionId) {
      return false;
    }

    try {
      $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contributionId));
      $sql = "SELECT honor_contact_id, honor_type_id FROM civicrm_contribution WHERE id = %1";
      $dao = CRM_Core_DAO::executeQuery($sql, array( 1 => array($contribution['id'], 'Integer')));
      if ($dao->fetch() && $dao->honor_contact_id) {
        $contribution['honor_contact_id'] = $dao->honor_contact_id;
        $contribution['honor_type_id'] = $dao->honor_type_id;
      }
    } catch (Exception $ex) {
      return false;
    }

    $receiveDate = new DateTime();
    $contribution['receive_date'] = $receiveDate->format('YmdHis');
    $contribution['contribution_status_id'] = 2;//pending
    $instrument_id = self::getPaymenyInstrument($contribution);
    if ($instrument_id) {
      $contribution['payment_instrument_id'] = $instrument_id;
    }
    unset($contribution['contribution_payment_instrument']);
    unset($contribution['payment_instrument']);
    unset($contribution['instrument_id']);
    unset($contribution['contribution_id']);
    unset($contribution['invoice_id']);
    unset($contribution['id']);

    unset($contribution['display_name']);
    unset($contribution['contact_type']);
    unset($contribution['contact_sub_type']);
    unset($contribution['sort_name']);
    return $contribution;
  }

  protected static function getPaymenyInstrument($contribution) {
    if (empty($contribution['instrument_id'])) {
      return false;
    }

    $instrument_id = CRM_Core_OptionGroup::getValue('payment_instrument', $contribution['instrument_id'], 'id', 'Integer');
    if (empty($instrument_id)) {
      return false;
    }
    return $instrument_id;
  }

}