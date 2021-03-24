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
        'isDefault' => TRUE,
      ),
    ));
    // Set image defaults
    $images = array('receipt_logo', 'receipt_signature', 'receipt_watermark', 'receipt_pdftemplate');
    foreach ($images as $image) {
      if (!empty($defaults[$image])) {
        $this->assign($image, $defaults[$image]);
        if (!file_exists(CRM_Core_Config::singleton()->customFileUploadDir . $defaults[$image])) {
          $this->assign($image.'_class', TRUE);
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
      $this->add('text', 'org_location_issued', ts('Location Issued', array('domain' => 'org.civicrm.cdntaxreceipts')));

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
        'org_name' => Civi::settings()->get('org_name'),
        'org_address_line1' => Civi::settings()->get('org_address_line1'),
        'org_address_line2' => Civi::settings()->get('org_address_line2'),
        'org_tel' => Civi::settings()->get('org_tel'),
        'org_fax' => Civi::settings()->get('org_fax'),
        'org_email' => Civi::settings()->get('org_email'),
        'org_web' => Civi::settings()->get('org_web'),
        'receipt_logo' => Civi::settings()->get('receipt_logo'),
        'receipt_signature' => Civi::settings()->get('receipt_signature'),
        'receipt_watermark' => Civi::settings()->get('receipt_watermark'),
        'receipt_pdftemplate' => Civi::settings()->get('receipt_pdftemplate'),
        'org_charitable_no' => Civi::settings()->get('org_charitable_no'),
        'org_location_issued' => Civi::settings()->get('org_location_issued'),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      Civi::settings()->set('org_name', $values['org_name']);
      Civi::settings()->set('org_address_line1', $values['org_address_line1']);
      Civi::settings()->set('org_address_line2', $values['org_address_line2']);
      Civi::settings()->set('org_tel', $values['org_tel']);
      Civi::settings()->set('org_fax', $values['org_fax']);
      Civi::settings()->set('org_email', $values['org_email']);
      Civi::settings()->set('org_web', $values['org_web']);
      Civi::settings()->set('org_charitable_no', $values['org_charitable_no']);
      Civi::settings()->set('org_location_issued', $values['org_location_issued']);
    }

  }

  function processReceiptOptions($mode) {
    if ( $mode == 'build' ) {
      $this->add('text', 'receipt_prefix', ts('Receipt Prefix', array('domain' => 'org.civicrm.cdntaxreceipts')));
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
        'receipt_prefix' => Civi::settings()->get('receipt_prefix'),
        'receipt_authorized_signature_text' => Civi::settings()->get('receipt_authorized_signature_text'),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      Civi::settings()->set('receipt_prefix', $values['receipt_prefix']);
      Civi::settings()->set('receipt_authorized_signature_text', $values['receipt_authorized_signature_text']);

      foreach ( array('receipt_logo', 'receipt_signature', 'receipt_watermark', 'receipt_pdftemplate') as $key ) {
        $upload_file = $this->getSubmitValue($key);
        if (is_array($upload_file)) {
          if ( $upload_file['error'] == 0 ) {
            $filename = CRM_Utils_File::makeFileName($upload_file['name']);
            if (!move_uploaded_file($upload_file['tmp_name'], CRM_Core_Config::singleton()->customFileUploadDir . $filename)) {
              CRM_Core_Error::fatal(ts('Could not upload the file'));
            }
            Civi::settings()->set($key, $filename);
          }
        }
      }
    }
  }

  function processSystemOptions($mode) {
    if ( $mode == 'build' ) {
      $this->addElement('checkbox', 'issue_inkind', ts('Setup in-kind receipts?', array('domain' => 'org.civicrm.cdntaxreceipts')));

      $delivery_options = array();
      $delivery_options[] = $this->createElement('radio', NULL, NULL, 'Print only', CDNTAX_DELIVERY_PRINT_ONLY);
      $delivery_options[] = $this->createElement('radio', NULL, NULL, 'Email or print', CDNTAX_DELIVERY_PRINT_EMAIL);
      $delivery_options[] = $this->createElement('radio', NULL, NULL, 'Data only', CDNTAX_DELIVERY_DATA_ONLY);
      $this->addGroup($delivery_options, 'delivery_method', ts('Delivery Method', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->addRule('delivery_method', 'Delivery Method', 'required');

      $yesno_options = array();
      $yesno_options[] = $this->createElement('radio', NULL, NULL, 'Yes', 1);
      $yesno_options[] = $this->createElement('radio', NULL, NULL, 'No', 0);
      $this->addGroup($yesno_options, 'attach_to_workflows', ts('Attach receipts to automated workflow messages?', array('domain' => 'org.civicrm.cdntaxreceipts')));
      $this->addRule('attach_to_workflows', 'Attach tax receipts to automated messages', 'required');

      $yesno_options2 = array();
      $yesno_options2[] = $this->createElement('radio', NULL, NULL, 'Yes', 1);
      $yesno_options2[] = $this->createElement('radio', NULL, NULL, 'No', 0);
      $this->addGroup($yesno_options2, 'enable_advanced_eligibility_report', ts('Enable Advanced Eligibility Check?', array('domain' => 'org.civicrm.cdntaxreceipts')));
    }
    else if ( $mode == 'defaults' ) {
      $defaults = array(
        'issue_inkind' => 0,
        'delivery_method' => Civi::settings()->get('delivery_method') ?? CDNTAX_DELIVERY_PRINT_ONLY,
        'attach_to_workflows' => Civi::settings()->get('attach_to_workflows') ?? 0,
        'enable_advanced_eligibility_report' => Civi::settings()->get('enable_advanced_eligibility_report') ?? 0,
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      Civi::settings()->set('delivery_method', $values['delivery_method']);
      Civi::settings()->set('attach_to_workflows', $values['attach_to_workflows']);
      Civi::settings()->set('enable_advanced_eligibility_report', $values['enable_advanced_eligibility_report']);
      if (isset($values['issue_inkind']) == TRUE) {
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
        'email_from' => Civi::settings()->get('email_from'),
        'email_archive' => Civi::settings()->get('email_archive'),
      );
      return $defaults;
    }
    else if ( $mode == 'post' ) {
      $values = $this->exportValues();
      Civi::settings()->set('email_from', $values['email_from']);
      Civi::settings()->set('email_archive', $values['email_archive']);
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
