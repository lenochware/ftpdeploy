<?php

return [
	'local' => '../project-directory',
	'remote' => 'ftp://user:password@ftp.example.com/www/project',
	'password' => 'choose password',
	'exclude' => [
		'~/.git', 
		'~/temp',
		'*/.*', 
		'*.bak',
	],
];

?>