<?php 

use pclib\Str;

class LocalDriver
{
	protected $connection;
	protected $rootDir;

	function __construct($datasource)
	{
		$this->connect($datasource);
	}

	function connect($datasource)
	{
		$drive = Str::match($datasource['host'], '/(\w)_drive/');
		$this->rootDir = ($drive? $drive.':':'') . $datasource['path'];
		if (!file_exists($this->rootDir)) {
			throw new Exception("Cannot found path '$this->rootDir'");
		}
	}

	function disconnect() {}

	function copyFile($from, $to)
	{
		$ok = @copy($from, $this->rootDir.'/'.$to);
		return $ok;
	}

	function deleteFile($fileName)
	{
		return unlink($this->rootDir.'/'.$fileName);
	}


	public function mkDir($directory)
	{
		mkdir($this->rootDir.'/'.$directory, 0777, true);
  }

	public function isDir($directory) {
		return is_dir($this->rootDir.'/'.$directory);
	}

  public function getFile($fileName)
  {
  	if (!file_exists($this->rootDir.'/'.$fileName)) return '';
    return file_get_contents($this->rootDir.'/'.$fileName);
  }	

} //LocalDriver

 ?>