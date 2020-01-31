<?php

require_once('CRM/Report/Form.php');
require_once('CRM/Utils/Type.php');

class CRM_Cdntaxreceipts_Form_Report_ReceiptsIssued extends CRM_Report_Form {

  protected $_where = NULL;

  function __construct() {

    $this->_customGroupExtends = array('Contact', 'Individual', 'Organization', 'Contribution');
    $this->_autoIncludeIndexedFieldsAsOrderBys = TRUE;

    $this->_columns = array(
      'civicrm_contact' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' =>
        array(
          'sort_name' =>
          array('title' => ts('Contact Name', array('domain' => 'org.civicrm.cdntaxreceipts')),
            'required' => TRUE,
          ),
          'id' =>
          array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'grouping' => 'tax-fields',
        'order_bys' =>
        array(
          'sort_name' =>
          array(
            'title' => ts('Last Name, First Name', array('domain' => 'org.civicrm.cdntaxreceipts')),
          ),
        ),
      ),
      'civicrm_cdntaxreceipts_log' =>
      array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' =>
        array(
          'issued_on' => array('title' => 'Issued On', 'default' => TRUE,'type' => CRM_Utils_Type::T_TIMESTAMP,),
          'receipt_amount' => array('title' => 'Receipt Amount', 'default' => TRUE, 'type' => CRM_Utils_Type::T_MONEY,),
          'receipt_no' => array('title' => 'Receipt No.', 'default' => TRUE),
          'issue_type' => array('title' => 'Issue Type', 'default' => TRUE),
          'issue_method' => array('title' => 'Issue Method', 'default' => TRUE),
          'uid' => array('title' => 'Issued By', 'default' => TRUE),
          'receipt_status' => array('title' => 'Receipt Status', 'default' => TRUE,),
          'email_opened' => array('title' => 'Email Open Date', 'type' => CRM_Utils_Type::T_TIMESTAMP, 'default' => TRUE),
        ),
        'grouping' => 'tax-fields',
        'filters' =>
        array(
          'issued_on' =>
          array(
            'title' => 'Issued On',
            'type' => CRM_Utils_Type::T_TIMESTAMP,
            'operatorType' => CRM_Report_Form::OP_DATE),
          'issue_type' =>
            array(
              'title' => ts('Issue Type'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => array('single' => ts('Single'), 'annual' => ts('Annual'), 'aggregate' => ts('Aggregate')),
              'type' => CRM_Utils_Type::T_STRING,
            ),
          'issue_method' =>
            array(
            'title' => ts('Issue Method', array('domain' => 'org.civicrm.cdntaxreceipts')),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => array('email' => 'Email', 'print' => 'Print'),
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'receipt_status' =>
            array(
              'title' => ts('Receipt Status', array('domain' => 'org.civicrm.cdntaxreceipts')),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => array('issued' => 'Issued', 'cancelled' => 'Cancelled'),
              'type' => CRM_Utils_Type::T_STRING,
            ),
          'email_opened' =>
          array('title' => ts('Email Open Date', array('domain' => 'org.civicrm.cdntaxreceipts')),
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'order_bys' =>
        array(
          'issued_on' =>
            array(
              'title' => 'Issued On', 'default' => '1', 'default_weight' => '0', 'default_order' => 'DESC',
            ),
          'receipt_no' =>
            array(
              'title' => ts('Receipt No.', array('domain' => 'org.civicrm.cdntaxreceipts')),
            ),
          'receipt_amount' =>
            array(
              'title' => ts('Receipt Amount', array('domain' => 'org.civicrm.cdntaxreceipts')),
            ),
          'receipt_status' =>
            array(
              'title' => ts('Receipt Status', array('domain' => 'org.civicrm.cdntaxreceipts')),
            ),
        ),
      ),
      'civicrm_cdntaxreceipts_log_contributions' =>
      array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' =>
        array(
          'contribution_id' => array(
            'default' => TRUE,
            'dbAlias' => "GROUP_CONCAT(DISTINCT cdntaxreceipts_log_contributions_civireport.contribution_id ORDER BY cdntaxreceipts_log_contributions_civireport.contribution_id SEPARATOR ', ')", ),
        ),
        'grouping' => 'tax-fields',
      ),
      'civicrm_contribution' => // for joining custom contribution fields
      array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
      ),
    );

    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();

    //check for permission to edit contributions
    if ( ! CRM_Core_Permission::check('access CiviContribute') ) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page', array('domain' => 'org.civicrm.cdntaxreceipts')));
    }
  }

