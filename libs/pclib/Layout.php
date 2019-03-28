<?php

namespace pclib;
use pclib;

/**
 * Template extended with 'head' tag, allowing add links to *.js and *.css files.
 * Moreover it contains support of flash messages.
 * @see App::setLayout()
 */
class Layout extends Tpl
{

/* Store bookmarks for breadcrumb navigator. */
public $bookmarks = array();

protected $headTag;
protected $messagesTag;
public $MESSAGE_PATTERN = '<div class="%s">%s</div>';

/** Load application state from session. */
function loadSession()
{
	$this->bookmarks = $this->app->getSession('pclib.bookmarks');
}

/** Save application state to session. */
function saveSession()
{
	if (isset($this->bookmarks))
		$this->app->setSession('pclib.bookmarks', $this->bookmarks);
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
	if ($route) $url = $this->app->router->createUrl($route);

	$maxlevel =& $this->bookmarks[-1]['maxlevel'];
	for ($i = $maxlevel; $i > $level; $i--) { unset($this->bookmarks[$i]); }
	$maxlevel = $level;

	$this->bookmarks[$level]['url'] = isset($url)? $url : $_SERVER['REQUEST_URI'];
	$this->bookmarks[$level]['title'] = $title;
}

/*
 * Return HTML (breadcrumb) navigator: bookmark1 / bookmark2 / bookmark3 ...
 * It is generated from bookmarked pages.
 * @see bookmark()
 * @param array $options separ: link separator, lastLink: current page is a link, ul: generate UL/LI
 */
function getNavig($options = array())
{
	$default = array('separ' => ' / ', 'lastlink' => false, 'ul' => false);
	$options += $default;

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

		if ($i == $maxlevel and !$options['lastlink'])
			$navig[] = "<span $alt>$title</span>";
		else
			$navig[] = "<a href=\"$url\" $alt>$title</a>";

	}

	if ($options['ul']) {
		return "<li>".implode('</li><li>', (array)$navig)."</li>";
	}
	else {
		return implode($options['separ'], (array)$navig);
	}
}

/**
 * Add links to *.css, *.js scripts into template.
 * Template must contains a head tag.
 * Example: $app->layout->addScripts('js/jquery.js', 'css/bootstrap.css');
 * @param array|variable_number_of_arguments List of paths to css and js files
 */
public function addScripts()
{
	$id = $this->headTag;
	if (!$id) throw new NoValueException('Missing "head" tag in template.');
	$scripts = func_get_args();
	if (is_array($scripts[0])) $scripts = $scripts[0];
	
	if (is_array($this->values[$id])) {
		$this->values[$id] = array_merge($this->values[$id], $scripts);
	}
	else $this->values[$id] = $scripts;
}

function addInline($s) 
{
	if (!$this->headTag) throw new NoValueException('Missing "head" tag in template.');
	$this->elements[$this->headTag]['inline'] .= $s;
}

/**
 * Add flash (session stored) message.
 * Template must contains a messages tag.
 * @param string $message
 * @param string $cssClass Css-class of the message div
 * @param mixed $args Variable number of message arguments
 */
public function addMessage($message, $cssClass = null, $params = array())
{
	if (!session_id()) throw new RuntimeException('Session is required.');
	if (!$this->messagesTag) throw new NoValueException('Missing "messages" tag in template.');
	if (!$cssClass) $cssClass = 'message';
	$flash = $this->app->getSession('pclib.flash');
	$flash[$cssClass][] = $this->app->t($message, $params);
	$this->app->setSession('pclib.flash', $flash);
}

/**
 * Print content of webpage HEAD section.
 * @copydoc tag-handler
 */
function print_Head($id, $sub, $value)
{
	$scripts = array();
	if ($this->elements[$id]['scripts']) {
		$scripts = array_merge($scripts, explode(',', $this->elements[$id]['scripts']));
	}
	if ($value) {
		$scripts = array_merge($scripts, (array)$value);
	}

	foreach(array_unique($scripts) as $script) {
		if (!file_exists($script)) {
			throw new FileNotFoundException("File '$script' not found.");
		}
		
		$version = $this->elements[$id]['noversion']? '' : '?v='.filemtime($script);
		$ext = substr($script, strrpos($script, '.'));
		if ($script{0} != '/') $script = BASE_URL.$script;
		switch($ext) {
		case '.js': print "<script language=\"JavaScript\" src=\"$script$version\"></script>\n"; break;
		case '.css': print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$script$version\">\n"; break;
		}
	}

	$inline = $this->elements[$id]['inline'];
	if ($inline) print $inline;
}

/**
 * Print flash messages.
 * @copydoc tag-handler
 */
function print_Messages($id, $sub, $value)
{
	$flash = $this->app->getSession('pclib.flash');
	if (!$flash) return;
	foreach ($flash as $cssClass => $messages) {
		print sprintf($this->MESSAGE_PATTERN, $cssClass, implode('<br>', $messages));
	}
	$this->app->setSession('pclib.flash', null);
}

/**
 * Print breadcrumb navigator.
 * @copydoc tag-handler
 */
function print_Navigator($id, $sub, $value)
{
	print $this->getNavig($this->elements[$id]);
}

function print_Element($id, $sub, $value)
{
	switch($this->elements[$id]["type"]) {
		case 'meta':
		case 'head':
			$this->print_Head($id,$sub,$value);
			break;
		case 'messages':
			$this->print_Messages($id,$sub,$value);
			break;
		case 'navigator':
			$this->print_Navigator($id,$sub,$value);
			break;
		default:
			parent::print_Element($id, $sub, $value);
			break;
	}
}

protected function _init()
{
	parent::_init();
	$typelist = $this->elements['pcl_document']['typelist'];
	$this->headTag = $typelist['head'];
	$this->messagesTag = $typelist['messages'];
}

protected function _out($block = null)
{
	parent::_out($block);
	$this->saveSession();
}

}