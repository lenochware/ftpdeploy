<?php
/**
 * @file
 * Web application.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Gives global access to web application.
 * It is facade for application services and general datastructures.
 * Features:
 * - application configuration: addConfig()
 * - working with controllers: run()
 * - working with services: setService()
 * - layout: setLayout(), message(), error()
 * - environment, log(), language ...
 */
class App extends system\BaseObject
{
/** Name of the aplication */
public $name;

/** Application configuration. */
public $config = array();

/** application base paths (webroot, basedir, baseurl and pclib) */
public $paths;

/** Master template of the website. @see setLayout() */
public $layout;

/** Storage of the global services - Db, Auth, Logger etc. */
public $services = array();

/** Current environment (such as 'develop','test','production'). */
public $environment;

/** Enabling debugMode will display debug-toolbar. */
public $debugMode = false;

public $indexFile = 'index.php';

/** var ErrorHandler */
public $errorHandler;

/** Occurs when App.error() method is called. */ 
public $onError;

/** Occurs before loading and running Controller. */ 
public $onBeforeRun;

/** Occurs after Controller has been executed. */ 
public $onAfterRun;

/** Occurs before application output. */ 
public $onBeforeOut;

/** Occurs after application output. */ 
public $onAfterOut;

public $plugins;

/**
 * Load config and sessions, read route.
 * @param string $name Unique name of the application.
 */
function __construct($name)
{
	global $pclib;
	parent::__construct();
	$this->name = $name;
	$pclib->app = $this;

	system\BaseObject::defaults('serviceLocator', array($this, 'getService'));

	$this->errorHandler = new system\ErrorHandler;
	$this->errorHandler->register();

	$this->paths = $this->getPaths();

	$this->environmentIp(
		array(
			'127.0.0.1' => 'develop',
			'::1' => 'develop',
			'*' => 'production',
		)
	);

	$this->addConfig( PCLIB_DIR.'Config.php' );
}


function __get($name)
{
	switch($name) {
		case 'controller': return $this->router->action->controller;
		case 'action':   return $this->router->action->method;
		case 'routestr': return $this->router->action->path;
		case 'content':  return $this->layout->values['CONTENT'];
		case 'language': return $this->getLanguage();
	}

	$service = $this->getService($name);
	return $service? $service : parent::__get($name);
}

function __set($name, $value)
{
	switch($name) {
		case 'controller': $this->router->action->controller = $value; return;
		case 'action':  $this->router->action->metod = $value; return;
		case 'content': $this->setContent($value); return;
		case 'language': $this->setLanguage($value); return;
	}
	if ($value instanceof IService) {
		$this->setService($name, $value);
	}
	else {
		throw new Exception("Cannot assign '%s' to App->%s property.", array(gettype($value), $name));
	}
}

/**
 * Set content of the webpage to be displayed.
 * It replaces {CONTENT} placeholder in layout.
 * Call out() for displaying website with content.
 * @param string $content Content placed into layout.
 */
function setContent($content)
{
	if (!$this->layout) throw new NoValueException('Cannot set content: app->layout does not exists.');
	$this->layout->values['CONTENT'] = (string)$content;
}

/**
 * Set layout template of the application.
 * Any page added with function setContent() will be put inside layout template.
 * Example: $app->setLayout('tpl/website.tpl');
 * @param string $path Path to website template.
 */
function setLayout($path)
{
	$this->layout = new Layout($path);
}

/**
 * Store message to log, using application Logger.
 * If application has no Logger service, this method does nothing.
 * For the parameters see Logger::log()
 */
function log($category, $message_id, $message = null, $item_id = null)
{
	$logger = $this->services['logger'];
	if (!$logger) return;
	return $logger->log($category, $message_id, $message, $item_id);
}

/**
 * Return default service object or null, if service must be created by user.
 * @param string $serviceName
 * @return IService $service
 */
protected function createDefaultService($serviceName) {
	$canBeDefault = array('logger', 'debugger', 'request', 'router');
	if (in_array($serviceName, $canBeDefault)) {
		$className = '\\pclib\\'.ucfirst($serviceName);

		return new $className;
	}
	else return null;
}

/**
 * Register application service such as Db or Logger.
 * Services can be accessed and used by other objects.
 * You can access service as `$app->serviceName` e.g. `$app->db->select("table")`.
 * @param IService $service Service object.
 */
function setService($name, IService $service)
{
	$this->services[$name] = $service;
}

function getService($serviceName)
{
	if (isset($this->services[$serviceName])) {
		return $this->services[$serviceName];
	}
	else {
		$service = $this->createDefaultService($serviceName);
		if ($service) {
			$this->setService($serviceName, $service);
			return $service;
		}
	}
	return false;
}

/**
 * Load application configuration.
 * $source must be valid php-file which containing array $config or $config array itself.
 * Can be called more than once - configurations will be merged.
 * Set #$config variable.
 * @param string|array $source Path to configuration file or array of config-parameters.
 */
function addConfig($source)
{
	if (is_array($source)) {
		$config = $source;
	}
	else {
		if (!file_exists($source))
			throw new FileNotFoundException("Configuration file '$source' not found.");
		else
			require $source;
	}

	$this->config = array_replace_recursive($this->config, (array)$config);

	$_env = $this->environment;

	if (is_string($_env) and is_array($$_env)) {
		$this->config = array_replace_recursive($this->config, $$_env);
	}

	$this->configure();
}

function addPlugins($dir)
{
	$this->plugins[] = array();
  foreach (glob($dir.'/*.php') as $fileName) {
    require_once($fileName);
    $pluginName = basename($fileName, '.php');
    $plugin = new $pluginName($this);
    $plugin->init();
   	$this->plugins[] = $plugin;
  }
}

/**
 * Set $app->environment variable by server ip-address.
 * @param array $env Array of ipAddress:environmentName pairs.
 */
function environmentIp(array $env)
{
	$serverIp = $this->request->serverIp;
	foreach ($env as $ip => $environment) {
		if ($ip == '*' or $ip == $serverIp) {
			$this->environment = $environment;
			return;
		}
	}
}

protected function registerDebugBar()
{
	extensions\DebugBar::register();
}

/*
 * Setup application according to its configuration.
 * Called when app->config changed.
 */
public function configure()
{
	global $pclib;
	$this->errorHandler->options = $this->config['pclib.errors'];

	if ($this->config['pclib.logger']['log']) {
		$this->logger->categories = $this->config['pclib.logger']['log'];
	}
	foreach ($this->config['pclib.directories'] as $k => $dir) {
		$this->config['pclib.directories'][$k] = paramstr($dir, $this->paths);
	}
	if ($this->config['pclib.compatibility']['legacy_classnames']) {
		$pclib->autoloader->addAliases($pclib->legacyAliases);
	}
}

/**
 * Perform redirect to $route.
 * Example: $app->redirect("products/edit/id:$id");
 * @param string|array $route
 * @param htttp code (e.g. 301 Moved Permanently)
 * See also @ref pcl-route
 */
function redirect($route, $code = null)
{

	if ($code and function_exists('http_response_code')) {
		http_response_code($code);
	}

	if (is_array($route)) {
		$url = $route['url'];
	}
	else {
		$url = $this->router->createUrl($route);
	}

	header("Location: $url");
	exit();
}

/**
 * Initialize application Translator and enable translation to the $language.
 * You can access current language as $app->language.
 * @param string $language Language code such as 'en' or 'source'.
 * @param bool $useDefault Preload default texts?
 * @param bool $useFile Try load texts from php file?
 */
function setLanguage($language, $useDefault = true, $useFile = true)
{
	$trans = new Translator($this->name);
	$trans->language = $language;
	
	if ($language == 'source') {
		$trans->autoUpdate = true;
	}
	elseif($useFile) {
		$transFile = $this->config['pclib.directories']['localization'].$language.'.php';
		if (file_exists($transFile)) $trans->useFile($transFile);
		else throw new FileNotFoundException("Translator file '$transFile' not found.");
	}

	if ($useDefault) {
		try {
			$trans->usePage('default');
		} catch (Exception $e) {
			throw new Exception('Cannot load texts for translator - '.$e->getMessage());
		}

	}

	$this->setService('translator', $trans);
}

function getLanguage()
{
	if (!$this->services['translator']) return '';
	return $this->services['translator']->language;
}

private function normalizeDir($s)
{
	return rtrim(strtr($s, "\\", "/"),"/");
}

function getPaths()
{
	$webroot = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['SCRIPT_FILENAME']);

