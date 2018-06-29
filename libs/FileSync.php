<?php

/**
 * Copy files from local directory to remote (ftp).
 */
class FileSync
{
	public $remote;
	public $errors;

	/** Occurs before copying a file. */ 
	public $onBeforeCopy;

	/** Occurs after copying a file. */ 
	public $onAfterCopy;

	/** Filter for list of files. Called for each file, return true/false. */ 
	public $onFilter;

	/**
	 * Connect to remote server.
	 * @param string $uri Example: 'ftp://user:password@ftp.host.com/path/to/files'
	 */
	function connect($uri)
	{
		$datasource = parse_url($uri);
		if ($datasource['query']) {
			parse_str($datasource['query'], $datasource['options']); 
		}
		$datasource['path'] = $this->normalizeDir($datasource['path']);

		switch ($datasource['scheme']) {
			case 'ftp':
				$this->remote = new FtpDriver($datasource);
				break;

			/*case 'file':
				$this->remote = new LocalDriver($datasource);
				break;*/
			
			default:
				throw new Exception("Unknown datasource type '$uri'.");
				break;
		}
	}

	/**
	 * Disconnect from the remote server.
	 */
	function disconnect()
	{
		$this->remote->disconnect();
		$this->remote = null;
	}

	/**
	 * Copy multiple files to remote.
	 * @param array $files [files: arrayOfFileNames, sourcedir: source-root-directory]
	 * @param array $options Options
	 */
	function remoteCopy($files, $options = array())
	{
		set_time_limit(0);
		$this->errors = array();

		foreach ($files['files'] as $fileName) {
			$this->fireEvent('onBeforeCopy', array($fileName));
			$ok = $this->remote->copyFile(
				$files['sourcedir'].'/'.$fileName, $fileName
			);
			$this->fireEvent('onAfterCopy', array($fileName, $ok));
			if (!$ok) $this->errors[] = "Copy of '$fileName' failed.";
		}
	}

	/**
	 * Copy file to remote.
	 * @param string $from Local path to original file
	 * @param string $to Remote path to file
	 */
	function copyFile($from, $to)
	{
		return $this->remote->copyFile($from, $to);
	}

	/**
	 * Delete file on remote.
	 * @param string $fileName Remote path to file
	 */
	function deleteFile($fileName)
	{
		return $this->remote->deleteFile($fileName);
	}

	/**
	 * Return list of files from specified directory and subdirectories (by default).
	 * It will apply filter criteria on returned list.
	 */
	function getList($directory, $options = array())
	{
		$recursive = isset($options['recursive'])? $options['recursive'] : true;
		$directory = $this->normalizeDir($directory).'/';

		if ($recursive) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($directory)
			);
		}
		else {
			$it = new DirectoryIterator($directory);
		}

		$files = array(
			'sourcedir' => $directory,
			'files' => array(),
		);

		foreach ($it as $file) {
			if (!$this->filterFunc($file, $options)) continue;
			$files['files'][] = str_replace($directory, '', $this->getPath($file));
		}

		return $files;
	}
	
	protected function filterFunc(SplFileInfo $file, $options)
	{
		if ($this->onFilter) {
			return $this->fireEvent('onFilter', array($file, $options));
		}
		else {
			return $this->defaultFilter($file, $options);
		}
	}

	/**
	 * Default include/exclude filter.
	 * Called for each file of the list, return true if file match criteria.
	 * By default it uses include/exclude criteria from project configuration file.
	 * @return boolean Pass filter
	 */
	public function defaultFilter(SplFileInfo $file, $options)
	{
		$include = $options['include'];
		$exclude = $options['exclude'];
		$tmin = $options['modified-after'];

		if ($exclude and $this->inPattern($exclude, $this->getPath($file))) return false;
		if ($include and !$this->inPattern($include, $this->getPath($file))) return false;
		if ($tmin and $file->getMTime() < $tmin) return false;
		
		return $file->isFile();
	}

	private function getPath(SplFileInfo $file)
	{
		return $this->normalizeDir($file->getPathname());
	}

	private function inPattern($patterns, $path)
	{
		foreach ($patterns as $pattern) {
			if (fnmatch($pattern, $path)) return true;
		}
		return false;
	}

	private function normalizeDir($dir)
	{
		return rtrim(str_replace("\\", "/", $dir), '/');
	}

	protected function fireEvent($name, array $params = array())
	{
		if (!is_callable($this->$name)) return;
		return call_user_func($this->$name, $this, $params);
	}

  function getFile($filePath)
  {
      return $this->remote->getFile($filePath);
  }

} //FileSync

/**
 * Driver for working with remote filesystem using ftp.
 */
class FtpDriver
{
	protected $connection;
	protected $rootDir;

	function __construct($datasource)
	{
		$this->connect($datasource);
	}

	function connect($datasource)
	{
		$this->rootDir = $datasource['path'];
		$this->connection = @ftp_connect($datasource['host']);

		$ok = @ftp_login($this->connection, $datasource['user'], $datasource['pass']);
			
		if ($datasource['options']['passive']) {
			ftp_pasv($this->connection, true);
		}
		
		if (!$ok) throw new Exception('FTP connection failed.');
	}

	function disconnect()
	{
		ftp_close($this->connection);
	}

	function copyFile($from, $to)
	{
		$remoteDir = pathinfo($to, PATHINFO_DIRNAME);
		if (!$this->isDir($remoteDir)) {
			$this->mkDir($remoteDir);
		}

		$ok = @ftp_put($this->connection, $this->rootDir.'/'.$to, $from, FTP_BINARY);

		//if (!$ok) $this->errors[] = "Copy of '$fileName' failed.";
		return $ok;
	}

	function deleteFile($fileName)
	{
		return ftp_delete($this->connection, $this->rootDir.'/'.$fileName);
	}

	public function mkDir($directory)
	{
   @ftp_chdir($this->connection, $this->rootDir);
   $parts = explode('/',$directory);
   foreach($parts as $part) {
      if(!@ftp_chdir($this->connection, $part)) {
         ftp_mkdir($this->connection, $part);
         ftp_chdir($this->connection, $part);
         //ftp_chmod($ftpcon, 0777, $part);
      }
   }
 }

	public function isDir($directory) {
		return @ftp_chdir($this->connection, $this->rootDir.'/'.$directory);
	}

   
  public function getFile($filePath)
  {
      @ftp_chdir($this->connection, $this->rootDir);
      ob_start();
      $result = @ftp_get($this->connection, 'php://output', $this->rootDir.'/'.$filePath, FTP_BINARY);
      $data = ob_get_contents();
      ob_end_clean();
      return $data;
  }


} //FtpDriver

/*
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
		$this->rootDir = $datasource['path'];
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


	public function mkDir($directory)
	{
		mkdir($this->rootDir.'/'.$directory, 0777, true);
  }

	public function isDir($directory) {
		return is_dir($this->rootDir.'/'.$directory);
	}

} //LocalDriver
*/
?>