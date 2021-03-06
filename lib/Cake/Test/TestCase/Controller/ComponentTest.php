<?php
/**
 * ComponentTest file
 *
 * PHP 5
 *
 * CakePHP(tm) Tests <http://book.cakephp.org/view/1196/Testing>
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/view/1196/Testing CakePHP(tm) Tests
 * @package       Cake.Test.Case.Controller
 * @since         CakePHP(tm) v 1.2.0.5436
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Test\TestCase\Controller;
use Cake\Controller\Component;
use Cake\Controller\ComponentCollection;
use Cake\Controller\Controller;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\Utility\ClassRegistry;
use TestApp\Controller\ComponentTestController;
use TestApp\Controller\Component\AppleComponent;
use TestApp\Controller\Component\OrangeComponent;

/**
 * ComponentTest class
 *
 * @package       Cake.Test.Case.Controller
 */
class ComponentTest extends TestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		Configure::write('App.namespace', 'TestApp');

		$this->_pluginPaths = App::path('Plugin');
		App::build(array(
			'Plugin' => array(CAKE . 'Test' . DS . 'TestApp' . DS . 'Plugin' . DS)
		));
	}

/**
 * test accessing inner components.
 *
 * @return void
 */
	public function testInnerComponentConstruction() {
		$Collection = new ComponentCollection();
		$Component = new AppleComponent($Collection);

		$this->assertInstanceOf('TestApp\Controller\Component\OrangeComponent', $Component->Orange, 'class is wrong');
	}

/**
 * test component loading
 *
 * @return void
 */
	public function testNestedComponentLoading() {
		$Collection = new ComponentCollection();
		$Apple = new AppleComponent($Collection);

		$this->assertInstanceOf('TestApp\Controller\Component\OrangeComponent', $Apple->Orange, 'class is wrong');
		$this->assertInstanceOf('TestApp\Controller\Component\BananaComponent', $Apple->Orange->Banana, 'class is wrong');
		$this->assertTrue(empty($Apple->Session));
		$this->assertTrue(empty($Apple->Orange->Session));
	}

/**
 * test that component components are not enabled in the collection.
 *
 * @return void
 */
	public function testInnerComponentsAreNotEnabled() {
		$Collection = new ComponentCollection();
		$Apple = $Collection->load('Apple');

		$this->assertInstanceOf('TestApp\Controller\Component\OrangeComponent', $Apple->Orange, 'class is wrong');
		$result = $Collection->enabled();
		$this->assertEquals(array('Apple'), $result, 'Too many components enabled.');
	}

/**
 * test a component being used more than once.
 *
 * @return void
 */
	public function testMultipleComponentInitialize() {
		$Collection = new ComponentCollection();
		$Banana = $Collection->load('Banana');
		$Orange = $Collection->load('Orange');

		$this->assertSame($Banana, $Orange->Banana, 'Should be references');
		$Banana->testField = 'OrangeField';

		$this->assertSame($Banana->testField, $Orange->Banana->testField, 'References are broken');
	}

/**
 * Test mutually referencing components.
 *
 * @return void
 */
	public function testSomethingReferencingCookieComponent() {
		$Controller = new ComponentTestController();
		$Controller->components = array('SomethingWithCookie');
		$Controller->uses = false;
		$Controller->constructClasses();
		$Controller->Components->trigger('initialize', array(&$Controller));
		$Controller->beforeFilter();
		$Controller->Components->trigger('startup', array(&$Controller));

		$this->assertInstanceOf('TestApp\Controller\Component\SomethingWithCookieComponent', $Controller->SomethingWithCookie);
		$this->assertInstanceOf('Cake\Controller\Component\CookieComponent', $Controller->SomethingWithCookie->Cookie);
	}

}
