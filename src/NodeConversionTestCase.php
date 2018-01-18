<?php
namespace Drupal\node_conversion;

/**
 * Class NodeConversionTestCase
 */
class NodeConversionTestCase extends DrupalWebTestCase {
  /**
   * Implementation of getInfo().
   */
  function getInfo() {
    return array(
      'name' => t('Node conversion'),
      'description' => t('Tests diverse aspects of Node Convert.'),
      'group' => t('Node Convert'),
    );
  }

  /**
   * Implements setUp().
   */
  function setUp() {
    // The first thing a setUp() method should always do is call its parent setUp() method.
    // If you need to enable any modules (including the one being tested),
    // add them as function parameters.

    parent::setUp('content', 'node_conversion');

    // Next, perform any required steps for the test methods within this test grouping.
  }

  /**
   * Implementation of tearDown().
   */
  function tearDown() {
    // Perform any clean-up tasks.

    // The last thing a tearDown() method should always do is call its parent tearDown() method.
    parent::tearDown();
  }

  /**
   * Tests simple node conversion.
   */
  function testSimpleNodeConversion() {
    $type1_name = $this->randomName(4);
    $type2_name = $this->randomName(4);
    $type1 = $this->drupalCreateContentType(array('type' => $type1_name, 'name' => $type1_name));
    $type2 = $this->drupalCreateContentType(array('type' => $type2_name, 'name' => $type2_name));
    $edit['type'] = $type1_name;
    $node = $this->drupalCreateNode($edit);
    node_conversion_node_conversion($node->nid, $type2_name, array(), array(), TRUE);
    $result = db_query("SELECT type FROM {node} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();
    $this->assertEqual($result, $type2_name, t("Simple node conversion passed."));
  }

  /**
   * Tests converting an invalid nid.
   */
  function testInvalidNidConvert() {
    $result = node_conversion_node_conversion(-1, $this->randomName(4), array(), array(), TRUE);
    $this->assertFalse($result, t("Node conversion didn't pass due to illegal nid."));
  }

  /**
   * Tests converting via UI.
   */
  function testSimpleNodeConversionUI() {
    $type1_name = $this->randomName(4);
    $type2_name = $this->randomName(4);
    $type1 = $this->drupalCreateContentType(array('type' => $type1_name, 'name' => $type1_name));
    $type2 = $this->drupalCreateContentType(array('type' => $type2_name, 'name' => $type2_name));

    $admin_user = $this->drupalCreateUser(array('administer site configuration', 'access administration pages', 'administer nodes', 'administer content types', 'administer conversion', 'convert from ' . $type1_name, 'convert to ' . $type2_name));
    $this->drupalLogin($admin_user);

    $edit['type'] = $type1_name;
    $node = $this->drupalCreateNode($edit);

    $edit = array();
    $edit['destination_type'] = $type2_name;
    $this->drupalPost('node/' . $node->nid . '/convert', $edit, t("Next"));
    $this->drupalPost(NULL, array(), t("Convert"));
    $this->assertText(t("Node @nid has been converted successfully.", array('@nid' => $node->nid)), t("Simple node conversion ui test passed."));
    $result = db_query("SELECT type FROM {node} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();
    $this->assertEqual($result, $type2_name, t("The converted node type is equal to the destination node type."));
  }

}
