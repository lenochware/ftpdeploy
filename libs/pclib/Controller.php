<?php

namespace pclib;
use pclib;

/**
 *  Base class for any application controller.
 *  Define your controller, say 'products', in file controllers/ProductsController.php
 *  like class ProductsController extends Controller.
 *  Now you can define actions such as: function editAction() { ... return 'your html'; }
 * It will be called on url '?r=products/edit'.
 * @see App::run()
 */
class Controller extends system\BaseObject
{

/**
 * Each action method name must have following postfix.
 * Only action methods are callable by sending request from user browser.
 */
public $ACTION_POSTFIX = 'Action';

/** var App Link to application */
protected $app;

/** Name of the controller without postfix. */
public $name;

/** Name of the called action without postfix. */
public $action;

/** Occurs when Controller is initialized. */
public $onInit;

function __construct(App $app)
{
	parent::__construct();
	$this->app = $app;
	if ($this->app->config['pclib.compatibility']['legacy_classnames']) {
		$this->ACTION_POSTFIX = '_Action';
	}
}

/**
 * Called before every action.
 * Override for controller's setup, testing access permissions, etc.
 **/
function init()
{
	$this->onInit();
}

/*
 * Return list of arguments for requested method, based on supplied params.
 */
function getArgs($actionMethod, array $params)
{
	$args = array();
	$rm = new \ReflectionMethod($this, $actionMethod);
	foreach($rm->getParameters() as $param)  {
		$param_value = $params[$param->name];
		if (!strlen($param_value) and !$param->isOptional()) {
			$this->app->error('Required parameter "%s" for page "%s" missing.', null, $param->name, get_class($this) .'/'.$this->action);
		}
		$args[] = isset($param_value)? $param_value : $param->getDefaultValue();
	}

	return $args;
}

/*
 * Return name of the action to be actually called.
 */
function findActionName($action)
{
	if (!$action) $action = 'index';

	if (method_exists($this, $action.$this->ACTION_POSTFIX)) {
		return $action;
	}
	elseif (method_exists($this, 'default'.$this->ACTION_POSTFIX)) {
		return 'default';		
	}

	return false;
}

/**
 * Call action method of the controller, feeding it with required parameters.
 * @param Action $action called action.
 */
public function run($action)
{
	$this->name = $action->controller;
	$this->action = $this->findActionName($action->method);
	$this->init();

	if (!$this->action) {
		$this->app->httpError(404, 'Page not found: "%s"', null, $action->path);
	}

	$action_method = $this->action.$this->ACTION_POSTFIX;
	$args = $this->getArgs($action_method, $action->params);

	if ($this->action == 'default') {
		$this->action = $action;
	}

	return call_user_func_array(array($this, $action_method), $args);
}

/**
 * Redirect to $route.
 **/
function redirect($route)
{
	$this->app->redirect($route);
}

/**
 * Return model for table $tableName.
 **/
function model($tableName, $id = null)
{
	$model = orm\Model::create($tableName, array(), false);
	
	if ($id) {
		$found = $model->find($id);
		if (!$found) return null;
	}

	return $model;
}

/**
 * Return orm\Selection class.
 **/
function selection($from = null)
{
	$sel = new orm\Selection;
	if ($from) $sel->from($from);
	return $sel;
}


}