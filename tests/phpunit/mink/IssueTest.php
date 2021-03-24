<?php
namespace Cdntaxreceipts\Tests\Mink;

// This feels awkward, but the extension and civi aren't installed yet, so the
// autoloader at the time this gets compiled won't be able to find it since
// it's within our extension. Or at least that seems like what's happening.
require_once 'CdntaxreceiptsBase.php';

class IssueTest extends CdntaxreceiptsBase {

  /**
   * @var array
   *   We always create one contact to start with.
   */
  protected $contact;

  public function setUp(): void {
    parent::setUp();
    $this->createUserAndLogIn();
    $this->contact = $this->createContact();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  public function testIssueTaxReceipt() {
    $contribution = civicrm_api3('Contribution', 'create', [
      'contact_id' => $this->contact['id'],
      'financial_type_id' => 'Donation',
      'total_amount' => '10',
    ]);

    // view the contribution
    $this->drupalGet(\CRM_Utils_System::url("civicrm/contact/view/contribution", "reset=1&id={$contribution['id']}&cid={$this->contact['id']}&action=view", TRUE, NULL, FALSE));

    // click the tax receipt button
    $this->getSession()->getPage()->pressButton('Tax Receipt');
    $this->assertSession()->pageTextContains('A tax receipt has not been issued for this contribution.');

    // I don't know why but we need to wait for it. It's strange because if we
    // don't wait for it then it's not like it can't find it to press, it's that
    // pressing it does nothing. Sometimes we need to press twice.
    $this->assertSession()->waitForElementVisible('css', '.crm-button_qf_ViewTaxReceipt_next');
    $this->getSession()->getPage()->pressButton('_qf_ViewTaxReceipt_next-bottom');
    $this->getSession()->getPage()->pressButton('_qf_ViewTaxReceipt_next-bottom');
    $this->assertSession()->pageTextContains("C-0000000{$contribution['id']}");
    $this->assertSession()->pageTextContains('Re-Issue Tax Receipt');
    $this->htmlOutput();
  }

}
