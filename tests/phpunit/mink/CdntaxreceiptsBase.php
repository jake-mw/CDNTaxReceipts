<?php
namespace Cdntaxreceipts\Tests\Mink;

use Drupal\Tests\civicrm\FunctionalJavascript\CiviCrmTestBase;

class CdntaxreceiptsBase extends CiviCrmTestBase {

  /**
   * @var int
   *   The uf_id of the logged in user.
   */
  protected $_loggedInUser = NULL;

  public function setUp(): void {
    parent::setUp();

    /**
     * Not much point to these tests if our extension isn't installed!
     * But you need to have set the path to the extensions dir where you're
     * developing this extension, since it's expecting everything under
     * the simpletest directory, but that doesn't exist yet until the tests
     * start.
     * Set it in phpunit.xml with <env name="DEV_EXTENSION_DIR" value="path_to_ext_folder"/>
     */
    if ($extdir = getenv('DEV_EXTENSION_DIR')) {
      \Civi::settings()->set('extensionsDir', $extdir);
      // Is there a better way to reset the extension system?
      \CRM_Core_Config::singleton(TRUE, TRUE);
      \CRM_Extension_System::setSingleton(new \CRM_Extension_System());
    }

    require_once 'api/api.php';
    civicrm_api3('Extension', 'install', ['keys' => 'org.civicrm.cdntaxreceipts']);
    // Drupal 8 is super cache-y.
    drupal_flush_all_caches();

    // Need this otherwise our new permission isn't available yet.
    unset(\Civi::$statics['CRM_Core_Permission']['basicPermissions']);

    $this->configureTaxReceiptSettings();
    $this->configureCiviSettings();
  }

  /**
   * Pretty much every test is going to start with this.
   */
  public function createUserAndLogIn(): void {
    $account = $this->createUser([
      'administer CiviCRM',
      'access CiviCRM',
      'administer CiviCRM system',
      'administer CiviCRM data',
      'access all custom data',
      'edit all contacts',
      'delete contacts',
      'access CiviContribute',
      'edit contributions',
      'delete in CiviContribute',
      'issue cdn tax receipts',
    ]);
    $this->drupalLogin($account);
    $this->_loggedInUser = $account->uid;
  }

  /**
   * Create a contact. Helper function since the usual individualCreate()
   * is not available.
   * @param int $index There are a couple stock contacts. You can pick one as the base params to use.
   * @param array $params Some params to merge into the base.
   * @return array
   */
  public function createContact(int $index = 0, array $params = []): array {
    $stockContacts = [
      'first_name' => ['Anthony', 'Joe', 'Terrence', 'Lucie', 'Albert', 'Bill', 'Kim'],
      'last_name' => ['Anderson', 'Miller', 'Smith', 'Collins', 'Peterson', 'Johnson', 'Li'],
    ];
    $vars = array_merge([
      'contact_type' => 'Individual',
      'first_name' => $stockContacts['first_name'][$index],
      'last_name' => $stockContacts['last_name'][$index],
      'email' => strtolower("{$stockContacts['first_name'][$index]}.{$stockContacts['last_name'][$index]}@example.org"),
      'phone' => preg_replace('/[^0-9]/', '', bin2hex("{$stockContacts['first_name'][$index]}{$stockContacts['last_name'][$index]}")),
    ], $params);

    // Phone doesn't work the same as email for create.
    // If the input params didn't blank it out, convert to right format.
    if (!empty($vars['phone'])) {
      $vars['api.Phone.create'] = ['phone' => $vars['phone']];
      unset($vars['phone']);
    }

    return civicrm_api3('Contact', 'create', $vars);
  }

  /**
   * Configure the extension, i.e. fill out the settings page.
   */
  private function configureTaxReceiptSettings(): void {
    \Civi::settings()->add([
      'org_name' => 'CDN Tax Org',
      'org_address_line1' => '123 Main St.',
      'org_address_line2' => 'Ababa, AA  A1A 1A1',
      'org_tel' => '123-456-7890',
      'org_fax' => '',
      'org_email' => 'cdntaxorg@example.org',
      'org_web' => 'https://cdntaxorg.example.org',
      'org_charitable_no' => '12345-678-RR0001',
      'receipt_prefix' => 'C-',
      'receipt_serial' => 0,
      'receipt_authorized_signature_text' => 'Receet Sighnor',
      'issue_inkind' => 0,
      'delivery_method' => CDNTAX_DELIVERY_DATA_ONLY,
      'attach_to_workflows' => 0,
      'enable_advanced_eligibility_report' => 0,
      'email_from' => 'cdntaxorg@example.org',
      'email_archive' => 'cdntaxorg@example.org',
    ]);
  }

  /**
   * Miscellaneous civi settings
   */
  private function configureCiviSettings(): void {
    \Civi::settings()->add([
      'ajaxPopupsEnabled' => 0,
      'backtrace' => 1,
    ]);
  }

}
