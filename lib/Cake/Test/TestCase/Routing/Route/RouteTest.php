<?php
/**
 * Route Test case file.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Test.Case.Routing.Route
 * @since         CakePHP(tm) v 2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Test\TestCase\Routing\Route;

use Cake\TestSuite\TestCase;
use Cake\Routing\Route\Route;
use Cake\Routing\Router;
use Cake\Core\Configure;
use Cake\Core\App;

/**
 * Test case for Route
 *
 * @package       Cake.Test.Case.Routing.Route
 **/
class RouteTest extends TestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		Configure::write('Routing', array('admin' => null, 'prefixes' => array()));
	}

/**
 * Test the construction of a Route
 *
 * @return void
 **/
	public function testConstruction() {
		$route = new Route('/:controller/:action/:id', array(), array('id' => '[0-9]+'));

		$this->assertEquals('/:controller/:action/:id', $route->template);
		$this->assertEquals(array(), $route->defaults);
		$this->assertEquals(array('id' => '[0-9]+'), $route->options);
		$this->assertFalse($route->compiled());
	}

/**
 * test Route compiling.
 *
 * @return void
 **/
	public function testBasicRouteCompiling() {
		$route = new Route('/', array('controller' => 'pages', 'action' => 'display', 'home'));
		$result = $route->compile();
		$expected = '#^/*$#';
		$this->assertEquals($expected, $result);
		$this->assertEquals(array(), $route->keys);

		$route = new Route('/:controller/:action', array('controller' => 'posts'));
		$result = $route->compile();

		$this->assertRegExp($result, '/posts/edit');
		$this->assertRegExp($result, '/posts/super_delete');
		$this->assertNotRegExp($result, '/posts');
		$this->assertNotRegExp($result, '/posts/super_delete/1');

		$route = new Route('/posts/foo:id', array('controller' => 'posts', 'action' => 'view'));
		$result = $route->compile();

		$this->assertRegExp($result, '/posts/foo:1');
		$this->assertRegExp($result, '/posts/foo:param');
		$this->assertNotRegExp($result, '/posts');
		$this->assertNotRegExp($result, '/posts/');

		$this->assertEquals(array('id'), $route->keys);

		$route = new Route('/:plugin/:controller/:action/*', array('plugin' => 'test_plugin', 'action' => 'index'));
		$result = $route->compile();
		$this->assertRegExp($result, '/test_plugin/posts/index');
		$this->assertRegExp($result, '/test_plugin/posts/edit/5');
		$this->assertRegExp($result, '/test_plugin/posts/edit/5/name:value/nick:name');
	}

/**
 * test that route parameters that overlap don't cause errors.
 *
 * @return void
 */
	public function testRouteParameterOverlap() {
		$route = new Route('/invoices/add/:idd/:id', array('controller' => 'invoices', 'action' => 'add'));
		$result = $route->compile();
		$this->assertRegExp($result, '/invoices/add/1/3');

		$route = new Route('/invoices/add/:id/:idd', array('controller' => 'invoices', 'action' => 'add'));
		$result = $route->compile();
		$this->assertRegExp($result, '/invoices/add/1/3');
	}

