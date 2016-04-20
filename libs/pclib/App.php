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

/**
 * Gives global access to web application.
 * It is facade for application services and general datastructures.
 * Features:
 * - application configuration: addConfig()
 * - working with controllers: run()
 * - working with services: setService()
 * - layout: setLayout(), routing, and error handling
 */
class App extends BaseObject
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

/** Current route: [controller,action] array. See also getRoute() , run() */
public $route = array();

/** Current enviroment (such as 'develop','test','production'). See setEnviroment() */
public $enviroment;

/** Enabling debugMode will display debug-toolbar. */
public $debugMode = false;

/* Store bookmarks for breadcrumb navigator. */
public $bookmarks = array();

public $indexFile = 'index.php';

public $CONTROLLER_POSTFIX = 'Controller';
public $MODEL_POSTFIX = 'Model';

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

	BaseObject::defaults('serviceLocator', array($this, 'getService'));

	$this->errorHandler = new ErrorHandler;
	$this->errorHandler->register();

	$this->paths = $this->getPaths();
	$this->addConfig( PCLIB_DIR.'Config.php' );
	$this->route = $_GET['r']? $this->getRoute() : $this->getRoute_Old();
	$this->loadSession();

}


function __get($name)
{
	switch($name) {
		case 'controller': return $this->route[0];
		case 'action':   return $this->route[1];
		case 'routestr': return implode('/', $this->route);
		case 'content':  return $this->layout->values['CONTENT'];
		case 'language': return $this->getLanguage();
	}

	$service = $this->getService($name);
	return $service? $service : parent::__get($name);
}

