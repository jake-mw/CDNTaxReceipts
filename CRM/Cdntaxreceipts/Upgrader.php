<?php

/**
 * Collection of upgrade steps
 */
class CRM_Cdntaxreceipts_Upgrader extends CRM_Cdntaxreceipts_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   */
  public function install() {
    $this->createTables();

    $email_message = '{$contact.email_greeting_display},

Attached please find your official tax receipt for income tax purposes.

{$orgName}';
    $email_subject = 'Your tax receipt {$receipt.receipt_no}';

    $this->_create_message_template($email_message, $email_subject);
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   */
  public function uninstall() {
    $this->executeSqlFile('sql/uninstall.sql');
  }

  /**
   * Get the character set and collation that the core CiviCRM tables are
   * currently using.
   * @return array
   */
  private function getDatabaseCharacterSettings():array {
    $values = [
      'charset' => 'utf8',
      'collation' => 'utf8_unicode_ci',
    ];
    // This doesn't exist before 5.29. Not worth implementing ourselves, just
    // use defaults above.
    if (method_exists('CRM_Core_BAO_SchemaHandler', 'getInUseCollation')) {
      $values['collation'] = CRM_Core_BAO_SchemaHandler::getInUseCollation();
      if (stripos($values['collation'], 'utf8mb4') !== FALSE) {
        $values['charset'] = 'utf8mb4';
      }
    }
    return $values;
  }

  /**
   * Create the tables.
   *
   * changes made in:
   *   0.9.beta1
   *   1.5.4 - use same character set that core tables are currently using
   *
   * NOTE: We avoid direct foreign keys to CiviCRM schema because this log should
   * remain intact even if a particular contact or contribution is deleted (for
   * auditing purposes).
   */
  protected function createTables() {
    $character_settings = $this->getDatabaseCharacterSettings();

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS cdntaxreceipts_log_contributions");
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS cdntaxreceipts_log");

    CRM_Core_DAO::executeQuery("CREATE TABLE cdntaxreceipts_log (
id int(11) NOT NULL AUTO_INCREMENT COMMENT 'The internal id of the issuance.',
receipt_no varchar(128) NOT NULL  COMMENT 'Receipt Number.',
issued_on int(11) NOT NULL COMMENT 'Unix timestamp of when the receipt was issued, or re-issued.',
contact_id int(10) unsigned NOT NULL COMMENT 'CiviCRM contact id to whom the receipt is issued.',
receipt_amount decimal(10,2) NOT NULL COMMENT 'Receiptable amount, total minus non-receiptable portion.',
is_duplicate tinyint(4) NOT NULL COMMENT 'Boolean indicating whether this is a re-issue.',
uid int(10) unsigned NOT NULL COMMENT 'Drupal user id of the person issuing the receipt.',
ip varchar(128) NOT NULL COMMENT 'IP of the user who issued the receipt.',
issue_type varchar(16) NOT NULL COMMENT 'The type of receipt (single or annual).',
issue_method varchar(16) NULL COMMENT 'The send method (email or print).',
receipt_status varchar(10) DEFAULT 'issued' COMMENT 'The status of the receipt (issued or cancelled)',
email_tracking_id varchar(64) NULL COMMENT 'A unique id to track email opens.',
email_opened datetime NULL COMMENT 'Timestamp an email open event was detected.',
PRIMARY KEY (id),
INDEX contact_id (contact_id),
INDEX receipt_no (receipt_no)
) ENGINE=InnoDB DEFAULT CHARSET={$character_settings['charset']} COLLATE {$character_settings['collation']} COMMENT='Log file of tax receipt issuing.'");

    // The contribution_id is *deliberately* not a foreign key to civicrm_contribution.
    // We don't want to destroy audit records if contributions are deleted.
    CRM_Core_DAO::executeQuery("CREATE TABLE cdntaxreceipts_log_contributions (
id int(11) NOT NULL AUTO_INCREMENT COMMENT 'The internal id of this line.',
receipt_id int(11) NOT NULL COMMENT 'The internal receipt ID this line belongs to.',
contribution_id int(10) unsigned NOT NULL COMMENT 'CiviCRM contribution id for which the receipt is issued.',
contribution_amount decimal(10,2) DEFAULT NULL COMMENT 'Total contribution amount.',
receipt_amount decimal(10,2) NOT NULL COMMENT 'Receiptable amount, total minus non-receiptable portion.',
receive_date datetime NOT NULL COMMENT 'Date on which the contribution was received, redundant information!',
PRIMARY KEY (id),
FOREIGN KEY (receipt_id) REFERENCES cdntaxreceipts_log(id),
INDEX contribution_id (contribution_id)
) ENGINE=InnoDB DEFAULT CHARSET={$character_settings['charset']} COLLATE {$character_settings['collation']} COMMENT='Contributions for each tax reciept issuing.'");
  }

  /**
   * @TODO This function is buggy - it returns false when the field already
   * exists. Also the entire function could just be replaced with CRM_Upgrade...addColumn().
   */
  public function upgrade_1320() {
    $this->ctx->log->info('Applying update 1.3.2');
    $dao =& CRM_Core_DAO::executeQuery("SELECT 1");
    $db_name = $dao->_database;
    $dao =& CRM_Core_DAO::executeQuery("
SELECT COUNT(*) as col_count
FROM information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = '{$db_name}'
AND TABLE_NAME = 'cdntaxreceipts_log'
AND COLUMN_NAME = 'receipt_status'");
    if ($dao->fetch()) {
      if ($dao->col_count == 0) {
        CRM_Core_DAO::executeQuery("ALTER TABLE cdntaxreceipts_log ADD COLUMN receipt_status varchar(10) DEFAULT 'issued'");
        $ndao =& CRM_Core_DAO::executeQuery("
SELECT COUNT(*) as col_count
FROM information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = '{$db_name}'
AND TABLE_NAME = 'cdntaxreceipts_log'
AND COLUMN_NAME = 'receipt_status'");
        if ($ndao->fetch()) {
          if ($ndao->col_count == 1) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * @TODO replace with CRM_Upgrade...addColumn and also there's one called
   * safeIndex() or something like that.
   */
  public function upgrade_1321() {
    $this->ctx->log->info('Applying update 1321: Email Tracking');
    CRM_Core_DAO::executeQuery('ALTER TABLE cdntaxreceipts_log ADD email_tracking_id varchar(64) NULL');
    CRM_Core_DAO::executeQuery('ALTER TABLE cdntaxreceipts_log ADD email_opened datetime NULL');
    CRM_Core_DAO::executeQuery('CREATE INDEX contribution_id ON cdntaxreceipts_log_contributions (contribution_id)');
    return TRUE;
  }

  public function upgrade_1322() {
    $this->ctx->log->info('Applying update 1322: Message Templates');
    $current_message = Civi::settings()->get('email_message');
    $current_subject = Civi::settings()->get('email_subject') . ' {$receipt.receipt_no}';
    return $this->_create_message_template($current_message, $current_subject);
  }

  public function upgrade_1410() {
    $this->ctx->log->info('Applying update 1410: Data mode');
    $email_enabled = Civi::settings()->get('enable_email');
    if ($email_enabled) {
      Civi::settings()->set('delivery_method', 1);
    }
    else {
      Civi::settings()->set('delivery_method', 0);
    }
    return TRUE;
  }

  /**
   * Update uploaded file paths to be relative instead of absolute.
   */
  public function upgrade_1411() {
    $this->ctx->log->info('Applying update 1411: uploaded file paths');
    foreach (array('receipt_logo', 'receipt_signature', 'receipt_watermark', 'receipt_pdftemplate') as $fileSettingName) {
      $path = Civi::settings()->get($fileSettingName);
      if (!empty($path)) {
        Civi::settings()->set($fileSettingName, basename($path));
      }
    }
    return TRUE;
  }

  public function _create_message_template($email_message, $email_subject) {

    $html_message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
 <title></title>
</head>
<body>
{capture assign=headerStyle}colspan="2" style="text-align: left; padding: 4px; border-bottom: 1px solid #999; background-color: #eee;"{/capture}
{capture assign=labelStyle }style="padding: 4px; border-bottom: 1px solid #999; background-color: #f7f7f7;"{/capture}
{capture assign=valueStyle }style="padding: 4px; border-bottom: 1px solid #999;"{/capture}

<center>
 <table width="620" border="0" cellpadding="0" cellspacing="0" style="font-family: Arial, Verdana, sans-serif; text-align: left;">

  <!-- BEGIN HEADER -->
  <!-- You can add table row(s) here with logo or other header elements -->
  <!-- END HEADER -->

  <!-- BEGIN CONTENT -->

  <tr>
   <td>
    <p>' . nl2br(htmlspecialchars($email_message)) . '</p>
   </td>
  </tr>
  <tr>
 </table>
</center>
{$openTracking}
</body>
</html>';

    // create message template for email that accompanies tax receipts
    $params = array(
      'sequential' => 1,
      'name' => 'msg_tpl_workflow_cdntaxreceipts',
      'title' => 'Message Template Workflow for CDN Tax Receipts',
      'description' => 'Message Template Workflow for CDN Tax Receipts',
      'is_reserved' => 1,
      'is_active' => 1,
      'api.OptionValue.create' => array(
        '0' => array(
          'label' => 'CDN Tax Receipts - Email Single Receipt',
          'value' => 1,
          'name' => 'cdntaxreceipts_receipt_single',
          'is_reserved' => 1,
          'is_active' => 1,
          'format.only_id' => 1,
        ),
        '1' => array(
          'label' => 'CDN Tax Receipts - Email Annual/Aggregate Receipt',
          'value' => 2,
          'name' => 'cdntaxreceipts_receipt_aggregate',
          'is_reserved' => 1,
          'is_active' => 1,
          'format.only_id' => 1,
        ),
      ),
    );
    $result = civicrm_api3('OptionGroup', 'create', $params);

    $params = array(
      'msg_title' => 'CDN Tax Receipts - Email Single Receipt',
      'msg_subject' => $email_subject,
      'msg_text' => $email_message,
      'msg_html' => $html_message,
      'workflow_id' => $result['values'][0]['api.OptionValue.create'][0],
      'is_default' => 1,
      'is_reserved' => 0,
    );
    civicrm_api3('MessageTemplate', 'create', $params);

    $params = array(
      'msg_title' => 'CDN Tax Receipts - Email Annual/Aggregate Receipt',
      'msg_subject' => $email_subject,
      'msg_text' => $email_message,
      'msg_html' => $html_message,
      'workflow_id' => $result['values'][0]['api.OptionValue.create'][1],
      'is_default' => 1,
      'is_reserved' => 0,
    );
    civicrm_api3('MessageTemplate', 'create', $params);

    return TRUE;
  }

  /**
   * Example: Run an external SQL script
   *
   * @return TRUE on success
   * @throws Exception
   */
  /*
  public function upgrade_4201() {
  $this->ctx->log->info('Applying update 4201');
  // this path is relative to the extension base dir
  $this->executeSqlFile('sql/upgrade_4201.sql');
  return TRUE;
  } */

}
