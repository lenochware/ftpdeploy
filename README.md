# Ftp-Deploy
Deploy your project on the production server, using FTP (SFTP, FTPS).

Show differences between development and production server and upload
changes with one click.

### Features

* Preview of changes (diff between deployment and production version)
* Choose files to deploy or deploy everything
* Deployment over ftp, sftp, ftps protocols or to local filesystem
* You can include/exclude files for deployment in your project

### Example of configuration file `config/myproject.php`

```php
return [
	'local' => '/local/path/to/files',
	'remote' => 'ftp://user:password@ftp.host.com/path/to/files',
	'password' => 'your-login',
	'charset' => 'source-code-charset' /* optional */
	'exclude' => [
		'~/.git',     /* ~ is project directory */
		'~/temp',
		'*.bak',
	],
];
```

### Installation

Install it using composer:

	composer create-project lenochware/ftpdeploy

or download from github

* Create file ftp-deploy/config/your-project.php (see example above)
* Directory ftp-deploy/data must be writeable
* Run ftp-deploy

### Requirements

* php 7.0
