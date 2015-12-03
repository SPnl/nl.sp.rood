<?php

class CRM_Rood_UpgradeSelector {

  protected $where;

  public function __construct() {
    $this->load();
  }

  public function load() {
    if (isset($_SESSION['CRM_Rood_UpgradeSelector'])) {
      $this->where = $_SESSION['CRM_Rood_UpgradeSelector'];
    }
  }

  public function store() {
    $_SESSION['CRM_Rood_UpgradeSelector'] = $this->where;
  }

  public function setData($rood_mtype, $status_ids, $birth_date_from_range, $birth_date_to_range) {
    $this->where = "WHERE 1 ";
    $this->where .= " AND civicrm_membership.membership_type_id = '".$rood_mtype."'";
    if (is_array($status_ids) && count($status_ids)) {
      $this->where .= " AND civicrm_membership.status_id IN (".implode(",", $status_ids).")";
    }
    if (!empty($birth_date_from_range) && !empty($birth_date_to_range)) {
      $from = new DateTime($birth_date_from_range);
      $to = new DateTime($birth_date_to_range);
      $this->where .= " AND DATE(civicrm_contact.birth_date) >= DATE('".$from->format('Y-m-d')."') AND DATE(civicrm_contact.birth_date) <= DATE('".$to->format('Y-m-d')."')";
    } elseif (!empty($birth_date_from_range) && empty($birth_date_to_range)) {
      $from = new DateTime($birth_date_from_range);
      $this->where .= " AND DATE(civicrm_contact.birth_date) >= DATE('".$from->format('Y-m-d')."')";
    } elseif (empty($birth_date_from_range) && !empty($birth_date_to_range)) {
      $to = new DateTime($birth_date_to_range);
      $this->where .= " AND DATE(civicrm_contact.birth_date) <= DATE('".$to->format('Y-m-d')."')";
    }
  }

  public function getWhere() {
    return $this->where;
  }

}