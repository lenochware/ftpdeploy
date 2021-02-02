# Ftp-Deploy
Deploy your project on the production server, using ftp.

See the differences on your development server and upload
changes to the production with one click.

### Features

* You have preview of modified files and can choose which files
 do you want upload.
* Each deploy is stored in the log file alongside with your comment.
* It can be extended with another remote drivers providing 
for example SFTP access.
* You can specify include/exclude files pattern in configuration

### Example of configuration file `config/myproject.php`

```php
return array(
	'local' => '/local/path/to/files',
	'remote' => 'ftp://user:password@ftp.host.com/path/to/files',
	'password' => 'your-login',
	'charset' => 'source-code-charset' /*optional*/
	'exclude' => array(
		'*/.git/*', 
		'*/.*', 
		'*.bak',
		'*/temp/*',
	),
);
```

### Installation

* Copy ftp-deploy somewhere at your development server
* Create file config/your-project.php (see example above)
* Directory ftp-deploy/data must be writeable
* Run ftp-deploy

### Requirements

* php 5.6
