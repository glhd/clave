<?php

return [
	'base_image' => env('CLAVE_BASE_IMAGE', 'ghcr.io/cirruslabs/ubuntu:latest'),
	'base_vm' => env('CLAVE_BASE_VM', 'clave-base'),

	'vm' => [
		'cpus' => env('CLAVE_VM_CPUS', 4),
		'memory' => env('CLAVE_VM_MEMORY', 8192),
		'display' => env('CLAVE_VM_DISPLAY', 'none'),
	],

	'ssh' => [
		'user' => env('CLAVE_SSH_USER', 'admin'),
		'port' => env('CLAVE_SSH_PORT', 22),
		'password' => env('CLAVE_SSH_PASSWORD', 'admin'),
		'options' => [
			'StrictHostKeyChecking' => 'no',
			'UserKnownHostsFile' => '/dev/null',
			'LogLevel' => 'ERROR',
			'ConnectTimeout' => '5',
		],
	],

	'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
];
