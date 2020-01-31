<?php

/*
 * Functions to save and read data from my table
 */

/**
 * cdntaxreceipts_issued_on
 *
 * Return unix timestamp of when the contribution was first issued
 * This checks both 'single' and 'annual' receipts.
 */

function cdntaxreceipts_issued_on($contribution_id) {

  $sql = "SELECT issued_on, l.id as receipt_id FROM cdntaxreceipts_log l
    INNER JOIN cdntaxreceipts_log_contributions c ON l.id = c.receipt_id
    WHERE is_duplicate = 0 AND contribution_id = $contribution_id";

  $dao = CRM_Core_DAO::executeQuery($sql);
  if ( $dao->fetch() ) {
    return array($dao->issued_on, $dao->receipt_id);
  }
  else {
    return array(NULL, NULL);
  }

}

/**
 * cdntaxreceipts_annual_issued_on
 *
 * Returns list($issued_on, receipt_id) of when an annual receipt was issued for the given contact and year.
 * (NULL,NULL) if no annual receipt has been issued.
 */

function cdntaxreceipts_annual_issued_on($contactId, $year) {

  $sql = "SELECT issued_on, l.id as receipt_id FROM cdntaxreceipts_log l
    INNER JOIN cdntaxreceipts_log_contributions c ON l.id = c.receipt_id
    WHERE is_duplicate = 0 AND issue_type = 'annual' AND contact_id = $contactId
      AND year(receive_date) = $year";

  $dao = CRM_Core_DAO::executeQuery($sql);
  if ( $dao->fetch() ) {
    return array($dao->issued_on, $dao->receipt_id);
  }
  else {
    return array(NULL, NULL);
  }

}/**
 * cdntaxreceipts_all_issued_on
 *
 * Return all records of when the contribution was receipted
 * This checks 'single', 'annual' and 'aggregate' receipts.
 *
 * @param $contribution_id - the contribution being looked up
 */

function cdntaxreceipts_all_issued_on($contribution_id) {

  $issued_on = array();

  $sql = "
    SELECT l.issued_on, l.id as receipt_id, l.is_duplicate, l.issue_type, l.issue_method
    FROM cdntaxreceipts_log l
    INNER JOIN cdntaxreceipts_log_contributions c ON l.id = c.receipt_id
    WHERE contribution_id = {$contribution_id}
    GROUP BY l.issued_on, l.is_duplicate, l.id as receipt_id, l.issue_type, l.issue_method";

  $dao = CRM_Core_DAO::executeQuery($sql);
  $count = 0;
  while ($dao->fetch()) {
    $issued_on['receipts'][$count] = array(
      $dao->issued_on,
      $dao->issue_method,
      $dao->receipt_id,
      $dao->is_duplicate,
      $dao->issue_type
    );
    $count++;
  }
  $issued_on['count'] = $count;
  return $issued_on;
}


function cdntaxreceipts_contributions_not_receipted($contactId, $year) {

  $sql = "SELECT contrib.id as contribution_id, contrib.total_amount,
        ifnull(contrib.non_deductible_amount, 0) as non_deductible_amount, contrib.receive_date
    FROM civicrm_contribution contrib
    INNER JOIN civicrm_financial_type contrib_type
            ON contrib.financial_type_id = contrib_type.id
    LEFT  JOIN cdntaxreceipts_log_contributions cdntax_c
            ON contrib.id = cdntax_c.contribution_id
    WHERE cdntax_c.receipt_id IS NULL AND contrib_type.name <> 'In-kind'
      AND contrib.contact_id = $contactId AND YEAR(contrib.receive_date) = $year
    ORDER BY contrib.id";

  $dao = CRM_Core_DAO::executeQuery($sql);
  $contributions = array();

  // Rather than loading each CRM_Contribute_DAO_Contribution object, we'll just
  // transfer the information returned from the query.  This should save a whole
  // lot of database calls versus loading each contribution object individually.

  while ( $dao->fetch() ) {
    if ( cdntaxreceipts_eligibleForReceipt($dao->contribution_id) ) {
      $contributions[] = array(
        'contribution_id' => $dao->contribution_id,
        'total_amount' => $dao->total_amount,
        'non_deductible_amount' => $dao->non_deductible_amount,
        'receive_date' => $dao->receive_date,
      );
    }
  }

  return $contributions;
}

