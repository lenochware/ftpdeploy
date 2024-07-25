<?php 
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

		if ($datasource['scheme'] == 'ftps') {
			$this->connection = ftp_ssl_connect($datasource['host']);
		}
		else {
			$this->connection = @ftp_connect($datasource['host']);
		}

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
      
      if (ftp_size($this->connection, $this->rootDir.'/'.$filePath) == -1) {
      	return '';
      }

      ob_start();
      $result = @ftp_get($this->connection, 'php://output', $this->rootDir.'/'.$filePath, FTP_BINARY);
      $data = ob_get_contents();
      ob_end_clean();
      return $data;
  }

}

?>