<?php
use Civi\Test\HeadlessInterface;

/**
 * @group headless
 */
class CRM_Cdntaxreceipts_UtfTest extends \CiviUnitTestCase implements HeadlessInterface {

  public function setUpHeadless() {
  }

  /**
   * Make sure the extension installs properly on all types of unicode.
   *
   * @dataProvider utfDataProvider
   * @param string $charset
   */
  public function testInstallCharsets(string $charset) {
    // Yeah this is awkward since it means we need to know what's in the
    // dataProvider and what the possibilities are but good enough for now.
    // Run the conversion task or the reverse if needed to get a starting
    // state that makes sense for the given charset, since it might be
    // different from the installed database.
    unset(\Civi::$statics['CRM_Core_BAO_SchemaHandler']);
    if ($charset === 'utf8mb4' && stripos(CRM_Core_BAO_SchemaHandler::getInUseCollation(), 'utf8mb4') === FALSE) {
      $this->callAPISuccess('System', 'utf8conversion', []);
    }
    elseif ($charset === 'utf8' && substr(CRM_Core_BAO_SchemaHandler::getInUseCollation(), 0, 8) === 'utf8mb4_') {
      $this->callAPISuccess('System', 'utf8conversion', ['is_revert' => 1]);
    }

    unset(\Civi::$statics['CRM_Core_BAO_SchemaHandler']);

    // install our extension
    // This sort of works, but only once? So use api instead.
    // \Civi\Test::headless()->installMe(__DIR__)->apply();
    $this->callAPISuccess('Extension', 'install', ['keys' => 'org.civicrm.cdntaxreceipts']);

    // Check if we have the same charset/collation as core.
    $dao = CRM_Core_DAO::executeQuery("SHOW TABLE STATUS LIKE 'cdntaxreceipts_log'");
    $dao->fetch();
    $this->assertStringStartsWith("{$charset}_", $dao->Collation);

    // This doesn't seem to actually uninstall it? So use api instead.
    //\Civi\Test::headless()->uninstallMe(__DIR__)->apply();
    $this->callAPISuccess('Extension', 'disable', ['keys' => 'org.civicrm.cdntaxreceipts']);
    $this->callAPISuccess('Extension', 'uninstall', ['keys' => 'org.civicrm.cdntaxreceipts']);
  }

  /**
   * Data Provider for testInstallCharsets
   * @return array
   */
  public function utfDataProvider():array {
    return [
      ['utf8'],
      ['utf8mb4'],
    ];
  }

}
