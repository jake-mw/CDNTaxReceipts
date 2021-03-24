<?php

use Civi\Test\HeadlessInterface;

/**
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Cdntaxreceipts_HookTest extends \CiviUnitTestCase implements HeadlessInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test eligibleAmount
   */
  public function testEligibleAmount() {
    $contact_id = $this->individualCreate([], 0, TRUE);
    $contribution = $this->callAPISuccess('contribution', 'create', [
      'contact_id' => $contact_id,
      'financial_type_id' => 'Donation',
      'total_amount' => '10',
    ]);
    $amount = cdntaxreceipts_eligibleAmount($contribution['id']);
    $this->assertEquals(10, $amount);
  }

  /**
   * Test eligibleAmount with hook
   * @see hookForEligibleAmount
   */
  public function testEligibleAmountWithHook() {
    \Civi::dispatcher()->addListener('hook_cdntaxreceipts_eligibleAmount', [$this, 'hookForEligibleAmount']);
    $contact_id = $this->individualCreate([], 0, TRUE);
    $contribution = $this->callAPISuccess('contribution', 'create', [
      'contact_id' => $contact_id,
      'financial_type_id' => 'Donation',
      'total_amount' => '10',
    ]);
    // We're expecting our hook to subtract 5 from the amount.
    $amount = cdntaxreceipts_eligibleAmount($contribution['id']);
    $this->assertEquals(5, $amount);
  }

  /**
   * This is the listener for hook_cdntaxreceipts_eligibleAmount
   *
   * @param \Civi\Core\Event\GenericHookEvent $e
   *   has member CRM_Contribute_DAO_Contribution $contribution
   */
  public function hookForEligibleAmount(\Civi\Core\Event\GenericHookEvent $e) {
    // alter the existing amount by subtracting 5
    $e->addReturnValues([$e->contribution->total_amount - 5]);
  }

}
