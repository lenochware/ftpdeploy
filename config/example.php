<?php

return array(
	'local' => '../project-directory',
	'remote' => 'ftp://user:password@ftp.example.com/www/project',
	'password' => 'test',
	'exclude' => array(
		'*/.git/*', 
		'*/.*', 
		'*.sql',
		'*.bak',
		'*/temp/*',
	),
);

?>