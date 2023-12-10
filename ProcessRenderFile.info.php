<?php namespace ProcessWire;

$info = array(
	'title' => 'Process Render File',
	'summary' => 'A Process module that renders markup from a file at an equivalent URL segment.',
	'version' => '0.1.0',
	'author' => 'Robin Sallis',
	'href' => 'https://github.com/Toutouwai/ProcessRenderFile',
	'icon' => 'code',
	'requires' => 'ProcessWire>=3.0.0, PHP>=5.4.0',
	'page' => array(
		'name' => 'prf',
		'title' => 'Render File',
		'parent' => 'setup',
	),
	'permission' => 'process-render-file',
	'permissions' => array(
		'process-render-file' => 'Use the Process Render File module'
	),
	'useNavJSON' => true,
);
