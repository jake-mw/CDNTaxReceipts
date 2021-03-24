<?php

use Civi\Test\HeadlessInterface;

/**
 * @todo This might need to be wrapped in a class to get it to autorun
 * in a test suite. It's not clear to me which files get picked up.
 *
 * @group headless
 */
class functionsTest extends \CiviUnitTestCase implements HeadlessInterface {

  /**
   * @var array
   */
  private $custom_group;
  /**
   * @var array
   */
  private $custom_field;

  public function setUpHeadless() {
    return \Civi\Test::headless()->installMe(__DIR__)->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->custom_group = $this->customGroupCreate();
  }

  public function tearDown() {
    $this->callAPISuccess('CustomField', 'delete', ['id' => $this->custom_field['id']]);
    $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->custom_group['id']]);
    parent::tearDown();
  }

  /**
   * Test _cdntaxreceipts_custom_field_exists()
   * @dataProvider customFieldProvider
   * @param array $create_params
   * @param string $label_to_check
   * @param bool $expected
   */
  public function testCustomFieldExists(array $create_params, string $label_to_check, bool $expected) {
    $this->custom_field = $this->callAPISuccess('CustomField', 'create', array_merge([
      'custom_group_id' => $this->custom_group['id'],
      'data_type' => 'String',
      'html_type' => 'Text',
      'is_required' => 1,
      'is_active' => 1,
    ], $create_params));
    $this->assertSame($expected, _cdntaxreceipts_custom_field_exists($this->custom_group['id'], $label_to_check));
  }

  /**
   * DataProvider for textCustomFieldExists().
   * @return array
   */
  public function customFieldProvider(): array {
    return [
      [
        [
          'name' => 'field1',
          'label' => 'field1',
        ],
        'field1',
        TRUE,
      ],
      [
        [
          'name' => 'field1',
          'label' => 'field1',
        ],
        'field_not',
        FALSE,
      ],
      [
        [
          'label' => 'field1',
        ],
        'field1',
        TRUE,
      ],
      /* This one doesn't work since label is a required field
      [
        [
          'name' => 'field1',
        ],
        'field_not',
        FALSE,
      ],*/
      [
        [
          'name' => 'field1',
          'label' => 'field2',
        ],
        'field1',
        TRUE,
      ],
      [
        [
          'name' => 'field1',
          'label' => 'field2',
        ],
        'field2',
        TRUE,
      ],
      [
        [
          'name' => 'field1',
          'label' => 'field2',
        ],
        'field_not',
        FALSE,
      ],
      [
        [
          'label' => 'This should get munged',
        ],
        'This should get munged',
        TRUE,
      ],
      [
        [
          'label' => 'This should get munged',
        ],
        'This_should_get_munged',
        TRUE,
      ],
      [
        [
          'name' => 'This would get munged but',
          'label' => 'This would get munged but',
        ],
        'This would get munged but',
        TRUE,
      ],
      [
        [
          'name' => 'This would get munged but',
          'label' => 'This would get munged but',
        ],
        'This_would_get_munged_but',
        FALSE,
      ],
    ];
  }

}
