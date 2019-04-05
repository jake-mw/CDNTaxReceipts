<?php

require_once('CRM/Report/Form.php');
require_once('CRM/Contribute/PseudoConstant.php');
require_once('CRM/Utils/Type.php');
require_once('CRM/Utils/Array.php');

class CRM_Cdntaxreceipts_Form_Report_ReceiptsNotIssued extends CRM_Report_Form {

  protected $_useEligibilityHooks = false;
  CONST SETTINGS = 'CDNTaxReceipts';

  function __construct() {

    $this->_customGroupExtends = array('Contact', 'Individual', 'Organization');
    $this->_autoIncludeIndexedFieldsAsOrderBys = true;

    $this->_columns = array(
      'civicrm_contact' =>
      array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' =>
        array(
          'sort_name' =>
          array('title' => ts('Contact Name', array('domain' => 'org.civicrm.cdntaxreceipts')),
            'required' => true,
          ),
          'id' =>
          array(
            'no_display' => true,
            'required' => true,
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
      'civicrm_financial_type' =>
      array(
        'dao' => 'CRM_Financial_DAO_FinancialType',
        'fields' =>
        array(
          'financial_type' => array('default' => true),
        ),
        'grouping' => 'tax-fields',
      ),
      'civicrm_contribution' =>
      array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' =>
        array(
          'receive_date' => array('default' => true),
          'total_amount' => array('default' => true),
          'contribution_source' => array('default' => true),
          'id' => array('title' => 'Contribution ID', 'default' => true),
        ),
        'filters' =>
        array(
          'receive_date' =>
          array(
            'operatorType' => CRM_Report_Form::OP_DATE),
          'financial_type_id' =>
          array('title' => ts('Financial Type', array('domain' => 'org.civicrm.cdntaxreceipts')),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Contribute_PseudoConstant::financialType(),
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
        'grouping' => 'tax-fields',
        'order_bys' =>
        array(
          'total_amount' => null,
          'receive_date' =>
          array(
            'default' => '1', 'default_weight' => '0', 'default_order' => 'DESC',
          ),
        ),
      ),
    );

    if (CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'enable_advanced_eligibility_report', null, 0) == 1) {
      $enable_options = array( 1 => ts('Yes'), 0 => ts('No'));
    }
    elseif (CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'enable_advanced_eligibility_report', null, 0) == 0) {
      $enable_options = array( 0 => ts('No'), 1 => ts('Yes'));
    }
    $this->_options =
      array(
        'use_advanced_eligibility' => array('title' => ts('Use Advanced Eligibility (Hooks - Memory Intensive)', array('domain' => 'org.civicrm.cdntaxreceipts')),
          'type' => 'select',
          'options' => $enable_options,
        ),
    );
    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();

    //check for permission to edit contributions
    if ( ! CRM_Core_Permission::check('access CiviContribute') ) {
      require_once('CRM/Core/Error.php');
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
            if ( $fieldName == 'total_amount' && $this->_useEligibilityHooks) {
              $field['dbAlias'] = "cdntax_t.eligible_amount";
            }
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

  function from($includeTemp = true) {

    $this->_from = "
        FROM civicrm_contact {$this->_aliases['civicrm_contact']}
        INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id AND
                   {$this->_aliases['civicrm_contribution']}.is_test = 0
        LEFT  JOIN civicrm_financial_type {$this->_aliases['civicrm_financial_type']}
                ON {$this->_aliases['civicrm_contribution']}.financial_type_id ={$this->_aliases['civicrm_financial_type']}.id
        LEFT  JOIN cdntaxreceipts_log_contributions cdntax_c
                ON {$this->_aliases['civicrm_contribution']}.id = cdntax_c.contribution_id ";

    if ($includeTemp && $this->_useEligibilityHooks) {
      $this->_from .= "
        LEFT JOIN cdntaxreceipts_temp_civireport_eligible cdntax_t
                ON {$this->_aliases['civicrm_contribution']}.id = cdntax_t.contribution_id ";
    }
  }

  function where($includeTemp = true) {

    parent::where();
    $this->_where .= "
    AND cdntax_c.id IS NULL AND {$this->_aliases['civicrm_contact']}.is_deleted = 0
    ";

    if ($includeTemp && $this->_useEligibilityHooks) {
      $this->_where .= "
      AND cdntax_t.contribution_id IS NOT NULL
      ";
    }
    else {
      $this->_where .= "
      AND {$this->_aliases['civicrm_contribution']}.contribution_status_id = 1
      AND {$this->_aliases['civicrm_financial_type']}.is_deductible = 1
      AND ({$this->_aliases['civicrm_contribution']}.total_amount - COALESCE({$this->_aliases['civicrm_contribution']}.non_deductible_amount,0)) > 0
      ";
    }

  }

  function postProcess() {

    $this->beginPostProcess();

    if (array_key_exists('use_advanced_eligibility', $this->_params)) {
      if ($this->_params['use_advanced_eligibility'] == 1) {
        $select[] = " '' as blankColumnBegin";
        $this->_useEligibilityHooks = true;
      }
    }
    // set up the temporary tables to do eligibility calculations
    if ($this->_useEligibilityHooks) {
      $this->createTempEligibilityTable();
    }

    $sql = $this->buildQuery(true);

    $rows = $graphRows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function createTempEligibilityTable() {
    $sql = "
CREATE TEMPORARY TABLE cdntaxreceipts_temp_civireport_eligible (
  contribution_id int unsigned,
  eligible_amount decimal(20,2)
) ENGINE=HEAP DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci";
    CRM_Core_DAO::executeQuery($sql);

    $this->from(false);
    $this->where(false);

    // if no receive_date filter, then limit to beginning of current month to prevent lock-up on large data sets
    if (strpos($this->_where, 'receive_date') === false) {
      $month = date("Ym", time()) . '01';
      $this->_where .= " AND {$this->_aliases['civicrm_contribution']}.receive_date >= {$month} ";
      CRM_Core_Session::setStatus(ts('For performance reasons, date range is limited to the current month. If you want to a different date range, please use the date filter.'), '', 'info');
    }

    $sql = "SELECT {$this->_aliases['civicrm_contribution']}.id $this->_from $this->_where";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ( $dao->fetch() ) {
      if ( cdntaxreceipts_eligibleForReceipt($dao->id) ) {
        $amount = cdntaxreceipts_eligibleAmount($dao->id);
        $sql = "INSERT INTO cdntaxreceipts_temp_civireport_eligible (contribution_id,eligible_amount)
                VALUES ($dao->id, $amount)";
        CRM_Core_DAO::executeQuery($sql);
      }
    }
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = false;
    foreach ($rows as $rowNum => $row) {

      // change contact name with link
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
                  'reset=1&cid=' . $row['civicrm_contact_id'],
                  $this->_absoluteUrl
               );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact");
        $entryFound = true;
      }

      if (array_key_exists('civicrm_contribution_id', $row)) {
        $url = CRM_Utils_System::url("civicrm/contact/view/contribution",
                  'reset=1&id=' . $row['civicrm_contribution_id'] . '&cid=' . $row['civicrm_contact_id'] . '&action=view&context=contribution&selectedChild=contribute',
                  $this->_absoluteUrl
               );
        $rows[$rowNum]['civicrm_contribution_id_link'] = $url;
        $rows[$rowNum]['civicrm_contribution_id_hover'] = ts("View Details of this Contribution");
        $entryFound = true;
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }
}

