# Ftp-Deploy
Deploy your project on the production server, using ftp.

See the differences between development and production server and upload
changes with one click.

### Features

* Preview of modified files, select which files do you want upload
* Deploy log with your comment
* Support deploy over ftp, sftp, ftps and on local filesystem
* You can specify include/exclude files pattern in configuration

### Example of configuration file `config/myproject.php`

```php
return [
	'local' => '/local/path/to/files',
	'remote' => 'ftp://user:password@ftp.host.com/path/to/files',
	'password' => 'your-login',
	'charset' => 'source-code-charset' /*optional*/
	'exclude' => [
		'~/.git',
		'~/temp',
		'*.bak',
	],
];
```

### Installation

* Copy ftp-deploy somewhere at your development server
* Create file config/your-project.php (see example above)
* Directory ftp-deploy/data must be writeable
* Run ftp-deploy

### Requirements

* php 7.0