/**
 * cdntaxreceipts_load_receipt
 *
 * Return associative array of receipt details for the given receipt_id
 */

function cdntaxreceipts_load_receipt($receipt_id) {
  $receipt = array();
  if (!isset($receipt_id)) {
    return $receipt;
  }

  $sql = "SELECT l.id, l.receipt_no, l.issued_on, l.contact_id, l.receipt_amount as total_receipt,
        l.is_duplicate, l.uid, l.ip, l.issue_type, l.issue_method, l.receipt_status,
        l.email_tracking_id, l.email_opened,
        c.contribution_id, c.contribution_amount, c.receipt_amount, c.receive_date
    FROM cdntaxreceipts_log l
    INNER JOIN cdntaxreceipts_log_contributions c ON l.id = c.receipt_id
    WHERE is_duplicate = 0 AND l.id = {$receipt_id}";

  $dao = CRM_Core_DAO::executeQuery($sql);

  if ( $dao->fetch() ) {
    $issued_by = CRM_Core_BAO_UFMatch::getUFValues($dao->uid);
    $receipt = array(
      'id' => $dao->id,
      'receipt_no' => $dao->receipt_no,
      'issued_on' => date('Y-m-d H:i:s', $dao->issued_on),
      'contact_id' => $dao->contact_id,
      'receipt_amount' => $dao->total_receipt,
      'is_duplicate' => $dao->is_duplicate,
      'uid' => $dao->uid,
      'uname' => $issued_by['uf_name'] . ' (' . $dao->uid . ')',
      'ip' => $dao->ip,
      'issue_type' => $dao->issue_type,
      'display_type' => _cdntaxreceipts_get_display_type($dao->issue_type),
      'issue_method' => $dao->issue_method,
      'receipt_status' => $dao->receipt_status,
      'email_tracking_id' => $dao->email_tracking_id,
      'email_opened' => $dao->email_opened,
    );

    $contributions = array();
    do {
      $contributions[] = array(
        'contribution_id' => $dao->contribution_id,
        'contribution_amount' => $dao->contribution_amount,
        'receipt_amount' => $dao->receipt_amount,
        'receive_date' => $dao->receive_date,
      );
    } while ( $dao->fetch() );

    $receipt['contributions'] = $contributions;
  }

  return $receipt;

}

/**
 * cdntaxreceipts_log
 *
 * Log the issuance
 */

 //TODO: Should return success or failure
function cdntaxreceipts_log($receipt) {

  $uid = CRM_Utils_System::getLoggedInUfID();
  $uid = empty($uid) ? 1 : $uid;
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

  $transaction = new CRM_Core_Transaction();
  // create the main entry
  $params = array(
    1 => array( $receipt['receipt_no'], 'String' ),
    2 => array( $receipt['issued_on'], 'Integer' ),
    3 => array( $receipt['contact_id'], 'Integer' ),
    4 => array( $receipt['receipt_amount'], 'Money' ),
    5 => array( $receipt['is_duplicate'], 'Boolean' ),
    6 => array( $uid, 'Integer' ),
    7 => array( $ip, 'String' ),
    8 => array( $receipt['issue_type'], 'String' ),
    9 => array( $receipt['issue_method'], 'String' ),
    10 => array( $receipt['receipt_status'], 'String' ),
    11 => array( $receipt['email_tracking_id'], 'String' ),
  );
  $sql = "INSERT INTO cdntaxreceipts_log (receipt_no, issued_on, contact_id, receipt_amount,
    is_duplicate, uid, ip, issue_type, issue_method, receipt_status, email_tracking_id)
      VALUES (%1, %2, %3, %4, %5, %6, %7, %8, %9, %10, %11)";
  $result = CRM_Core_DAO::executeQuery($sql, $params);
  if (is_a($result, 'DB_Error')) {
    return FALSE;
  }
  $receipt_id = CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');

  // add line items
  foreach ( $receipt['contributions'] as $contribution ) {
    $params = array(
      1 => array( $receipt_id, 'Integer' ),
      2 => array( $contribution['contribution_id'], 'Integer' ),
      3 => array( $contribution['contribution_amount'], 'Money' ),
      4 => array( $contribution['receipt_amount'], 'Money' ),
      5 => array( $contribution['receive_date'], 'String' ),
    );
    $sql = "INSERT INTO cdntaxreceipts_log_contributions (receipt_id, contribution_id,
      contribution_amount, receipt_amount, receive_date)
        VALUES (%1, %2, %3, %4, %5)";
    $result = CRM_Core_DAO::executeQuery($sql, $params);
    if (is_a($result, 'DB_Error')) {
      return FALSE;
    }
  }
  $transaction->commit();
  return $receipt_id;

}

