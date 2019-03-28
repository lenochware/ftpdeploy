<?php 
class TextLogger
{
	public $name;
	public $path = 'data/log/';
	protected $file;

	public $output;

	function __construct($name, $options = array())
	{
		$this->name = $name;
		$this->file = fopen($this->path.$name.'.log', "a");

	}

	function __destruct()
	{
		fclose($this->file);
	}

	function getHtmlOutput()
	{
		return nl2br(str_replace('failed', '<span style="color:red;font-weight:bold">failed</span>', $this->output));
	}

	function log($message)
	{
		fwrite($this->file, $message."\n");
		$this->output .= $message."\n";

		if ($options['output'] == 'screen') {
				print "$message<br>";
				flush();
		}
	}

}

?>