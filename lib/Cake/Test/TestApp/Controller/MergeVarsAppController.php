<?php
/**
 * MergeVarsAppController
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Controller
 * @since         CakePHP(tm) v 3.0.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace TestApp\Controller;
use Cake\Controller\Controller;

/**
 * Test case AppController
 *
 */
class MergeVarsAppController extends Controller {

/**
 * components
 *
 * @var array
 */
	public $components = array('MergeVar' => array('flag', 'otherFlag', 'redirect' => false));

/**
 * helpers
 *
 * @var array
 */
	public $helpers = array('MergeVar' => array('format' => 'html', 'terse'));
}
