<?php
/**
 * XmlViewTest file
 *
 * PHP 5
 *
 * CakePHP(tm) Tests <http://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/2.0/en/development/testing.html CakePHP(tm) Tests
 * @package       Cake.Test.Case.View
 * @since         CakePHP(tm) v 2.1.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Test\TestCase\View;
use Cake\Controller\Controller;
use Cake\Core\App;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\TestSuite\TestCase;
use Cake\Utility\Xml;
use Cake\View\XmlView;

/**
 * XmlViewTest
 *
 * @package       Cake.Test.Case.View
 */
class XmlViewTest extends TestCase {

/**
 * testRenderWithoutView method
 *
 * @return void
 */
	public function testRenderWithoutView() {
		$Request = new Request();
		$Response = new Response();
		$Controller = new Controller($Request, $Response);
		$data = array('users' => array('user' => array('user1', 'user2')));
		$Controller->set(array('users' => $data, '_serialize' => 'users'));
		$View = new XmlView($Controller);
		$output = $View->render(false);

		$this->assertSame(Xml::build($data)->asXML(), $output);
		$this->assertSame('application/xml', $Response->type());

		$data = array(
			array(
				'User' => array(
					'username' => 'user1'
				)
			),
			array(
				'User' => array(
					'username' => 'user2'
				)
			)
		);
		$Controller->set(array('users' => $data, '_serialize' => 'users'));
		$View = new XmlView($Controller);
		$output = $View->render(false);

		$expected = Xml::build(array('response' => array('users' => $data)))->asXML();
		$this->assertSame($expected, $output);
	}

/**
 * Test render with an array in _serialize
 *
 * @return void
 */
	public function testRenderWithoutViewMultiple() {
		$Request = new Request();
		$Response = new Response();
		$Controller = new Controller($Request, $Response);
		$data = array('no' => 'nope', 'user' => 'fake', 'list' => array('item1', 'item2'));
		$Controller->set($data);
		$Controller->set('_serialize', array('no', 'user'));
		$View = new XmlView($Controller);
		$output = $View->render(false);

		$expected = array(
			'response' => array('no' => $data['no'], 'user' => $data['user'])
		);
		$this->assertSame(Xml::build($expected)->asXML(), $output);
		$this->assertSame('application/xml', $Response->type());
	}

/**
 * testRenderWithView method
 *
 * @return void
 */
	public function testRenderWithView() {
		App::build(array('View' => array(
			CAKE . 'Test' . DS . 'TestApp' . DS . 'View' . DS
		)));
		$Request = new Request();
		$Response = new Response();
		$Controller = new Controller($Request, $Response);
		$Controller->name = $Controller->viewPath = 'Posts';

		$data = array(
			array(
				'User' => array(
					'username' => 'user1'
				)
			),
			array(
				'User' => array(
					'username' => 'user2'
				)
			)
		);
		$Controller->set('users', $data);
		$View = new XmlView($Controller);
		$output = $View->render('index');

		$expected = array(
			'users' => array('user' => array('user1', 'user2'))
		);
		$expected = Xml::build($expected)->asXML();
		$this->assertSame($expected, $output);
		$this->assertSame('application/xml', $Response->type());
		$this->assertInstanceOf('Cake\View\HelperCollection', $View->Helpers);
	}

}
