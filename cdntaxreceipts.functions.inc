<?php

require_once 'tcpdf/tcpdf.php';
require_once('FPDI/fpdi.php');

  class PDF extends FPDI {
    /**
     * "Remembers" the template id of the imported page
     */
    var $_tplIdx;

    /**
     * include a background template for every page
     */
    function Header() {
      $pdf_template_file = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_pdftemplate');
      if (!empty($pdf_template_file)) {

        if (is_null($this->_tplIdx)) {
          $pdf_template_file = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_pdftemplate');
          $this->setSourceFile($pdf_template_file);
          $this->_tplIdx = $this->importPage(1);
        }
        $this->useTemplate($this->_tplIdx);
      }
      else {
        $this->parsers = array();
      }
    }

    function Footer() {}
  }



  const CDNTAX_SETTINGS = 'CDNTaxReceipts';

/**
 * processTaxReceipt()
 * Accepts an associative array containing receipt variables, and:
 * - generates a PDF file using the provided variables
 * - sends a copy of the receipt to the email archive
 * - emails the receipt to the donor if issue_method='email'
 * - logs the receipt to the audit log
 *
 * This method is common to both single and annual receipts.
 */
function cdntaxreceipts_processTaxReceipt($receipt, &$collectedPdf = NULL, $mode, $method = NULL) {

  // Get contact details
  list($displayname, $email) = CRM_Contact_BAO_Contact::getContactDetails($receipt['contact_id']);

  // allow modules to alter any details
  // invoke hook_cdntaxreceipts_alter_receipt(&$receipt)
  //   modules should accept a reference to the $receipt array and alter it directly if they wish
  //   one useful use case: alter the $receipt['source'] line to include custom text
  //   to alter receipt amount, use hook_cdntaxreceipts_eligibleAmount() instead
  if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
    CRM_Utils_Hook::singleton()->invoke(1, $receipt, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_alter_receipt');
  } else {
    CRM_Utils_Hook::singleton()->invoke(1, $receipt, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_alter_receipt');
  }

  // generate the PDF file
  list($pdf_file, $user_friendly) = cdntaxreceipts_generateFormattedReceipt($receipt, $collectedPdf, $mode);

  // generate an email to the tax archive and possibly to the donor
  if ( $mode == CDNTAXRECEIPTS_MODE_PREVIEW ) {
    $sent = TRUE;
  }
  else {

    $org_name = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_name');
    $email_from = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'email_from');
    $email_archive = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'email_archive');

    // get all the basic contact information
    $params = array(
      'id' => $receipt['contact_id'],
    );
    $contact = civicrm_api3('contact', 'getsingle', $params);

    // add in the greetings (not return as part of the api result)
    $params = array(
      'id' => $receipt['contact_id'],
      'return.email_greeting' => 1,
    );
    $greetings = civicrm_api3('contact', 'getsingle', $params);
    $contact['email_greeting_display'] = $greetings['email_greeting_display'];

    $attachment = array(
      'fullPath' => $pdf_file,
      'mime_type' => 'application/pdf',
      'cleanName' => $user_friendly,
    );

    $tplParams = array(
      'orgName' => $org_name,
      'contact' => $contact,
      'receipt' => $receipt,
    );

    $sendTemplateParams = array(
      'groupName' => 'msg_tpl_workflow_cdntaxreceipts',
      'contactId' => $receipt['contact_id'],
      'tplParams' => $tplParams,
      'from' => $org_name . ' <' . $email_from . '> ',
      'toName' => $contact['display_name'],
      'attachments' => array( $attachment ),
    );

    if ($receipt['issue_type'] == 'single') {
      $sendTemplateParams['valueName'] = 'cdntaxreceipts_receipt_single';
    }
    else {
      $sendTemplateParams['valueName'] = 'cdntaxreceipts_receipt_aggregate';
    }

    // send a copy (without open tracking) to the tax archive
    $sendTemplateParams['toEmail'] = $email_archive;
    list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplate::sendTemplate($sendTemplateParams);

    // send a copy (with open tracking) to the donor (sometimes)
    if ($sent && $receipt['issue_method'] == 'email' && $mode != CDNTAXRECEIPTS_MODE_WORKFLOW) {
      $sendTemplateParams['toEmail'] = $contact['email'];
      $sendTemplateParams['tplParams']['openTracking'] = "<img src=\"" . CRM_Utils_System::baseURL() .
        "civicrm/cdntaxreceipts/open?r={$receipt['email_tracking_id']}\" width='1' height='1' alt='' border='0'>";
      list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplate::sendTemplate($sendTemplateParams);

      // TODO: log an activity
    }

    // log the receipt
    if (!$receipt['is_duplicate'] && $sent) {
      cdntaxreceipts_log($receipt);
    }
  }

  if ( isset($collectedPdf) ) {
    unlink($pdf_file); // file is no longer needed
    $pdf_file = NULL;
  }

  // if not unset, cron will clean up the PDF file later on
  return array( $sent, $receipt['issue_method'], $pdf_file );

}

/**
 * generateFormattedReceipt()
 * Accepts an associative array of receipt variables and produces a PDF file.
 * This function should always be called through processTaxReceipt().  It is imperative
 * that each receipt generated by the system gets logged and archived properly.
 */