/**
 * cdntaxreceipts_cancel
 *
 * @param int $receipt_id
 * @return int $receipt_id
 *
 * Cancel the receipt
 */

function cdntaxreceipts_cancel($receipt_id) {

  // create the main entry
  $params = array(
    1 => array( $receipt_id, 'Integer' ),
  );
  $sql = "UPDATE cdntaxreceipts_log SET receipt_status = 'cancelled' WHERE id = %1";
  $result = CRM_Core_DAO::executeQuery($sql, $params);
  if (is_a($result, 'DB_Error')) {
    return FALSE;
  }

  return $receipt_id;

}

/**
 * @param array $contributionIds
 * @return array $contributions with the contents below
 *
 * $contributions[$id] = array(
 *   'contribution_id' => $id,
 *   'contact_id' => $dao->contact_id,
 *   'total_amount' => $dao->total_amount,
 *   'non_deductible_amount' => $dao->non_deductible_amount,
 *   'receive_date' => $dao->receive_date,
 *   'receive_year' => $dao->receive_year,
 *   'eligible' => $eligible,
 *   'receipt_id' => $dao->receipt_id,
);
 */
function cdntaxreceipts_contributions_get_status($contributionIds = array()) {
  $contributions = array();

  $ids = implode(',', $contributionIds);

  if (!empty($contributionIds)) {
    $sql = "
    SELECT
        contrib.id as contribution_id
      , contrib.contact_id
      , contrib.total_amount
      , ifnull(contrib.non_deductible_amount, 0) as non_deductible_amount
      , contrib.receive_date
      , YEAR(contrib.receive_date) as receive_year
      , IF(cdntax_c.receipt_id IS NULL, 0, cdntax_c.receipt_id) as receipt_id
    FROM civicrm_contribution contrib
    INNER JOIN civicrm_financial_type contrib_type
            ON contrib.financial_type_id = contrib_type.id
    LEFT  JOIN cdntaxreceipts_log_contributions cdntax_c
            ON contrib.id = cdntax_c.contribution_id
    WHERE contrib_type.name <> 'In-kind' AND contrib.id IN($ids)";

    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $id = $dao->contribution_id;

      $eligible = cdntaxreceipts_eligibleForReceipt($id);
      $contributions[$id] = array(
        'contribution_id' => $id,
        'contact_id' => $dao->contact_id,
        'total_amount' => $dao->total_amount,
        'non_deductible_amount' => $dao->non_deductible_amount,
        'receive_date' => $dao->receive_date,
        'receive_year' => $dao->receive_year,
        'eligible' => $eligible,
        'receipt_id' => $dao->receipt_id,
      );
    }
  }
  foreach ($contributions as $id => $contrib) {
    $contributions[$id]['non_deductible_amount'] = $contributions[$id]['total_amount'] - cdntaxreceipts_eligibleAmount($id);
  }

  return $contributions;
}

/**
 * Record a tax receipt as having been opened by the email
 * recipient.
 */
function cdntaxreceipts_process_open($tracking_id) {

  $sql = "
UPDATE cdntaxreceipts_log
SET email_opened = now()
WHERE email_tracking_id = %1 AND email_opened IS NULL";

  CRM_Core_DAO::executeQuery($sql, array(1 => array($tracking_id, 'String')));

}

/*
 * cdntaxreceipts_log_next_id()
 *
 * Get the next id to be generated in the log table.
 * Assumes that this table has an autoincrement id field.
 * Does not deal with locking issue.
 */
function cdntaxreceipts_log_next_id() {
  $sql = "SELECT id FROM cdntaxreceipts_log ORDER BY id DESC LIMIT 1";
  $last_id = CRM_Core_DAO::singleValueQuery($sql);
  return $last_id + 1;
}