/**
 * test compiling routes with keys that have patterns
 *
 * @return void
 **/
	public function testRouteCompilingWithParamPatterns() {
		$route = new Route(
			'/:controller/:action/:id',
			array(),
			array('id' => Router::ID)
		);
		$result = $route->compile();
		$this->assertRegExp($result, '/posts/edit/1');
		$this->assertRegExp($result, '/posts/view/518098');
		$this->assertNotRegExp($result, '/posts/edit/name-of-post');
		$this->assertNotRegExp($result, '/posts/edit/4/other:param');
		$this->assertEquals(array('controller', 'action', 'id'), $route->keys);

		$route = new Route(
			'/:lang/:controller/:action/:id',
			array('controller' => 'testing4'),
			array('id' => Router::ID, 'lang' => '[a-z]{3}')
		);
		$result = $route->compile();
		$this->assertRegExp($result, '/eng/posts/edit/1');
		$this->assertRegExp($result, '/cze/articles/view/1');
		$this->assertNotRegExp($result, '/language/articles/view/2');
		$this->assertNotRegExp($result, '/eng/articles/view/name-of-article');
		$this->assertEquals(array('lang', 'controller', 'action', 'id'), $route->keys);

		foreach (array(':', '@', ';', '$', '-') as $delim) {
			$route = new Route('/posts/:id' . $delim . ':title');
			$result = $route->compile();

			$this->assertRegExp($result, '/posts/1' . $delim . 'name-of-article');
			$this->assertRegExp($result, '/posts/13244' . $delim . 'name-of_Article[]');
			$this->assertNotRegExp($result, '/posts/11!nameofarticle');
			$this->assertNotRegExp($result, '/posts/11');

			$this->assertEquals(array('id', 'title'), $route->keys);
		}

		$route = new Route(
			'/posts/:id::title/:year',
			array('controller' => 'posts', 'action' => 'view'),
			array('id' => Router::ID, 'year' => Router::YEAR, 'title' => '[a-z-_]+')
		);
		$result = $route->compile();
		$this->assertRegExp($result, '/posts/1:name-of-article/2009/');
		$this->assertRegExp($result, '/posts/13244:name-of-article/1999');
		$this->assertNotRegExp($result, '/posts/hey_now:nameofarticle');
		$this->assertNotRegExp($result, '/posts/:nameofarticle/2009');
		$this->assertNotRegExp($result, '/posts/:nameofarticle/01');
		$this->assertEquals(array('id', 'title', 'year'), $route->keys);

		$route = new Route(
			'/posts/:url_title-(uuid::id)',
			array('controller' => 'posts', 'action' => 'view'),
			array('pass' => array('id', 'url_title'), 'id' => Router::ID)
		);
		$result = $route->compile();
		$this->assertRegExp($result, '/posts/some_title_for_article-(uuid:12534)/');
		$this->assertRegExp($result, '/posts/some_title_for_article-(uuid:12534)');
		$this->assertNotRegExp($result, '/posts/');
		$this->assertNotRegExp($result, '/posts/nameofarticle');
		$this->assertNotRegExp($result, '/posts/nameofarticle-12347');
		$this->assertEquals(array('url_title', 'id'), $route->keys);
	}

/**
 * test more complex route compiling & parsing with mid route greedy stars
 * and optional routing parameters
 *
 * @return void
 */
	public function testComplexRouteCompilingAndParsing() {
		$route = new Route(
			'/posts/:month/:day/:year/*',
			array('controller' => 'posts', 'action' => 'view'),
			array('year' => Router::YEAR, 'month' => Router::MONTH, 'day' => Router::DAY)
		);
		$result = $route->compile();
		$this->assertRegExp($result, '/posts/08/01/2007/title-of-post');
		$result = $route->parse('/posts/08/01/2007/title-of-post');

		$this->assertEquals(count($result), 6);
		$this->assertEquals($result['controller'], 'posts');
		$this->assertEquals($result['action'], 'view');
		$this->assertEquals($result['year'], '2007');
		$this->assertEquals($result['month'], '08');
		$this->assertEquals($result['day'], '01');
		$this->assertEquals($result['pass'][0], 'title-of-post');

		$route = new Route(
			"/:extra/page/:slug/*",
			array('controller' => 'pages', 'action' => 'view', 'extra' => null),
			array("extra" => '[a-z1-9_]*', "slug" => '[a-z1-9_]+', "action" => 'view')
		);
		$result = $route->compile();

		$this->assertRegExp($result, '/some_extra/page/this_is_the_slug');
		$this->assertRegExp($result, '/page/this_is_the_slug');
		$this->assertEquals(array('extra', 'slug'), $route->keys);
		$this->assertEquals(array('extra' => '[a-z1-9_]*', 'slug' => '[a-z1-9_]+', 'action' => 'view'), $route->options);
		$expected = array(
			'controller' => 'pages',
			'action' => 'view'
		);
		$this->assertEquals($expected, $route->defaults);

		$route = new Route(
			'/:controller/:action/*',
			array('project' => false),
			array(
				'controller' => 'source|wiki|commits|tickets|comments|view',
				'action' => 'branches|history|branch|logs|view|start|add|edit|modify'
			)
		);
		$this->assertFalse($route->parse('/chaw_test/wiki'));

		$result = $route->compile();
		$this->assertNotRegExp($result, '/some_project/source');
		$this->assertRegExp($result, '/source/view');
		$this->assertRegExp($result, '/source/view/other/params');
		$this->assertNotRegExp($result, '/chaw_test/wiki');
		$this->assertNotRegExp($result, '/source/wierd_action');
	}

