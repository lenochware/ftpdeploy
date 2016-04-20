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