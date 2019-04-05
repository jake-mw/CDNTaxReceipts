<?php

require_once 'CRM/Core/Form.php';
require_once 'cdntaxreceipts.functions.inc';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Cdntaxreceipts_Form_Settings extends CRM_Core_Form {

  CONST SETTINGS = 'CDNTaxReceipts';

  function buildQuickForm() {

    CRM_Utils_System::setTitle(ts('Configure CDN Tax Receipts', array('domain' => 'org.civicrm.cdntaxreceipts')));

    $this->processOrgOptions('build');
    $this->processReceiptOptions('build');
    $this->processSystemOptions('build');
    $this->processEmailOptions('build');

    $arr1 = $this->processOrgOptions('defaults');
    $arr2 = $this->processReceiptOptions('defaults');
    $arr3 = $this->processSystemOptions('defaults');
    $arr4 = $this->processEmailOptions('defaults');
    $defaults = array_merge($arr1, $arr2, $arr3, $arr4);
    $this->setDefaults($defaults);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit', array('domain' => 'org.civicrm.cdntaxreceipts')),
        'isDefault' => true,
      ),
    ));
    // Set image defaults
    $images = array('receipt_logo', 'receipt_signature', 'receipt_watermark', 'receipt_pdftemplate');
    foreach ($images as $image) {
      if (CRM_Utils_Array::value($image, $defaults)) {
        $this->assign($image, $defaults[$image]);
        if (!file_exists($defaults[$image])) {
          $this->assign($image.'_class', true);
        }
      }
    }

    parent::buildQuickForm();
  }

  function processOrgOptions($mode) {
    if ( $mode == 'build' ) {
      $this->add('text', 'org_name', ts('Organization Name', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_address_line1', ts('Address Line 1', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_address_line2', ts('Address Line 2', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_tel', ts('Telephone', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_fax', ts('Fax', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_email', ts('Email', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_web', ts('Website', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'org_charitable_no', ts('Charitable Registration Number', array('domain' => 'org.civicrm.cdntaxreceipts')));

      $this->addRule('org_name', 'Enter Organization Name', 'required');
      $this->addRule('org_address_line1', 'Enter Address Line 1', 'required');
      $this->addRule('org_address_line2', 'Enter Address Line 2', 'required');
      $this->addRule('org_tel', 'Enter Telephone', 'required');
      $this->addRule('org_email', 'Enter Email', 'required');
      $this->addRule('org_web', 'Enter Website', 'required');
      $this->addRule('org_charitable_no', 'Enter Charitable Number', 'required');
    }
    else if ( $mode == 'defaults' ) {
      $defaults = array(
        'org_name' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_name'),
        'org_address_line1' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_address_line1'),
        'org_address_line2' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_address_line2'),
        'org_tel' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_tel'),
        'org_fax' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_fax'),
        'org_email' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_email'),
        'org_web' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_web'),
        'receipt_logo' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_logo'),
        'receipt_signature' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_signature'),
        'receipt_watermark' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_watermark'),
        'receipt_pdftemplate' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_pdftemplate'),
        'org_charitable_no' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'org_charitable_no'),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      CRM_Core_BAO_Setting::setItem($values['org_name'], self::SETTINGS, 'org_name');
      CRM_Core_BAO_Setting::setItem($values['org_address_line1'], self::SETTINGS, 'org_address_line1');
      CRM_Core_BAO_Setting::setItem($values['org_address_line2'], self::SETTINGS, 'org_address_line2');
      CRM_Core_BAO_Setting::setItem($values['org_tel'], self::SETTINGS, 'org_tel');
      CRM_Core_BAO_Setting::setItem($values['org_fax'], self::SETTINGS, 'org_fax');
      CRM_Core_BAO_Setting::setItem($values['org_email'], self::SETTINGS, 'org_email');
      CRM_Core_BAO_Setting::setItem($values['org_web'], self::SETTINGS, 'org_web');
      CRM_Core_BAO_Setting::setItem($values['org_charitable_no'], self::SETTINGS, 'org_charitable_no');
    }

  }

  function processReceiptOptions($mode) {
    if ( $mode == 'build' ) {
      $this->add('text', 'receipt_prefix', ts('Receipt Prefix', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('checkbox', 'receipt_serial', ts('Serial Receipt Numbers', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'receipt_authorized_signature_text', ts('Authorized Signature Text', array('domain' => 'org.civicrm.cdntaxreceipts')));

      $uploadSize = cdntaxreceipts_getCiviSetting('maxFileSize');
      if ($uploadSize >= 8 ) {
        $uploadSize = 8;
      }
      $uploadFileSize = $uploadSize * 1024 * 1024;

      $this->assign('uploadSize', $uploadSize );
      $this->setMaxFileSize( $uploadFileSize );

      $this->addElement('file', 'receipt_logo', ts('Organization Logo', array('domain' => 'org.civicrm.cdntaxreceipts')), 'size=30 maxlength=60');
      $this->addUploadElement('receipt_logo');
      $this->addRule( 'receipt_logo', ts('File size should be less than %1 MBytes (%2 bytes)', array(1 => $uploadSize, 2 => $uploadFileSize)), 'maxfilesize', $uploadFileSize, array('domain' => 'org.civicrm.cdntaxreceipts') );

      $this->addElement('file', 'receipt_signature', ts('Signature Image', array('domain' => 'org.civicrm.cdntaxreceipts')), 'size=30 maxlength=60');
      $this->addUploadElement('receipt_signature');
      $this->addRule( 'receipt_signature', ts('File size should be less than %1 MBytes (%2 bytes)', array(1 => $uploadSize, 2 => $uploadFileSize)), 'maxfilesize', $uploadFileSize, array('domain' => 'org.civicrm.cdntaxreceipts') );

      $this->addElement('file', 'receipt_watermark', ts('Watermark Image', array('domain' => 'org.civicrm.cdntaxreceipts')), 'size=30 maxlength=60');
      $this->addUploadElement('receipt_watermark');
      $this->addRule( 'receipt_watermark', ts('File size should be less than %1 MBytes (%2 bytes)', array(1 => $uploadSize, 2 => $uploadFileSize)), 'maxfilesize', $uploadFileSize, array('domain' => 'org.civicrm.cdntaxreceipts') );

      $this->addElement('file', 'receipt_pdftemplate', ts('PDF Template', array('domain' => 'org.civicrm.cdntaxreceipts')), 'size=30 maxlength=60');
      $this->addUploadElement('receipt_pdftemplate');
      $this->addRule( 'receipt_pdftemplate', ts('File size should be less than %1 MBytes (%2 bytes)', array(1 => $uploadSize, 2 => $uploadFileSize)), 'maxfilesize', $uploadFileSize, array('domain' => 'org.civicrm.cdntaxreceipts') );
    }
    else if ( $mode == 'defaults' ) {
      $defaults = array(
        'receipt_prefix' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_prefix'),
        'receipt_serial' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_serial'),
        'receipt_authorized_signature_text' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'receipt_authorized_signature_text'),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      CRM_Core_BAO_Setting::setItem($values['receipt_prefix'], self::SETTINGS, 'receipt_prefix');
      CRM_Core_BAO_Setting::setItem('1', self::SETTINGS, 'receipt_serial');
      CRM_Core_BAO_Setting::setItem($values['receipt_authorized_signature_text'], self::SETTINGS, 'receipt_authorized_signature_text');

      $receipt_logo = $this->getSubmitValue('receipt_logo');
      $receipt_signature = $this->getSubmitValue('receipt_signature');
      $receipt_watermark = $this->getSubmitValue('receipt_watermark');
      $receipt_pdftemplate = $this->getSubmitValue('receipt_pdftemplate');

      $config = CRM_Core_Config::singleton( );
      foreach ( array('receipt_logo', 'receipt_signature', 'receipt_watermark', 'receipt_pdftemplate') as $key ) {
        $upload_file = $this->getSubmitValue($key);
        if (is_array($upload_file)) {
          if ( $upload_file['error'] == 0 ) {
            $filename = $config->customFileUploadDir . CRM_Utils_File::makeFileName($upload_file['name']);
            if (!move_uploaded_file($upload_file['tmp_name'], $filename)) {
              CRM_Core_Error::fatal(ts('Could not upload the file'));
            }
            CRM_Core_BAO_Setting::setItem($filename, self::SETTINGS, $key);
          }
        }
      }
    }
  }

  function processSystemOptions($mode) {
    if ( $mode == 'build' ) {
      $this->addElement('checkbox', 'issue_inkind', ts('Setup in-kind receipts?', array('domain' => 'org.civicrm.cdntaxreceipts')));

      $delivery_options = array();
      $delivery_options[] = $this->createElement('radio', null, null, 'Print only', CDNTAX_DELIVERY_PRINT_ONLY);
      $delivery_options[] = $this->createElement('radio', null, null, 'Email or print', CDNTAX_DELIVERY_PRINT_EMAIL);
      $delivery_options[] = $this->createElement('radio', null, null, 'Data only', CDNTAX_DELIVERY_DATA_ONLY);
      $this->addGroup($delivery_options, 'delivery_method', ts('Delivery Method', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->addRule('delivery_method', 'Delivery Method', 'required');

      $yesno_options = array();
      $yesno_options[] = $this->createElement('radio', null, null, 'Yes', 1);
      $yesno_options[] = $this->createElement('radio', null, null, 'No', 0);
      $this->addGroup($yesno_options, 'attach_to_workflows', ts('Attach receipts to automated workflow messages?', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->addRule('attach_to_workflows', 'Attach tax receipts to automated messages', 'required');

      $yesno_options2 = array();
      $yesno_options2[] = $this->createElement('radio', null, null, 'Yes', 1);
      $yesno_options2[] = $this->createElement('radio', null, null, 'No', 0);
      $this->addGroup($yesno_options2, 'enable_advanced_eligibility_report', ts('Enable Advanced Eligibility Check?', array('domain' => 'org.civicrm.cdntaxreceipts')));
    }
    else if ( $mode == 'defaults' ) {
      $defaults = array(
        'issue_inkind' => 0,
        'delivery_method' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'delivery_method', null, CDNTAX_DELIVERY_PRINT_ONLY),
        'attach_to_workflows' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'attach_to_workflows', null, 0),
        'enable_advanced_eligibility_report' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'enable_advanced_eligibility_report', null, 0),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      CRM_Core_BAO_Setting::setItem($values['delivery_method'], self::SETTINGS, 'delivery_method');
      CRM_Core_BAO_Setting::setItem($values['attach_to_workflows'], self::SETTINGS, 'attach_to_workflows');
      CRM_Core_BAO_Setting::setItem($values['enable_advanced_eligibility_report'], self::SETTINGS, 'enable_advanced_eligibility_report');

      if (isset($values['issue_inkind']) == true) {
        if ( $values['issue_inkind'] == 1 ) {
          cdntaxreceipts_configure_inkind_fields();
        }
      }
    }
  }

  function processEmailOptions($mode) {
    if ( $mode == 'build' ) {
      $this->add('text', 'email_from', ts('Email From', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->add('text', 'email_archive', ts('Archive Email', array('domain' => 'org.civicrm.cdntaxreceipts')));

      $this->addRule('email_from', 'Enter email from address', 'required');
      $this->addRule('email_archive', 'Enter email archive address', 'required');
    }
    else if ( $mode == 'defaults' ) {
      $defaults = array(
        'email_from' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'email_from'),
        'email_archive' => CRM_Core_BAO_Setting::getItem(self::SETTINGS, 'email_archive'),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      CRM_Core_BAO_Setting::setItem($values['email_from'], self::SETTINGS, 'email_from');
      CRM_Core_BAO_Setting::setItem($values['email_archive'], self::SETTINGS, 'email_archive');
    }
  }

  function postProcess() {
    parent::postProcess();
    $this->processOrgOptions('post');
    $this->processReceiptOptions('post');
    $this->processSystemOptions('post');
    $this->processEmailOptions('post');

    $statusMsg = ts('Your settings have been saved.', array('domain' => 'org.civicrm.cdntaxreceipts'));
    CRM_Core_Session::setStatus( $statusMsg, '', 'success' );
  }
}