function __set($name, $value)
{
	switch($name) {
		case 'controller': $this->route[0] = $value; return;
		case 'action':  $this->route[1] = $value; return;
		case 'content': $this->setContent($value); return;
		case 'language': $this->setLanguage($value); return;
	}
	if ($value instanceof IService) {
		$this->setService($name, $value);
	}
	else {
		throw new Exception('Cannot assign '.gettype($value).' to App->'.$name.' property.');
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
	$this->layout = new App_Layout($path);
}

/**
 * Set $app->enviroment variable, if any url pattern in $enviroments will
 * match current url. You can use wildcards.
 * Example: $app->setEnviroment(['http://localhost' => 'develop', '*' => 'production']);
 * @param array $enviroments Array of [pattern => enviroment] pairs.
 */
function setEnviroment(array $enviroments)
{
	foreach($enviroments as $pattern => $name) {
		if ($this->request->urlmatch($pattern)) {
			$this->enviroment = $name;
			return $this->enviroment;
		}
	}
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
 * Return Debugger object, create it, if not exists.
 */
function getDebugger()
{
	if (!$this->services['debugger']) $this->setService('debugger', new Debugger);
	return $this->services['debugger'];
}

/**
 * Return Request object, create it, if not exists.
 */
function getRequest()
{
	if (!$this->services['request']) $this->setService('request', new Request);
	return $this->services['request'];
}

/**
 * Return Logger object, create it, if not exists.
 */
function getLogger($options = array())
{
	if (!$this->services['logger']) {
		$this->setService('logger', new Logger);
	}
	if ($options) {
		$this->services['logger']->categories = $options['log'];
	}

	return $this->services['logger'];
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
	if ($serviceName == 'request') return $this->getRequest();
	if ($serviceName == 'debugger') return $this->getDebugger();
	if ($serviceName == 'logger') return $this->getLogger();
	return $this->services[$serviceName];
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

	$this->config = array_merge($this->config, (array)$config);

	if (is_array($enviroment))  {
		$var = $this->setEnviroment($enviroment);
		$this->config = array_merge($this->config, (array)$$var);
	}

	$this->configure();
}

protected function registerDebugBar()
{
	require_once PCLIB_DIR . 'extensions/DebugBar.php';
	$debugbar = new DebugBar;
	$debugbar->register();
}

/*
 * Setup application according to its configuration.
 * Called when app->config changed.
 */
public function configure()
{
	$this->errorHandler->options = $this->config['pclib.errors'];
	$underscore = $this->config['pclib.compatibility']['controller_underscore_postfixes']? '_' : '';
	$this->CONTROLLER_POSTFIX = $underscore.'Controller';
	$this->MODEL_POSTFIX = $underscore.'Model';

	if ($this->config['pclib.logger']) {
		$this->getLogger($this->config['pclib.logger']);
	}
	foreach ($this->config['pclib.directories'] as $k => $dir) {
		$this->config['pclib.directories'][$k] = paramstr($dir, $this->paths);
	}
	//$this->events->run($this, 'pclib.app.onconfigure');
}

/**
 * Perform redirect to $route.
 * Example: $app->redirect("products/edit/id:$id");
 * See also @ref pcl-route
 */
function redirect($route)
{
	$this->saveSession();
	$url = $this->getUrl($route);
	header("Location: $url");
	exit();
}

/** Load application state from session. */
protected function loadSession()
{
	$this->bookmarks = $this->getSession('pclib.bookmarks');
}

/** Save application state to session. */
protected function saveSession()
{
	if (isset($this->bookmarks))
		$this->setSession('pclib.bookmarks', $this->bookmarks);
}

/**
 * Initialize application Translator and enable translation to the $language.
 * You can access current language as $app->language.
 * @param string $language Language code such as 'en' or 'source'.
 * @param bool $useDefault Preload default texts?
 */
function setLanguage($language, $useDefault = true)
{
	$trans = new Translator($this->name);
	$trans->language = $language;
	$transFile = $this->config['pclib.directories']['localization'].$language.'.php';
	if (file_exists($transFile)) $trans->useFile($transFile);
	else throw new FileNotFoundException("Translator file '$transFile' not found.");
	if ($useDefault) $trans->usePage('default');
	if ($language == 'source') $trans->autoUpdate = true;
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
		'pclib' 	=> $this->normalizeDir(substr(PCLIB_DIR, strlen($webroot))),
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

/*
 * Bookmark (store in session) current URL as $title.
 * Next you can build breadcrumb navigator from bookmarked url adresses.
 * Ex: app->bookmark(1, 'Main page'); app->bookmark(2, 'Subpage');
 * @see getNavig()
 *
 * @param string $level Level of this item in history/breadcrumb tree.
 * @param string $title Label of the link shown in navigator
 * @param string $route If set, it will bookmark this route instead of current url
 * @param string $url If set, it will bookmark this url instead of current url
 */
function bookmark($level, $title, $route = null, $url = null)
{
	if ($route) list($temp, $url) = explode('?', $this->getUrl($route));

	$maxlevel =& $this->bookmarks[-1]['maxlevel'];
	for ($i = $maxlevel; $i > $level; $i--) { unset($this->bookmarks[$i]); }
	$maxlevel = $level;

	$this->bookmarks[$level]['url'] = isset($url)? $url : $_SERVER['QUERY_STRING'];
	$this->bookmarks[$level]['title'] = $title;
}

/*
 * Return HTML (breadcrumb) navigator: bookmark1 / bookmark2 / bookmark3 ...
 * It is generated from bookmarked pages.
 * @see bookmark()
 * @param string $separ link separator
 * @param bool $lastLink current page is link in navigator
 */
function getNavig($separ = ' / ', $lastLink = false)
{
	$maxlevel = $this->bookmarks[-1]['maxlevel'];
	for($i = 0; $i <= $maxlevel; $i++) {
		$url   = $this->bookmarks[$i]['url'];
		$title = $this->bookmarks[$i]['title'];
		$alt = '';
		if (!$title) continue;

		if (utf8_strlen($title) > 30) {
			$alt = 'title="'.$title.'"';
			$title = utf8_substr($title, 0, 30). '...';
		}

		if ($i == $maxlevel and !$lastLink)
			$navig[] = "<span $alt>$title</span>";
		else
			$navig[] = "<a href=\"".$this->indexFile."?$url\" $alt>$title</a>";

	}
	return implode($separ, (array)$navig);
}

/**
 * Return App_Controller object.
 * @param string $name Name of the controller's class without postfix
 **/
function getController($name)
{
	$className = ucfirst($name).$this->CONTROLLER_POSTFIX;

	$dir = $this->config['pclib.directories']['controllers'];

	$searchPaths = array(
		$dir.$className.'.php', 
		$dir.strtolower($className).'.php',
		$dir.$name.'.php',
	);

	while ($path = array_shift($searchPaths)) {
		if (file_exists($path)) break;
		if (!$searchPaths) return null;
	}

	require_once($path);
	$controller = new $className($this);
	return $controller;
}

/**
 * Return Model object.
 * @param string $name Name of the Model's class without postfix
 **/
function getModel($name)
{
	$className = $name.$this->MODEL_POSTFIX;
	$dir = $this->config['pclib.directories']['models'];
	$path = $dir.$className.'.php';

	require_once($path);
	$model = new $className($this);
	return $model;
}

/**
 * Read current URL and return route [controller,action].
 * Override for your own route format. By default it takes parameter 'r'
 * .i.e. url '?r=orders/add' means call method add() of Orders_Controller.
 */
function getRoute()
{
	$route = explode('/', $_GET['r']);

	//%form button has been pressed, set route accordingly.
	if ($_REQUEST['pcl_form_submit'])
		$route[1] = $_REQUEST['pcl_form_submit'];
	return $route;
}

//Support of old url format for backward compatibility.
function getRoute_Old()
{
	$module = $_GET['module']? $_GET['module'] : $_GET['modul'];
	$action = $_GET['action'];
	$id     = $_GET['id'];

	if ($_REQUEST['pcl_form_submit'])
		$action = $_REQUEST['pcl_form_submit'];

	return array($module, $action, $id);
}

/**
 * Translate internal route to proper url.
 * Used when links in templates are generated or for redirection.
 * @param string|array $ra route 'ctrl/action/param:param' or array('route'=>...,'params=>...)
 */
function getUrl($ra)
{
	if (!$ra) return '';
	if (is_string($ra)) $ra = $this->splitRoute($this->replaceParams($ra));

	$index = BASE_URL.$this->indexFile;
	if ($ra['route']) {
		$ra['params'] = array('r' => implode('/', $ra['route'])) + (array)$ra['params'];
	}
	$spar = pcl_build_query($ra['params']);
	return $index.($spar? '?'.$spar:'');
}

function splitRoute($rs)
{
	if (!$rs) return array();
	$ra = array('route' => array(), 'params' => array());

	foreach(explode('/', $rs) as $section) {
		if ($section == '__GET__') {$ra['params'] += $_GET; continue;}
		list($name,$value) = explode(':', $section);
		if (isset($value)) $ra['params'][$name] = $value;
		else $ra['route'][] = $section;
	}
	return $ra;
}

protected function replaceParams($s)
{
	if (strpos($s,'{') !== false) $s = preg_replace_callback (
		"/{([a-z0-9_.]+)}/i", array($this, 'callback_getvalue'), $s
	);
	return $s;
}

private function callback_getvalue($param)
{
	list($id,$sub) = explode('.', $param[1]);
	if ($id == 'GET') {
		if ($sub) return $_GET[$sub];
		else return '__GET__';
	}
	else return null;
}

/**
 * Execute method of the controller.
 * Without parameters, route is read from current url - i.e. from #$route variable.
 * Route ['products','add'] means: call method Products_Controller->add_action();
 * @param string $rs Route string. See @ref pcl-route
 **/
function run($rs = null)
{
	if ($this->debugMode) $this->registerDebugBar();

	if ($rs) {
		$ra = $this->splitRoute($rs);
		$this->route = $ra['route'];
		$params = $ra['params'];
	}
	else
		$params = $_GET;

	$event = $this->onBeforeRun();
	if ($event and !$event->propagate) return;

	$ct = $this->getController($this->controller);
	if (!$ct) $this->error('Page not found: "%s"', null, $this->controller);

	$html = $ct->run($this->action, $params);

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
	$this->saveSession();
	$this->onAfterOut();
}

}