function cdntaxreceipts_generateFormattedReceipt($receipt, &$collectedPdf = NULL, $mode) {

  require_once 'tcpdf/tcpdf.php';

  $pdf_img_files_path = dirname(__FILE__) . '/img/';

  $address = cdntaxreceipts_getAddress($receipt['contact_id']);

  $address_line_1 = isset($address['street_address']) ? $address['street_address'] : '';
  $parts = array();
  foreach(array('city', 'state_province', 'postal_code')  as $addr_part) {
    if (isset($address[$addr_part])) {
      $parts[] = $address[$addr_part];
    }
  }
  $address_line_1b = isset($address['supplemental_address_1']) ? $address['supplemental_address_1'] : '';
  $address_line_2 = implode(' ', $parts);
  $address_line_3 = isset($address['country']) ? $address['country'] : '';

  list($displayname, $email) = CRM_Contact_BAO_Contact::getContactDetails($receipt['contact_id']);
  if ( $receipt['issue_type'] == 'single' ) {
    $pos = strpos($receipt['receive_date'], '-');
    if ($pos === FALSE) {
      $date = substr($receipt['receive_date'], 0, 8);
      $display_date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
    }
    else {
      $display_date = substr($receipt['receive_date'], 0, 10);
    }
    $display_year = '';
  }
  else {
    $display_year = $receipt['receive_date'];
    $display_date = date('M j, Y', mktime(0,0,0,1,1,$receipt['receive_date'])) . ' - ' . date('M j, Y', mktime(0,0,0,12,31,$receipt['receive_date']));
  }

  $line_1 = ts("This is your Official Receipt for income tax purposes.", array('domain' => 'org.civicrm.cdntaxreceipts'));

  $config = CRM_Core_Config::singleton();
  $pdf_file = $config->uploadDir . 'Receipt-' . $receipt['receipt_no'] . '.pdf';
  $user_friendly = 'Receipt-' . $receipt['receipt_no'] . '.pdf';

  if (!defined('pdf_page_format')) {
    define('PDF_PAGE_FORMAT', 'USLETTER', true); // orig: 'A4'
  }
  $pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', TRUE, 'UTF-8', FALSE);
  $pdf->Open();

  $pdf->SetAuthor(CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_name'));

  $mymargin_left = 12;
  $mymargin_top = 6;
  $mymargin_right = 12;
  $pdf->SetMargins($mymargin_left, $mymargin_top, $mymargin_right);

  $pdf->setJPEGQuality('100');

  $pdf->SetAutoPageBreak('', $margin=0);

  $pdf_variables = array(
    "mode" => $mode,
    "mymargin_left" => $mymargin_left,
    "mymargin_top" => $mymargin_top,
    "is_duplicate" => $receipt['is_duplicate'],
    "pdf_img_files_path" => $pdf_img_files_path,
    "line_1" => $line_1,
    "source_funds" => isset($receipt['source']) ? $receipt['source'] : '',
    "amount" => $receipt['receipt_amount'],
    "display_date" => $display_date,
    "display_year" => $display_year,
    "issued_on" => date('Y-m-d', $receipt['issued_on']),
    "issue_type" => $receipt['issue_type'],
    "receipt_number" => $receipt['receipt_no'],
    "displayname" => $displayname,
    "address_line_1" => $address_line_1,
    "address_line_1b" => $address_line_1b,
    "address_line_2" => $address_line_2,
    "address_line_3" => $address_line_3,
    "inkind_values" => isset($receipt['inkind_values']) ? $receipt['inkind_values'] : array(),
    "receipt_contributions" => $receipt['contributions'],
    "receipt_status" => $receipt['receipt_status'],
  );

  $output_files = array($pdf);
  if ( isset($collectedPdf) && ( $receipt['issue_method'] == 'print' || $mode == CDNTAXRECEIPTS_MODE_PREVIEW) ) {
    $output_files[] = $collectedPdf;
  }

  foreach ( $output_files as $f ) {
    $f->AddPage();
    _cdntaxreceipts_writePage($f, $pdf_variables, $receipt);
  }

  // close and output the single receipt, but not the collected receipts...
  $pdf->Close();
  $pdf->Output($pdf_file, 'F');

  return array($pdf_file, $user_friendly);
}

function _cdntaxreceipts_writePage(&$f, $pdf_variables, $receipt) {

  //
  // hook_cdntaxreceipts_writeReceipt(&$pdf, $pdf_variables, $receipt)
  //
  // If there's a custom function to write the page, invoke it.
  // Otherwise, provide a default receipt format.
  //
  // Note: if hook_cdntaxreceipts_writeReceipt is implemented in more than one active
  // module/extension, the receipt will get written into the PDF file multiple
  // times. It's one peril of using the hook system here. Implementers will need to catch
  // this when testing their particular installs.
  //

  $results = CRM_Utils_Hook::singleton()->invoke(3, $f, $pdf_variables, $receipt, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_writeReceipt');

  if (is_array($results)) {
    foreach ($results as $result) {
      if ($result == TRUE) {
        return;
      }
    }
  }

  // if no custom function, go with default formatting...
  if ($pdf_variables['is_duplicate']) {
    // print duplicate copy
    _cdntaxreceipts_writeReceipt($f, $pdf_variables);
  }
  else {
    // print original AND duplicate copy
    $mymargin_top = $pdf_variables['mymargin_top'];
    _cdntaxreceipts_writeReceipt($f, $pdf_variables);
    $pdf_variables["mymargin_top"] = $mymargin_top + 90;
    $pdf_variables["is_duplicate"] = TRUE;
    _cdntaxreceipts_writeReceipt($f, $pdf_variables);
    $pdf_variables["mymargin_top"] = $mymargin_top + 90*2;
    $pdf_variables["is_duplicate"] = TRUE;
    _cdntaxreceipts_writeReceipt($f, $pdf_variables);
  }
}

function _cdntaxreceipts_writeReceipt(&$pdf, $pdf_variables) {

  // Extract variables
  $mode = $pdf_variables["mode"];
  $mymargin_left = $pdf_variables["mymargin_left"];
  $mymargin_top = $pdf_variables["mymargin_top"];
  $is_duplicate = $pdf_variables["is_duplicate"];
  $pdf_img_files_path = $pdf_variables["pdf_img_files_path"];
  $line_1 = $pdf_variables["line_1"];
  $source_funds = $pdf_variables["source_funds"];
  $amount = $pdf_variables["amount"];
  $display_date = $pdf_variables["display_date"];
  $issued_on = $pdf_variables["issued_on"];
  $receipt_number = $pdf_variables["receipt_number"];
  $displayname = $pdf_variables["displayname"];
  $address_line_1 = $pdf_variables["address_line_1"];
  $address_line_1b = $pdf_variables["address_line_1b"];
  $address_line_2 = $pdf_variables["address_line_2"];
  $address_line_3 = $pdf_variables["address_line_3"];
  $inkind_values = $pdf_variables["inkind_values"];
  $display_year = $pdf_variables["display_year"];
  $issue_type = $pdf_variables["issue_type"];
  $receipt_contributions = $pdf_variables['receipt_contributions'];
  $receipt_status = $pdf_variables['receipt_status'];

  // Middle center section
  if ($mode == CDNTAXRECEIPTS_MODE_PREVIEW) {
    $pdf->Image($pdf_img_files_path . 'preview_mode.png', $mymargin_left + 65, $mymargin_top, '', 45);
  }
  else if ($receipt_status == 'cancelled') {
    $pdf->Image($pdf_img_files_path . 'cancelled_trans.png', $mymargin_left + 65, $mymargin_top, '', 45);
  }
  else if ($is_duplicate) {
    $pdf->Image($pdf_img_files_path . 'duplicate_trans.png', $mymargin_left + 65, $mymargin_top, '', 45);
  }
  // Top left section
  $pdf_template_file = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_pdftemplate');
  if (!empty($pdf_template_file)) {
  }
  else {
    $pdf->Image(CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_logo', NULL, $pdf_img_files_path . 'your-logo.png'), $mymargin_left, $mymargin_top, '', 30);

  // Top right section
  $pdf->SetFont('Helvetica', '', 8);
  $pdf->SetY($mymargin_top);
  $pdf->Write(10, CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_name'), '', 0, 'R', TRUE, 0, FALSE, FALSE, 0);
  $pdf->SetY($mymargin_top + 4);
  $pdf->Write(10, CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_address_line1'), '', 0, 'R', TRUE, 0, FALSE, FALSE, 0);
  $pdf->SetY($mymargin_top + 8);
  $pdf->Write(10, CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_address_line2'), '', 0, 'R', TRUE, 0, FALSE, FALSE, 0);
  $pdf->SetY($mymargin_top + 12);

  $telTxt = ts('Tel: %1', array(1 => CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_tel'), 'domain' => 'org.civicrm.cdntaxreceipts'));
  if ( CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_fax' ) != '' ) {
    $telTxt .= '; ' . ts('Fax: %1', array(1 => CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_fax'), 'domain' => 'org.civicrm.cdntaxreceipts'));
  }
  $pdf->Write(10, $telTxt, '', 0, 'R', TRUE, 0, FALSE, FALSE, 0);
  $pdf->SetFont('Helvetica', 'I', 8);
  $pdf->SetY($mymargin_top + 16);

  $emailTxt = ts('Email: %1; Website: %2', array(
    1 => CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_email'),
    2 => CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_web'),
    'domain' => 'org.civicrm.cdntaxreceipts')
  );
  $registrationTxt = ts('Charitable Registration: %1', array(1 => CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_charitable_no'), 'domain' => 'org.civicrm.cdntaxreceipts'));

  $pdf->Write(10, $emailTxt, '', 0, 'R', TRUE, 0, FALSE, FALSE, 0);
  $pdf->SetY($mymargin_top + 20);
  $pdf->Write(10, $registrationTxt, '', 0, 'R', TRUE, 0, FALSE, FALSE, 0);
  }

  // Right section
  $x_detailscolumn = 120;
  $y_detailscolumnstart = 22;
  $pdf_template_file = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_pdftemplate');
  if (!empty($pdf_template_file)) {
  }
  else {
    $background_image = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_watermark');
    if ( $background_image ) $pdf->Image(CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_watermark'), $mymargin_left + $x_detailscolumn, $mymargin_top + $y_detailscolumnstart + 6, '', 40);
  }
  $pdf->SetXY($mymargin_left + $x_detailscolumn, $mymargin_top + $y_detailscolumnstart + 6);
  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->Write(10, ts("Issue Date: %1", array(1 => $issued_on, 'domain' => 'org.civicrm.cdntaxreceipts')));
  $pdf->SetXY($mymargin_left + $x_detailscolumn, $mymargin_top + $y_detailscolumnstart + 12);
  $pdf->Write(10, ts("Received on: %1", array(1 => $display_date, 'domain' => 'org.civicrm.cdntaxreceipts')));
  $pdf->SetXY($mymargin_left + $x_detailscolumn, $mymargin_top + $y_detailscolumnstart + 18);
  $pdf->Write(10, ts("Eligible Amount:  $%1", array(1 => number_format($amount, 2), 'domain' => 'org.civicrm.cdntaxreceipts')));
  $pdf->SetXY($mymargin_left + $x_detailscolumn, $mymargin_top + $y_detailscolumnstart + 34);

  if ($issue_type == 'annual') {
    $pdf->Write(10, ts("Thank you for giving in %1!", array( 1 => $display_year)));
    // $pdf->Write(10, "Contribution(s): ");
    // foreach ($receipt_contributions as $key => $value) {
    //   $test = $receipt_contributions[$key]['contribution_id'];
    //   $pdf->Write(10, $receipt_contributions[$key]['contribution_id'] . ' ');
    // }
  }
  else if (strlen($source_funds) < 36 && strlen($source_funds) > 0) {
    $pdf->Write(10, $source_funds);
  }
  else if (strlen($source_funds) > 0) {
    $source_funds_words = explode(" ", substr($source_funds, 0, 36));
    $source_funds_lastbit = array_pop($source_funds_words);
    $pdf->Write(10, implode(" ", $source_funds_words));
    $source_funds_count = count($source_funds_words);
    // $source_funds_nextline = array_splice(explode(" ", $source_funds), $source_funds_count);
    $exploded = explode(" ", $source_funds);
    $source_funds_nextline = array_splice($exploded, $source_funds_count);
    $pdf->SetXY($mymargin_left + $x_detailscolumn + 16, $mymargin_top + $y_detailscolumnstart + 38);
    $pdf->Write(10, implode(" ", $source_funds_nextline));
  }

  // Left section
  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->SetY($mymargin_top + $y_detailscolumnstart + 6);
  $pdf->Write(10, ts("Receipt No: %1", array(1 => $receipt_number, 'domain' => 'org.civicrm.cdntaxreceipts')));
  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->SetY($mymargin_top + $y_detailscolumnstart + 10);
  $pdf->Write(10, ts("Received from: ", array('domain' => 'org.civicrm.cdntaxreceipts')), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
  $pdf->SetFont('Helvetica', 'B', 10);
  $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 20);
  $pdf->Write(10, strtoupper($displayname), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);

  if (!$address_line_1b){
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 24);
    $pdf->Write(10, strtoupper($address_line_1), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 28);
    $pdf->Write(10, strtoupper($address_line_2), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 32);
    $pdf->Write(10, strtoupper($address_line_3), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
  }
  else {
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 24);
    $pdf->Write(10, strtoupper($address_line_1), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 28);
    $pdf->Write(10, strtoupper($address_line_1b), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 32);
    $pdf->Write(10, strtoupper($address_line_2), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->SetXY($mymargin_left+10, $mymargin_top + $y_detailscolumnstart + 36);
    $pdf->Write(10, strtoupper($address_line_3), '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
  }

  // If this is an in-kind donation
  if ( !empty($inkind_values) ) {

    $inkind_str = ts('Donation in-kind: %1', array(1 => $inkind_values[0], 'domain' => 'org.civicrm.cdntaxreceipts'));

    if (!empty($inkind_values[3])) {
      if ($inkind_values[3] < $amount) {
          $inkind_str .= ' - ' . ts('Cost: %1', array(1 => $inkind_values[3], 'domain' => 'org.civicrm.cdntaxreceipts'));
      }
    }

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetY($mymargin_top + 68);
    $pdf->Write(10, $inkind_str, ' - ', 0, 'L', TRUE, 0, FALSE, FALSE, 0);

    $appraiser = $inkind_values[1] . ' - '. $inkind_values[2];
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY($mymargin_left + $x_detailscolumn, $mymargin_top + $y_detailscolumnstart + 24);

    if (strlen($appraiser) < 36) {
      $pdf->Write(10, ts("Appraiser: %1", array(1 => $appraiser, 'domain' => 'org.civicrm.cdntaxreceipts')));
    }
    else {
      $appraiser_words = explode(" ", substr($appraiser, 0, 36));
      $appraiser_lastbit = array_pop($appraiser_words);
      $pdf->Write(10, ts("Appraiser: %1", array(1 => implode(" ", $appraiser_words), 'domain' => 'org.civicrm.cdntaxreceipts')));
      $appraiser_count = count($appraiser_words);
      $appraiser_nextline = array_splice(explode(" ", $appraiser), $appraiser_count);
      $pdf->SetXY($mymargin_left + $x_detailscolumn + 16, $mymargin_top + $y_detailscolumnstart + 28);
      $pdf->Write(10, implode(" ", $appraiser_nextline));
    }

  }

  // Bottom left section
  if (!empty($pdf_template_file)) {
  }
  else {
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetY($mymargin_top + 72);
    $pdf->Write(10, $line_1, '', 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetY($mymargin_top + 76, 0, 'L', TRUE, 0, FALSE, FALSE, 0);
    $pdf->Write(10, ts('Canadian Revenue Agency: www.cra-arc.gc.ca/charities', array('domain' => 'org.civicrm.cdntaxreceipts')));

    // Bottom center section
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY($mymargin_left + 92, $mymargin_top + 74);
    $pdf->Write(10, ts('Thank you!', array('domain' => 'org.civicrm.cdntaxreceipts')));

    // Bottom right section
    $signature = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_authorized_signature_text');
    if ( $signature == '' ) {
      $signature = ts('Authorized Signature', array('domain' => 'org.civicrm.cdntaxreceipts'));
    }
    $sig_offset = strlen($signature) - 20;
    $pdf_template_file = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_pdftemplate');

    $sig_image = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_signature');
    if (empty($sig_image)) {
      $sig_image = $pdf_img_files_path . 'authorized-signature.png';
    }
    $pdf->Image($sig_image, $mymargin_left + 137, $mymargin_top + 67, '', 15);
    $pdf->Line($mymargin_left + 136, $mymargin_top + 79, $mymargin_left + 186, $mymargin_top + 79);
    $pdf->SetXY($mymargin_left + 148 - $sig_offset, $mymargin_top + 76);
    $pdf->SetFont("Helvetica", "I", 7);
    $pdf->Write(10, $signature);
  }

  // Line at the bottom
  $pdf->Line($mymargin_left, $mymargin_top + 85, 198, $mymargin_top + 85, 'dash');
}

/**************************************
 * SECTION: Utility Functions
 */

/**
 * set up the custom field structure for In-Kind tax receipts
 */

function cdntaxreceipts_configure_inkind_fields() {

  // check if the In-kind contribtion type exists.  if not, create it.
  $dao = _cdntaxreceipts_get_contribution_dao();
  $dao->name = 'In-kind';

  if ( ! $dao->find(true) ) {
    $dao->name = 'In-kind';
    $dao->is_deductible = TRUE;
    $dao->is_active = TRUE;
    $dao->save();
  }

  $contrib_type_id = $dao->id;

  // check if the custom group exists.  if not, create it.
  $params = array(
    'title' => 'In-kind donation fields',
    'version' => 3,
  );

  require_once 'api/api.php';
  $result = civicrm_api( 'custom_group', 'get', $params );

  if ( $result['count'] == 0 ) {
    $group = array(
      'title' => 'In-kind donation fields',
      'extends' => array( 'Contribution' ),
      'extends_entity_column_value' => array( $contrib_type_id ),
      'collapse_display' => 0,
      'style' => 'Inline',
      'is_active' => 1,
      'version' => 3
    );
    $result =& civicrm_api('custom_group', 'create', $group);
  }
  foreach ( $result['values'] as $id => $detail ) {
    $custom_group_id = $id;
  }

  // check if the custom fields exist.  if not, create them.
  foreach ( array( 'Description of property', 'Appraised by', 'Address of Appraiser', 'Original cost' ) as $field ) {
    $params = array(
      'custom_group_id' => $custom_group_id,
      'label' => $field,
      'version' => 3,
    );
    $result = civicrm_api( 'custom_field', 'get', $params);

    if ( $result['count'] == 0 ) {
      $field_params = array(
        'custom_group_id' => $custom_group_id,
        'label' => $field,
        'data_type' => 'String',
        'is_required' => 1,
        'is_active' => 1,
        'version' => 3,
      );
      if ( $field == 'Original cost' ) {
        $field_params['is_required'] = 0;
        $field_params['help_post'] = 'Amount originally paid for the item. This item is needed for a special rule'.
          ' that applies if either something was purchased in the last ten years'.
          ' with the intent of making a donation, or if an item is donated within three years'.
          ' of being purchased. In both cases the amount that can be claimed is the lesser'.
          ' of the market value, and the amount paid for the item. This item is used to show'.
          ' the cost of the item donated (and the creditable amount) if either of those '.
          ' situations apply.';
      }
      $result =& civicrm_api('custom_field', 'create', $field_params);
    }
  }
}

/**************************************
 * SECTION: Tax Receipt API
 */

/**
 * issueTaxReceipt()
 * Issues a tax receipt for a single contribution.
 * Detects if the receipt is a duplicate and reacts appropriately.
 */
function cdntaxreceipts_issueTaxReceipt($contribution, &$collectedPdf = NULL, $mode = CDNTAXRECEIPTS_MODE_BACKOFFICE) {

  // This function basically needs to set up a $receipt[] array and pass it to
  // processTaxReceipt() which does the hard work
  $receipt = array();

  // check if a Tax Receipt has already been issued previously for this Contribution
  // user input could be tainted! we want this method to check everything for validity...
  list($issued_on, $receipt_id) = cdntaxreceipts_issued_on($contribution->id);

  // When returning issued_on field:
  //   returns false for non existing $contribution ID
  //   returns 0 for existing but old data
  //   returns timestamp for new data
  $is_duplicate = empty($issued_on) ? 0 : 1;


  if ( $is_duplicate ) {
    // if this was issued as part of an annual receipt, then jump over to the Annual method
    $receipt = cdntaxreceipts_load_receipt($receipt_id);
    if ( $receipt['issue_type'] == 'annual' ) {
      $contactId = $receipt['contact_id'];
      $year = substr($receipt['contributions'][0]['receive_date'], 0, 4);
      return cdntaxreceipts_issueAnnualTaxReceipt($contactId, $year, $collectedPdf, $mode);
    }
    else if ( $receipt['issue_type'] == 'aggregate' ) {
      $contactId = $receipt['contact_id'];
      $year = substr($receipt['contributions'][0]['receive_date'], 0, 4);
      list($method, $email) = cdntaxreceipts_sendMethodForContact($contactId);
      return cdntaxreceipts_issueAggregateTaxReceipt($contactId, $year, $receipt['contributions'],
        $method, $collectedPdf, $mode);
    }
  }

  // determine the send method
  list($method, $email) = cdntaxreceipts_sendMethodForContact($contribution->contact_id);

  // Cancelled receipts are not sent via email
  if ($is_duplicate && $receipt['receipt_status'] == 'cancelled') {
    $method = 'print';
  }

  // "Attach" receipts are print (we save the file & attach to an email)
  if ($mode == CDNTAXRECEIPTS_MODE_WORKFLOW) {
    $method = 'email';
  }

  // generate a receipt number
  $receipt_no = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_prefix') . str_pad($contribution->id, 8, 0, STR_PAD_LEFT);

  // process In-Kind variables if this is a receipt for an in-kind contribution

  $contributiontype =  _cdntaxreceipts_get_type_for_contribution($contribution);

  $inkind_values = array();
  // check if this is an 'In-kind" contribution.
  if ( $contributiontype->name == 'In-kind' ) {
    // in this case get the custom field values:
    require_once 'CRM/Core/BAO/CustomField.php';
    $groupTitle = 'In-kind donation fields';
    $fieldLabel_description = 'Description of property';
    $customFieldID_description = CRM_Core_BAO_CustomField::getCustomFieldID( $fieldLabel_description, $groupTitle );
    $fieldLabel_appraisedby = 'Appraised by';
    $customFieldID_appraisedby = CRM_Core_BAO_CustomField::getCustomFieldID( $fieldLabel_appraisedby, $groupTitle );
    $fieldLabel_appraiseraddress = 'Address of Appraiser';
    $customFieldID_appraiseraddress = CRM_Core_BAO_CustomField::getCustomFieldID( $fieldLabel_appraiseraddress, $groupTitle );
    $fieldLabel_cost = 'Original cost';
    $customFieldID_cost = CRM_Core_BAO_CustomField::getCustomFieldID( $fieldLabel_cost, $groupTitle );

    require_once 'CRM/Core/BAO/CustomValueTable.php';

    $custom_id = 'custom_' . $customFieldID_description;
    $params = array('entityID' => $contribution->id, $custom_id => 1);
    $values = CRM_Core_BAO_CustomValueTable::getValues($params);
    $inkind_values[] = $values[$custom_id];

    $custom_id = 'custom_' . $customFieldID_appraisedby;
    $params = array('entityID' => $contribution->id, $custom_id => 1);
    $values = CRM_Core_BAO_CustomValueTable::getValues($params);
    $inkind_values[] = $values[$custom_id];

    $custom_id = 'custom_' . $customFieldID_appraiseraddress;
    $params = array('entityID' => $contribution->id, $custom_id => 1);
    $values = CRM_Core_BAO_CustomValueTable::getValues($params);
    $inkind_values[] = $values[$custom_id];

    $custom_id = 'custom_' . $customFieldID_cost;
    $params = array('entityID' => $contribution->id, $custom_id => 1);
    $values = CRM_Core_BAO_CustomValueTable::getValues($params);
    $inkind_values[] = $values[$custom_id];
  }

  $receipt = array(
    'receipt_no' => $receipt_no,
    'issued_on' =>  (int) $_SERVER['REQUEST_TIME'],
    'contact_id' => $contribution->contact_id,
    'receipt_amount' => cdntaxreceipts_eligibleAmount($contribution->id),
    'is_duplicate' => $is_duplicate,
    'issue_type' => 'single',
    'issue_method' => $method,
    'receive_date' => $contribution->receive_date,
    'inkind_values' => $inkind_values,
    'receipt_status' => ($is_duplicate && isset($receipt)) ? $receipt['receipt_status'] : 'issued',
    'email_tracking_id' => md5(uniqid(rand(), TRUE)),
  );

  if ( $contribution->source != '' ) {
    $receipt['source'] = 'Source: ' . $contribution->source;
  }

  $receipt['contributions'] = array(
    array(
      'contribution_id' => $contribution->id,
      'contribution_amount' => $contribution->total_amount,
      'receipt_amount' => $receipt['receipt_amount'],
      'receive_date' => $contribution->receive_date,
    )
  );

  return cdntaxreceipts_processTaxReceipt($receipt, $collectedPdf, $mode);
}

function cdntaxreceipts_eligibleForReceipt( $contributionId ) {

  // This function may be called multiple times throughout a request lifecycle, for the
  // same contribution. This can be resource intensive, since it must load the Contribution
  // object each time and will invoke any custom hooks, which may themselves hit the DB. This
  // adds up quickly, particularly during bulk runs. The basic eligibility of a
  // contribution will not change through the request lifecycle, so we can cache those we
  // have already computed.
  static $cache = array();
  if ( array_key_exists($contributionId, $cache) ) {
    return $cache[$contributionId];
  }
  
  require_once('CRM/Contribute/DAO/Contribution.php');
  $contribution =  new CRM_Contribute_DAO_Contribution();
  $contribution->id = $contributionId;

  if ( ! $contribution->find( TRUE ) ) {
    CRM_Core_Error::fatal( "CDNTaxReceipts: Could not retrieve details for this contribution" );
  }

  // 1. check that contribution amount is deductible
  $deductibleAmount = cdntaxreceipts_eligibleAmount($contributionId);;

  // 2. we used to check here the financial type -- to see if it is deductible.
  // Since the introduction of split transactions (4.5 is the cleanest starting point, though they existed prior),
  // we no longer check the financial type of the contribution, and check instead the tax eligibility of each
  // line item. This is done in the eligibleAmount() function.

  // 3. check if the Contribution Status is Completed ( i.e. = 1 )
  $contributionStatus = $contribution->contribution_status_id;
  if ( !isset($contributionStatus) ) {
    CRM_Core_Error::fatal( "CDNTaxReceipts: Could not find status of this Contribution." );
  }

  // invoke hook_cdntaxreceipts_eligible:
  //   module implementations should return an array containing a single item,
  //   either TRUE or FALSE. assumes TRUE until we receive a FALSE. one FALSE
  //   disqualifies the contribution for a tax receipt.
  if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
    $results = CRM_Utils_Hook::singleton()->invoke(1, $contribution, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_eligible');
  } else {
    $results = CRM_Utils_Hook::singleton()->invoke(1, $contribution, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_eligible');
  }
  $hookOk = TRUE;

  if (is_array($results)) {
    foreach ( $results as $result ) {
      if ( $result == FALSE ) {
        $hookOk = FALSE;
      }
    }
  }

  if ( $contributionStatus == '1' && $contribution->is_test == 0 && $deductibleAmount > 0 && $hookOk ) {
    $cache[$contributionId] = TRUE;
  }
  else {
    $cache[$contributionId] = FALSE;
  }

  return $cache[$contributionId];

}

function cdntaxreceipts_eligibleAmount( $contributionId ) {

  // This function may be called multiple times throughout a request lifecycle, for the
  // same contribution. This can be resource intensive, since it must load the Contribution
  // object each time and will invoke any custom hooks, which may themselves hit the DB. This
  // adds up quickly, particularly during bulk runs. The basic eligibility amount of a
  // contribution will not change through the request lifecycle, so we can cache those we
  // have already computed.
  static $cache = array();
  if ( array_key_exists($contributionId, $cache) ) {
    return $cache[$contributionId];
  }
  
  require_once('CRM/Contribute/DAO/Contribution.php');
  $contribution =  new CRM_Contribute_DAO_Contribution();
  $contribution->id = $contributionId;

  if ( ! $contribution->find( TRUE ) ) {
    CRM_Core_Error::fatal( "CDNTaxReceipts: Could not retrieve details for this contribution" );
  }

  // 1. calculate deductible amount
  $deductibleAmount = 0;
  if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
    // compute for overall contribution in 4.4
    $financialType =  _cdntaxreceipts_get_type_for_contribution($contribution);
    if ( $financialType->is_deductible ) {
      $deductibleAmount = $contribution->total_amount;
    }
  }
  else {
    // compute by line item in 4.5+ if there are line items
    // (if the contribution was done pre-upgrade, it might not have line items)
    $line_item = new CRM_Price_DAO_LineItem();
    $line_item->contribution_id = $contributionId;
    $count = $line_item->find();

    if ($count > 0) {
      while ( $line_item->fetch() ) {
        $financialType =  _cdntaxreceipts_get_type_for_line_item($line_item);
        if ( $financialType->is_deductible ) {
          $deductibleAmount += $line_item->line_total;
        }
      }
    }
    else {
      // compute for overall contribution
      $financialType =  _cdntaxreceipts_get_type_for_contribution($contribution);
      if ( $financialType->is_deductible ) {
        $deductibleAmount = $contribution->total_amount;
      }
    } 
  }

  // account for manually-entered non_deductible_amount
  $deductibleAmount = $deductibleAmount - $contribution->non_deductible_amount;
  if ( $deductibleAmount < 0 ) {
    $deductibleAmount = 0;
  }
 
  // 2. allow modules to alter the amount. lowest amount wins.
   if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
    $results = CRM_Utils_Hook::singleton()->invoke(1, $contribution, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_eligibleAmount');
  } else {
    $results = CRM_Utils_Hook::singleton()->invoke(1, $contribution, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_eligibleAmount');
  }

  if (is_array($results)) {
    foreach ( $results as $result ) {
      if ( $result < $deductibleAmount ) {
        $deductibleAmount = $result;
      }
    }
  }

  $cache[$contributionId] = $deductibleAmount;
  return $deductibleAmount;
}

/**
 * issueAnnualTaxReceipt()
 * Issues an annual tax receipt for a given year.
 * Detects if the receipt is a duplicate and reacts appropriately.
 */
function cdntaxreceipts_issueAnnualTaxReceipt( $contactId, $year, &$collectedPdf = NULL, $mode = CDNTAXRECEIPTS_MODE_BACKOFFICE ) {

  // determine the send method
  list( $method, $email ) = cdntaxreceipts_sendMethodForContact($contactId);

  // query for duplicate receipt
  list( $issued_on, $receipt_id ) = cdntaxreceipts_annual_issued_on($contactId, $year);

  // When returning issued_on field:
  //   returns false for non existing $contribution ID
  //   returns 0 for existing but old data
  //   returns timestamp for new data
  $is_duplicate = empty($issued_on) ? 0 : 1;

  if ( $is_duplicate ) {
    // issue an identical receipt
    $receipt = cdntaxreceipts_load_receipt($receipt_id);
    // Cancelled receipts are not sent via email
    if ($receipt['receipt_status'] == 'cancelled') {
      $method = 'print';
    }
    $receipt['issued_on'] = (int) $_SERVER['REQUEST_TIME'];
    $receipt['is_duplicate'] = 1;
    $receipt['receive_date'] = $year;
    $receipt['issue_method'] = $method;
  }
  else {

    $contributions = cdntaxreceipts_contributions_not_receipted($contactId, $year);

    if ( count($contributions) == 0 ) {
      return array(FALSE, $method, NULL);
    }

    // calculate total amount
    $receiptContributions = array();
    $totalReceipt = 0;
    foreach ( $contributions as $c ) {
      $eligibleAmount = cdntaxreceipts_eligibleAmount($c['contribution_id']);
      $receiptContributions[] = array(
        'contribution_id' => $c['contribution_id'],
        'contribution_amount' => $c['total_amount'],
        'receipt_amount' => $eligibleAmount,
        'receive_date' => $c['receive_date'],
      );
      $totalReceipt += $eligibleAmount;
    }

    // generate a receipt number
    $receiptNo = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_prefix') . str_pad($receiptContributions[0]['contribution_id'], 8, 0, STR_PAD_LEFT);

    $receipt = array(
      'receipt_no' => $receiptNo,
      'issued_on' =>  (int) $_SERVER['REQUEST_TIME'],
      'contact_id' => $contactId,
      'receipt_amount' => $totalReceipt,
      'is_duplicate' => 0,
      'issue_type' => 'annual',
      'issue_method' => $method,
      'receive_date' => $year,
      'receipt_status' => 'issued',
      'contributions' => $receiptContributions,
      'email_tracking_id' => md5(uniqid(rand(), TRUE)),
    );

  }

  return cdntaxreceipts_processTaxReceipt($receipt, $collectedPdf, $mode);
}

/**
 * cdntaxreceipts_issueAggregateTaxReceipt()
 * Issues an grouped tax receipt for a given contact, year and list of selected contributions.
 * Currently does not issue duplicates. Pass only original contribution ids.
 *
 * @param $contactId
 * @param $year
 * @param $contributions
 * @param $method
 * @param null $collectedPdf
 * @param bool $previewMode
 * @return array
 */
function cdntaxreceipts_issueAggregateTaxReceipt($contactId, $year, $contributions, $method, &$collectedPdf = NULL,
                                                 $mode = CDNTAXRECEIPTS_MODE_BACKOFFICE) {
  if ( count($contributions) == 0 ) {
    return array(FALSE, $method, NULL);
  }

  /** Two different formats come into this function in the $contributions array
   * 1. When issuing for the first time:
   * array ($contribution_id => array(
   *   'contribution_id' => $id,
   *   'contact_id' => $dao->contact_id,
   *   'total_amount' => $dao->total_amount,
   *   'non_deductible_amount' => $dao->non_deductible_amount,
   *   'receive_date' => $dao->receive_date,
   *   'receive_year' => $dao->receive_year,
   *   'eligible' => $eligible,
   *   'receipt_id' => $dao->receipt_id,
   * ),);
   * 2. When re-issuing:
   *
   *  $index (not contrib id) => array(
   *    'contribution_id'
   *    'contribution_amount'
   *    'receipt_amount'
   *    'receive_date'
   * );
   **/

  $contrib_copy = $contributions;
  $check_contribution = array_shift($contrib_copy);
  list($issued_on, $receipt_id) = cdntaxreceipts_issued_on($check_contribution['contribution_id']);

  if (isset($check_contribution['receipt_id']) && $check_contribution['receipt_id'] != 0) {
    $is_duplicate = TRUE;
  }
  else {
    $is_duplicate = empty($issued_on) ? 0 : 1;
  }

  if ( $is_duplicate ) {
    $receipt = cdntaxreceipts_load_receipt($receipt_id);
    // Cancelled receipts are not sent via email
    if ($receipt['receipt_status'] == 'cancelled') {
      $method = 'print';
    }
    $receipt['issued_on'] = (int) $_SERVER['REQUEST_TIME'];
    $receipt['is_duplicate'] = 1;
    $receipt['issue_method'] = $method;
    $receipt['receive_date'] = $year;
  }
  else {
    // calculate total amount
    $receiptContributions = array();
    $totalReceipt = 0;
    foreach ( $contributions as $c ) {
      $eligibleAmount =  cdntaxreceipts_eligibleAmount($c['contribution_id']);
      $receiptContributions[] = array(
        'contribution_id' => $c['contribution_id'],
        'contribution_amount' => $c['total_amount'],
        'receipt_amount' => $eligibleAmount,
        'receive_date' => $c['receive_date'],
      );
      $totalReceipt += $eligibleAmount;
    }

    // generate a receipt number
    $receiptNo = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'receipt_prefix') . str_pad($receiptContributions[0]['contribution_id'], 8, 0, STR_PAD_LEFT);

    $receipt = array(
      'receipt_no' => $receiptNo,
      'issued_on' =>  (int) $_SERVER['REQUEST_TIME'],
      'contact_id' => $contactId,
      'receipt_amount' => $totalReceipt,
      'is_duplicate' => 0,
      'issue_type' => 'aggregate',
      'issue_method' => $method,
      'receive_date' => $year,
      'receipt_status' => 'issued',
      'contributions' => $receiptContributions,
      'email_tracking_id' => md5(uniqid(rand(), TRUE)),
    );
  }

  return cdntaxreceipts_processTaxReceipt($receipt, $collectedPdf, $mode);
}


/* determines the send method for the specified contact
 * returns array, [0] = 'email' or 'print', [1] = email address or NULL
 */
function cdntaxreceipts_sendMethodForContact( $contactId ) {

  $global_email = CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'enable_email', NULL, TRUE);
  if ( ! $global_email ) {
    return array('print', NULL);
  }

  $method = array('print', NULL);
  
  require_once 'CRM/Contact/BAO/Contact.php';
  list($displayname, $email, $doNotEmail, $onHold) = CRM_Contact_BAO_Contact::getContactDetails($contactId);

  if ( isset($email) ) {
    if ( ! $doNotEmail && ! $onHold ) {
      $method = array('email', $email);
    }
  }
  
  // invoke hook_cdntaxreceipts_method:
  //   module implementations should return an array containing a single item,
  //   either 'email' or 'print'. if we receive one or more 'print', the contact
  //   is disqualified from receiving email receipts. 
  if (version_compare(CRM_Utils_System::version(), '4.5', '<')) {
    $results = CRM_Utils_Hook::singleton()->invoke(1, $contactId, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_method');
  } else {
    $results = CRM_Utils_Hook::singleton()->invoke(1, $contactId, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, 'cdntaxreceipts_method');
  }
  $emailOk = TRUE;

  if (is_array($results)) {
    foreach ( $results as $result ) {
      if ( $result == 'print' ) {
        $method = array('print', NULL);
      }
    }
  }

  return $method;

}

function cdntaxreceipts_sendMethodForContribution( $contributionId ) {

  $contribution =  new CRM_Contribute_DAO_Contribution();
  $contribution->id = $contributionId;

  if ( ! $contribution->find( TRUE ) ) {
    CRM_Core_Error::fatal( "CDNTaxReceipts: Could not retrieve details for this contribution" );
  }

  return cdntaxreceipts_sendMethodForContact( $contribution->contact_id );
}

function cdntaxreceipts_openCollectedPDF() {

  static $pdf;

  if ( ! isset($pdf) ) {
    //define ('K_PATH_IMAGES', '');
    require_once 'tcpdf/tcpdf.php';

    $pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', TRUE, 'UTF-8', FALSE);
    $pdf->Open();

    $pdf->SetAuthor(CRM_Core_BAO_Setting::getItem(CDNTAX_SETTINGS, 'org_name'));

    $mymargin_left = 12;
    $mymargin_top = 6;
    $mymargin_right = 12;
    $pdf->SetMargins($mymargin_left, $mymargin_top, $mymargin_right);

    $pdf->setJPEGQuality('100');

    $pdf->SetAutoPageBreak('', $margin=0);
  }

  return $pdf;

}

function cdntaxreceipts_sendCollectedPDF(&$pdf, $filename) {
  if ( $pdf->getNumPages() > 0 ) {
    $pdf->Output($filename, 'D');
    CRM_Utils_System::civiExit();
  }
  else {
    $pdf->Close();
  }
}

/**
 * Isolate version checking code
 * @param $contribution
 * @return CRM_Contribute_DAO_ContributionType|CRM_Financial_DAO_FinancialType
 */
function _cdntaxreceipts_get_type_for_contribution($contribution) {

  if (version_compare(CRM_Utils_System::version(), '4.3', '>=')) {
    require_once 'CRM/Financial/DAO/FinancialType.php';
    $contributionType = new CRM_Financial_DAO_FinancialType();
    $contributionType->id = $contribution->financial_type_id;
    if ( ! $contributionType->find( TRUE ) ) {
      CRM_Core_Error::fatal( "CDNTaxReceipts: Could not find corresponding contribution type." );
    }
  }
  else { // Assume 4.2 or lower
    require_once 'CRM/Contribute/DAO/ContributionType.php';
    $contributionType = new CRM_Contribute_DAO_ContributionType();
    $contributionType->id = $contribution->contribution_type_id;
    if ( ! $contributionType->find( TRUE ) ) {
      CRM_Core_Error::fatal( "CDNTaxReceipts: Could not find corresponding contribution type." );
    }
  }
  return $contributionType;
}

function _cdntaxreceipts_get_contribution_dao() {
  // Require once lines are only needed if the civicrm.settings.php does not have the autoloader line
  require_once 'CRM/Utils/VersionCheck.php';
  $majorVersion = CRM_Utils_System::version();
  if (version_compare('4.3alpha1', $majorVersion) < 1) {
    require_once 'CRM/Financial/DAO/FinancialType.php';
    $contributionType = new CRM_Financial_DAO_FinancialType();
  }
  else { // Assume 4.2 or lower
    require_once 'CRM/Contribute/DAO/ContributionType.php';
    $contributionType = new CRM_Contribute_DAO_ContributionType();
  }
  return $contributionType;
}

function _cdntaxreceipts_get_type_for_line_item($line_item) {
  require_once 'CRM/Financial/DAO/FinancialType.php';
  $financialType = new CRM_Financial_DAO_FinancialType();
  $financialType->id = $line_item->financial_type_id;
  if ( ! $financialType->find( TRUE ) ) {
    CRM_Core_Error::fatal( "CDNTaxReceipts: Could not find corresponding contribution type." );
  }
  return $financialType;
}

/**
 * Get the correct contact address. Get the billing address followed by the is_primary as a fallback.
 * @param $contact_id
 * @return array|mixed|null address : Contact address fixed with named country and state_province
 */
function cdntaxreceipts_getAddress($contact_id) {

  $address = NULL;
  // get Address information via contact
  $params = array(
    'version' => 3,
    'contact_id' => $contact_id,
    'is_billing' => 1,
  );


  $address_results = civicrm_api('Address', 'get', $params);

  if ( $address_results['is_error'] == 0) {
    $address = array_shift($address_results['values']);
  }

  if (!isset($address)) {
    $params = array(
      'version' => 3,
      'contact_id' => $contact_id,
      'is_primary' => 1,
    );
    $address_results = civicrm_api('Address', 'get', $params);

    if ( $address_results['is_error'] == 0) {
      $address = array_shift($address_results['values']);
    }

  }
  $address = isset($address) ? $address : array();
  // add actual names for province and country instead of just having ids
  require_once 'CRM/Core/BAO/Address.php';
  CRM_Core_BAO_Address::fixAddress($address);

  return $address;
}

/**
 * Returns a setting of CiviCRM.
 *
 * @param string $key the setting to retrieve.
 * @return string the value of the $key setting.
 */
function cdntaxreceipts_getCiviSetting($key) {
  $result = civicrm_api3('Setting', 'get', array(
    'sequential' => 1,
    'return' => array($key),
  ));
  $settings = CRM_Utils_Array::first($result['values']);
  return isset($settings[$key]) ? $settings[$key] : NULL;
}

/**
 * Sets the value of a CiviCRM setting.
 *
 * @param string $key the setting to set.
 * @paramm string $value the value for the setting.
 */
function cdntaxreceipts_setCiviSetting($key, $value) {
  civicrm_api3('Setting', 'create', array(
    'sequential' => 1,
    $key => $value,
  ));
}

/**
 * @param $issue_type
 * @return string display formatted Issue type
 */
function _cdntaxreceipts_get_display_type($issue_type) {
  switch ($issue_type) {
    case 'aggregate' :
      return 'Aggregate';
    case 'annual' :
      return 'Annual';
    case 'single' :
      return 'Single';
    default:
      return '';
  }
}