/**
 * test that routes match their pattern.
 *
 * @return void
 **/
	public function testMatchBasic() {
		$route = new Route('/:controller/:action/:id', array('plugin' => null));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'plugin' => null));
		$this->assertFalse($result);

		$result = $route->match(array('plugin' => null, 'controller' => 'posts', 'action' => 'view', 0));
		$this->assertFalse($result);

		$result = $route->match(array('plugin' => null, 'controller' => 'posts', 'action' => 'view', 'id' => 1));
		$this->assertEquals('/posts/view/1', $result);

		$route = new Route('/', array('controller' => 'pages', 'action' => 'display', 'home'));
		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 'home'));
		$this->assertEquals('/', $result);

		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 'about'));
		$this->assertFalse($result);

		$route = new Route('/pages/*', array('controller' => 'pages', 'action' => 'display'));
		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 'home'));
		$this->assertEquals('/pages/home', $result);

		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 'about'));
		$this->assertEquals('/pages/about', $result);

		$route = new Route('/blog/:action', array('controller' => 'posts'));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view'));
		$this->assertEquals('/blog/view', $result);

		$result = $route->match(array('controller' => 'nodes', 'action' => 'view'));
		$this->assertFalse($result);

		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 1));
		$this->assertFalse($result);

		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'id' => 2));
		$this->assertFalse($result);

		$route = new Route('/foo/:controller/:action', array('action' => 'index'));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view'));
		$this->assertEquals('/foo/posts/view', $result);

		$route = new Route('/:plugin/:id/*', array('controller' => 'posts', 'action' => 'view'));
		$result = $route->match(array('plugin' => 'test', 'controller' => 'posts', 'action' => 'view', 'id' => '1'));
		$this->assertEquals('/test/1/', $result);

		$result = $route->match(array('plugin' => 'fo', 'controller' => 'posts', 'action' => 'view', 'id' => '1', '0'));
		$this->assertEquals('/fo/1/0', $result);

		$result = $route->match(array('plugin' => 'fo', 'controller' => 'nodes', 'action' => 'view', 'id' => 1));
		$this->assertFalse($result);

		$result = $route->match(array('plugin' => 'fo', 'controller' => 'posts', 'action' => 'edit', 'id' => 1));
		$this->assertFalse($result);

		$route = new Route('/admin/subscriptions/:action/*', array(
			'controller' => 'subscribe', 'admin' => true, 'prefix' => 'admin'
		));

		$url = array('controller' => 'subscribe', 'admin' => true, 'action' => 'edit', 1);
		$result = $route->match($url);
		$expected = '/admin/subscriptions/edit/1';
		$this->assertEquals($expected, $result);

		$url = array(
			'controller' => 'subscribe',
			'admin' => true,
			'action' => 'edit_admin_e',
			1
		);
		$result = $route->match($url);
		$expected = '/admin/subscriptions/edit_admin_e/1';
		$this->assertEquals($expected, $result);
	}

/**
 * test that non-greedy routes fail with extra passed args
 *
 * @return void
 */
	public function testGreedyRouteFailurePassedArg() {
		$route = new Route('/:controller/:action', array('plugin' => null));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view', '0'));
		$this->assertFalse($result);

		$route = new Route('/:controller/:action', array('plugin' => null));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'test'));
		$this->assertFalse($result);
	}

/**
 * test that non-greedy routes fail with extra passed args
 *
 * @return void
 */
	public function testGreedyRouteFailureNamedParam() {
		$route = new Route('/:controller/:action', array('plugin' => null));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'page' => 1));
		$this->assertFalse($result);
	}

/**
 * test that falsey values do not interrupt a match.
 *
 * @return void
 */
	public function testMatchWithFalseyValues() {
		$route = new Route('/:controller/:action/*', array('plugin' => null));
		$result = $route->match(array(
			'controller' => 'posts', 'action' => 'index', 'plugin' => null, 'admin' => false
		));
		$this->assertEquals('/posts/index/', $result);
	}