	return array(
		'webroot' => $this->normalizeDir($webroot),
		'baseurl' => $this->normalizeDir(dirname($_SERVER['SCRIPT_NAME'])),
		'basedir' => $this->normalizeDir(dirname($_SERVER['SCRIPT_FILENAME'])),
		'pclib' => $this->normalizeDir(substr(PCLIB_DIR, strlen($webroot))),
	);
}

/**
 * Translate string $s.
 * Uses Translator service if present, otherwise return unmodified $s.
 * Example: $app->t('File %%s not found.', $fileName);
 * @param string $s String to be translated.
 * @param mixed $args Variable number of arguments.
 */
function t($s)
{
	$translator = $this->services['translator'];
	if ($translator) $s = $translator->translate($s);
	$args = array_slice(func_get_args(), 1);
	if ($args) {
		if (is_array($args[0])) $args = $args[0];
		$s = vsprintf ($s, $args);
	}
	return $s;
}

/**
 * Display flash message.
 * Layout template must contains messages tag.
 * In message %%s arguments can be used. Messages are also translated with Translator.
 * You can call message() even before redirect.
 * Example: $app->message('File %%s not found', $fileName);
 * @param string $message
 * @param string $cssClass Css-class of the message div
 * @param mixed $args Variable number of message arguments
 */
function message($message, $cssClass = null)
{
	$args = array_slice(func_get_args(), 2);
	$this->layout->addMessage($message, $cssClass, $args);
}

