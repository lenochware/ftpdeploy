<?php 

/**
 * Driver for working with remote filesystem using ftp.
 */
class SftpDriver
{
	protected $connection;
	protected $sftp;
	protected $rootDir;

	function __construct($datasource)
	{
		if (!extension_loaded('ssh2')) {
			die('Required PHP extension "ssh2" is not loaded.');
		}

		$this->connect($datasource);
	}

	function connect($datasource)
	{
		$this->rootDir = $datasource['path'];
		$this->connection = ssh2_connect($datasource['host'], $datasource['port'] ?: 22);

		if (!ssh2_auth_password($this->connection, $datasource['user'], $datasource['pass'])) {
		  throw new Exception('SFTP authentication failed.');
		}

		$this->sftp = ssh2_sftp($this->connection);

		if (!$this->sftp) {
		  throw new Exception('FTP connection failed.');
		}
	}

	function disconnect()
	{
		ssh2_disconnect($this->connection);
	}

	function copyFile($from, $to)
	{
		$remoteDir = pathinfo($to, PATHINFO_DIRNAME);
		if (!$this->isDir($remoteDir)) {
			$this->mkDir($remoteDir);
		}

		$ok = ssh2_scp_send($this->connection, $from, $this->rootDir.'/'.$to);

		return $ok;
	}

	function deleteFile($fileName)
	{
		return unlink('ssh2.sftp://' . $this->sftp . $this->rootDir.'/'.$fileName);
	}

	public function mkDir($directory)
	{
		mkdir('ssh2.sftp://' . $this->sftp . $this->rootDir.'/'.$directory, 0755, true);
 	}

	public function isDir($directory) {
		return is_dir('ssh2.sftp://' . $this->sftp . $this->rootDir.'/'.$directory);
	}

   
	public function getFile($filePath)
	{
		$uri = 'ssh2.sftp://' . $this->sftp . $this->rootDir.'/'.$filePath;

		if (!file_exists($uri)) return '';

		$stream = fopen($uri, 'r');
		if (!$stream) return '';
		$data = stream_get_contents($stream);
		fclose($stream);

    return $data;
  }

} 

?>