/**
 * test match() with greedy routes, and passed args.
 *
 * @return void
 */
	public function testMatchWithPassedArgs() {
		$route = new Route('/:controller/:action/*', array('plugin' => null));

		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'plugin' => null, 5));
		$this->assertEquals('/posts/view/5', $result);

		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'plugin' => null, 0));
		$this->assertEquals('/posts/view/0', $result);

		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'plugin' => null, '0'));
		$this->assertEquals('/posts/view/0', $result);

		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'plugin' => null, 'word space'));
		$this->assertEquals('/posts/view/word%20space', $result);

		$route = new Route('/test2/*', array('controller' => 'pages', 'action' => 'display', 2));
		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 1));
		$this->assertFalse($result);

		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 2, 'something'));
		$this->assertEquals('/test2/something', $result);

		$result = $route->match(array('controller' => 'pages', 'action' => 'display', 5, 'something'));
		$this->assertFalse($result);
	}

/**
 * test that match with patterns works.
 *
 * @return void
 */
	public function testMatchWithPatterns() {
		$route = new Route('/:controller/:action/:id', array('plugin' => null), array('id' => '[0-9]+'));
		$result = $route->match(array('controller' => 'posts', 'action' => 'view', 'id' => 'foo'));
		$this->assertFalse($result);

		$result = $route->match(array('plugin' => null, 'controller' => 'posts', 'action' => 'view', 'id' => '9'));
		$this->assertEquals('/posts/view/9', $result);

		$result = $route->match(array('plugin' => null, 'controller' => 'posts', 'action' => 'view', 'id' => '922'));
		$this->assertEquals('/posts/view/922', $result);

		$result = $route->match(array('plugin' => null, 'controller' => 'posts', 'action' => 'view', 'id' => 'a99'));
		$this->assertFalse($result);
	}

/**
 * test persistParams ability to persist parameters from $params and remove params.
 *
 * @return void
 */
	public function testPersistParams() {
		$route = new Route(
			'/:lang/:color/blog/:action',
			array('controller' => 'posts'),
			array('persist' => array('lang', 'color'))
		);
		$url = array('controller' => 'posts', 'action' => 'index');
		$params = array('lang' => 'en', 'color' => 'blue');
		$result = $route->persistParams($url, $params);
		$this->assertEquals('en', $result['lang']);
		$this->assertEquals('blue', $result['color']);

		$url = array('controller' => 'posts', 'action' => 'index', 'color' => 'red');
		$params = array('lang' => 'en', 'color' => 'blue');
		$result = $route->persistParams($url, $params);
		$this->assertEquals('en', $result['lang']);
		$this->assertEquals('red', $result['color']);
	}

/**
 * test the parse method of Route.
 *
 * @return void
 */
	public function testParse() {
		$route = new Route(
			'/:controller/:action/:id',
			array('controller' => 'testing4', 'id' => null),
			array('id' => Router::ID)
		);
		$route->compile();
		$result = $route->parse('/posts/view/1');
		$this->assertEquals('posts', $result['controller']);
		$this->assertEquals('view', $result['action']);
		$this->assertEquals('1', $result['id']);

		$route = new Route(
			'/admin/:controller',
			array('prefix' => 'admin', 'admin' => 1, 'action' => 'index')
		);
		$route->compile();
		$result = $route->parse('/admin/');
		$this->assertFalse($result);

		$result = $route->parse('/admin/posts');
		$this->assertEquals('posts', $result['controller']);
		$this->assertEquals('index', $result['action']);
	}

/**
 * Test that :key elements are urldecoded
 *
 * @return void
 */
	public function testParseUrlDecodeElements() {
		$route = new Route(
			'/:controller/:slug',
			array('action' => 'view')
		);
		$route->compile();
		$result = $route->parse('/posts/%E2%88%82%E2%88%82');
		$this->assertEquals('posts', $result['controller']);
		$this->assertEquals('view', $result['action']);
		$this->assertEquals('∂∂', $result['slug']);

		$result = $route->parse('/posts/∂∂');
		$this->assertEquals('posts', $result['controller']);
		$this->assertEquals('view', $result['action']);
		$this->assertEquals('∂∂', $result['slug']);
	}

/**
 * test numerically indexed defaults, get appended to pass
 *
 * @return void
 */
	public function testParseWithPassDefaults() {
		$route = new Route('/:controller', array('action' => 'display', 'home'));
		$result = $route->parse('/posts');
		$expected = array(
			'controller' => 'posts',
			'action' => 'display',
			'pass' => array('home'),
		);
		$this->assertEquals($expected, $result);
	}