/**
 * Display warning message.
 * @deprecated Use app->message($message, 'warning');
 * @see message()
 **/
function warning($message, $cssClass = null)
{
	$args = array_slice(func_get_args(), 2);
	$this->layout->addMessage($message, $cssClass? $cssClass : 'warning', $args);
}

/**
 * Display error message and exit application.
 * @see message()
 **/
function error($message, $cssClass = null)
{
	$args = array_slice(func_get_args(), 2);
	$message = vsprintf($this->t($message), $args);
	if (!$cssClass) $cssClass = 'error';

	$event = $this->onError($message);
	if ($event and !$event->propagate) return;

	$this->setContent('<div class="'.$cssClass.'">'.$message.'</div>');
	$this->out();
	exit(1);
}

/**
 * Display error message with http response code header and exit application.
 * @see message()
 **/
function httpError($code, $message, $cssClass = null)
{
	if (function_exists('http_response_code')) {
		http_response_code($code);
	}

	$args = array_slice(func_get_args(), 3);
	$message = vsprintf($this->t($message), $args);
	$this->error($message, $cssClass);
}

/**
 * Get application session variable.
 * Session variables are stored in their own namespace $ns.
 * By default it is application name, so sessions for different
 * applications does not collide.
 * Variable name can be plain: 'user' or with group: 'pclib.user'.
 * All system variables uses group 'pclib'.
 * @param string $name Variable name.
 * @param string $ns (optional) Namespace.
 * @return mixed Session variable value.
 **/
function getSession($name, $ns = null)
{
	if (!$ns) $ns = $this->name;
	if (strpos($name, '.')) {
		list($n1,$n2) = explode('.', $name);
		return $_SESSION[$ns][$n1][$n2];
	}
	return $_SESSION[$ns][$name];
}

/**
 * Set application session variable.
 * @see getSession()
 * @param string $name name of session variable
 * @param mixed $value value of variable
 * @param string $ns (optional) Namespace
 **/
function setSession($name, $value, $ns = null)
{
	if (!$ns) $ns = $this->name;
	if (strpos($name, '.')) {
		list($n1,$n2) = explode('.', $name);
		$_SESSION[$ns][$n1][$n2] = $value;
	}
	else {
		$_SESSION[$ns][$name] = $value;
	}
}

/**
 * Delete application session variable.
 * Without parameters, it will delete whole application session.
 * @see getSession()
 * @param string $name name of variable
 * @param string $ns (optional) Namespace
 **/
function deleteSession($name = null, $ns = null)
{
	if (!$ns) $ns = $this->name;
	if (strpos($name, '.')) {
		list($n1,$n2) = explode('.', $name);
		unset($_SESSION[$ns][$n1][$n2]);
	}
	elseif ($name)
		unset($_SESSION[$ns][$name]);
	else
		unset($_SESSION[$ns]);
}

function getClassName($name, $loaderName)
{
	$options = $this->config['pclib.loader'][$loaderName];
	if (!$options) throw new Exception("Loader '%s' is not defined in configuration.", array($loaderName));

	if ($this->config['pclib.compatibility']['legacy_classnames']) {
		$postfix = $options['postfix']? '_'.lcfirst($options['postfix']) : '';
	}
	else {
		$name = ucfirst($name);
		$postfix = $options['postfix'];
	}

	$className = $name.$postfix;

	if($options['dir']) {
		$path = $options['dir'].'/'.$className.'.php';
		if (!file_exists($path)) return $options['default'];
		require_once($path);
	}

	if ($options['namespace']) {
		$className = $options['namespace'].'\\'.$className;
	}

	return $className;
}

function newController($name)
{
	$className = $this->getClassName($name, 'controller');
	return $className? new $className($this) : null;
}

function newModel($name)
{
	return orm\Model::create($name, array(), false);
}

/**
 * Execute method of the controller.
 * Without parameters, route is read from current url.
 * Route 'products/add' means: call method ProductsController->addAction();
 * @param string $rs Route string. See @ref pcl-route
 **/
function run($rs = null)
{
	if ($this->debugMode) $this->registerDebugBar();

	if ($rs) {
		$this->router->action = new Action($rs);
	}
	
	$action = $this->router->action;

	$event = $this->onBeforeRun();
	if ($event and !$event->propagate) return;

	$ct = $this->newController($action->controller);
	if (!$ct) $this->httpError(404, 'Page not found: "%s"', null, $action->controller);

	$html = $ct->run($action);

	$event = $this->onAfterRun();
	if ($event and $event->propagate) return;

	$this->setContent($html);
}

/**
 * Display webpage.
 * Get #$layout template populated with content and display it.
 * You must setup layout and content first.
 * @see setContent(), setLayout()
 **/
function out()
{
	$event = $this->onBeforeOut();
	if ($event and !$event->propagate) return;

	if (!$this->layout) throw new NoValueException('Cannot show output: app->layout does not exists.');
	$this->layout->out();
	$this->onAfterOut();
}

}