  function select() {
    $select = array();
    $this->_columnHeaders = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            $alias = "{$tableName}_{$fieldName}";
            $select[] = "{$field['dbAlias']} as {$alias}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_selectAliases[] = $alias;
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  static
  function formRule($fields, $files, $self) {
    $errors = $grouping = array();
    return $errors;
  }

  function from() {
    $this->_from = "
        FROM cdntaxreceipts_log {$this->_aliases['civicrm_cdntaxreceipts_log']}
        INNER JOIN cdntaxreceipts_log_contributions {$this->_aliases['civicrm_cdntaxreceipts_log_contributions']}
                ON {$this->_aliases['civicrm_cdntaxreceipts_log']}.id = {$this->_aliases['civicrm_cdntaxreceipts_log_contributions']}.receipt_id
        LEFT  JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                ON {$this->_aliases['civicrm_contribution']}.id = {$this->_aliases['civicrm_cdntaxreceipts_log_contributions']}.contribution_id 
        LEFT  JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_cdntaxreceipts_log']}.contact_id ";

  }

  function groupBy( ) {
    // required for GROUP_CONCAT
    $this->_groupBy = "GROUP BY {$this->_aliases['civicrm_cdntaxreceipts_log']}.id";
  }

  function postProcess() {

    $this->beginPostProcess();

    $sql = $this->buildQuery(TRUE);

    $rows = $graphRows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      // change contact name with link
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        require_once('CRM/Utils/System.php');
        $url = CRM_Utils_System::url("civicrm/contact/view",
                  'reset=1&cid=' . $row['civicrm_contact_id'],
                  $this->_absoluteUrl
               );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_cdntaxreceipts_log_issue_type', $row)) {
        if ($rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_type'] == 'single' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_type'] = ts('Single', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        elseif ($rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_type'] == 'annual' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_type'] = ts('Annual', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        elseif ($rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_type'] == 'aggregate' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_type'] = ts('Aggregate', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_cdntaxreceipts_log_issue_method', $row)) {
        if ($rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_method'] == 'print' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_method'] = ts('Print', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        elseif ($rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_method'] == 'email' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_issue_method'] = ts('Email', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_cdntaxreceipts_log_receipt_status', $row)) {
        if ($rows[$rowNum]['civicrm_cdntaxreceipts_log_receipt_status'] == 'issued' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_receipt_status'] = ts('Issued', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        elseif ($rows[$rowNum]['civicrm_cdntaxreceipts_log_receipt_status'] == 'cancelled' ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_receipt_status'] = ts('Cancelled', array('domain' => 'org.civicrm.cdntaxreceipts'));
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_cdntaxreceipts_log_issued_on', $row)) {
        $rows[$rowNum]['civicrm_cdntaxreceipts_log_issued_on'] = date('Y-m-d', $rows[$rowNum]['civicrm_cdntaxreceipts_log_issued_on']);
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_cdntaxreceipts_log_uid', $row)) {
        $issued_by = CRM_Core_BAO_UFMatch::getUFValues($rows[$rowNum]['civicrm_cdntaxreceipts_log_uid']);
        if( $issued_by ) {
          $rows[$rowNum]['civicrm_cdntaxreceipts_log_uid'] = $issued_by['uf_name'];
          $entryFound = TRUE;
        }
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }

  function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $totalAmount = $average = array();
    $count = 0;
    $select = "
        SELECT COUNT({$this->_aliases['civicrm_cdntaxreceipts_log']}.receipt_amount ) as count,
               SUM( {$this->_aliases['civicrm_cdntaxreceipts_log']}.receipt_amount ) as amount,
               ROUND(AVG({$this->_aliases['civicrm_cdntaxreceipts_log']}.receipt_amount), 2) as avg
        ";

    $sql = "{$select}
      {$this->_from}
      {$this->_where}";

    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $totalAmount[] = CRM_Utils_Money::format($dao->amount, 'CAD');
      $average[] =   CRM_Utils_Money::format($dao->avg, 'CAD');
      $count += $dao->count;
    }
    $statistics['counts']['amount'] = array(
      'title' => ts('Total Amount Issued', array('domain' => 'org.civicrm.cdntaxreceipts')),
      'value' => implode(',  ', $totalAmount),
      'type' => CRM_Utils_Type::T_STRING,
    );
    $statistics['counts']['count'] = array(
      'title' => ts('Number Issued', array('domain' => 'org.civicrm.cdntaxreceipts')),
      'value' => $count,
    );
    $statistics['counts']['avg'] = array(
      'title' => ts('Average Amount Issued', array('domain' => 'org.civicrm.cdntaxreceipts')),
      'value' => implode(',  ', $average),
      'type' => CRM_Utils_Type::T_STRING,
    );
    return $statistics;
  }
}