/**
 * test that http header conditions can cause route failures.
 *
 * @return void
 */
	public function testParseWithHttpHeaderConditions() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$route = new Route('/sample', array('controller' => 'posts', 'action' => 'index', '[method]' => 'POST'));

		$this->assertFalse($route->parse('/sample'));
	}

/**
 * test that patterns work for :action
 *
 * @return void
 */
	public function testPatternOnAction() {
		$route = new Route(
			'/blog/:action/*',
			array('controller' => 'blog_posts'),
			array('action' => 'other|actions')
		);
		$result = $route->match(array('controller' => 'blog_posts', 'action' => 'foo'));
		$this->assertFalse($result);

		$result = $route->match(array('controller' => 'blog_posts', 'action' => 'actions'));
		$this->assertNotEmpty($result);

		$result = $route->parse('/blog/other');
		$expected = array('controller' => 'blog_posts', 'action' => 'other', 'pass' => array());
		$this->assertEquals($expected, $result);

		$result = $route->parse('/blog/foobar');
		$this->assertFalse($result);
	}

/**
 * test the parseArgs method
 *
 * @return void
 */
	public function testParsePassedArgument() {
		$route = new Route('/:controller/:action/*');
		$result = $route->parse('/posts/edit/1/2/0');
		$expected = array(
			'controller' => 'posts',
			'action' => 'edit',
			'pass' => array('1', '2', '0'),
		);
		$this->assertEquals($expected, $result);
	}

/**
 * test restructuring args with pass key
 *
 * @return void
 */
	public function testPassArgRestructure() {
		$route = new Route('/:controller/:action/:slug', array(), array(
			'pass' => array('slug')
		));
		$result = $route->parse('/posts/view/my-title');
		$expected = array(
			'controller' => 'posts',
			'action' => 'view',
			'slug' => 'my-title',
			'pass' => array('my-title'),
		);
		$this->assertEquals($expected, $result, 'Slug should have moved');
	}

/**
 * Test the /** special type on parsing.
 *
 * @return void
 */
	public function testParseTrailing() {
		$route = new Route('/:controller/:action/**');
		$result = $route->parse('/posts/index/1/2/3/foo:bar');
		$expected = array(
			'controller' => 'posts',
			'action' => 'index',
			'pass' => array('1/2/3/foo:bar'),
		);
		$this->assertEquals($expected, $result);

		$result = $route->parse('/posts/index/http://example.com');
		$expected = array(
			'controller' => 'posts',
			'action' => 'index',
			'pass' => array('http://example.com'),
		);
		$this->assertEquals($expected, $result);
	}

/**
 * Test the /** special type on parsing - UTF8.
 *
 * @return void
 */
	public function testParseTrailingUTF8() {
		$route = new Route('/category/**', array('controller' => 'categories', 'action' => 'index'));
		$result = $route->parse('/category/%D9%85%D9%88%D8%A8%D8%A7%DB%8C%D9%84');
		$expected = array(
			'controller' => 'categories',
			'action' => 'index',
			'pass' => array('موبایل'),
		);
		$this->assertEquals($expected, $result);
	}

/**
 * test getName();
 *
 * @return void
 */
	public function testGetName() {
		$route = new Route('/foo/bar', array(), array('_name' => 'testing'));
		$this->assertEquals('testing', $route->getName());

		$route = new Route('/:controller/:action');
		$this->assertEquals('_controller:_action', $route->getName());

		$route = new Route('/articles/:action', array('controller' => 'posts'));
		$this->assertEquals('posts:_action', $route->getName());

		$route = new Route('/articles/list', array('controller' => 'posts', 'action' => 'index'));
		$this->assertEquals('posts:index', $route->getName());
	}

/**
 * Test getName() with plugins.
 *
 * @return void
 */
	public function testGetNamePlugins() {
		$route = new Route(
			'/a/:controller/:action',
			array('plugin' => 'asset')
		);
		$this->assertEquals('asset._controller:_action', $route->getName());

		$route = new Route(
			'/a/assets/:action',
			array('plugin' => 'asset', 'controller' => 'assets')
		);
		$this->assertEquals('asset.assets:_action', $route->getName());

		$route = new Route(
			'/assets/get',
			array('plugin' => 'asset', 'controller' => 'assets', 'action' => 'get')
		);
		$this->assertEquals('asset.assets:get', $route->getName());
	}

}
