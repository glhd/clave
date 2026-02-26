<?php

use App\Data\SessionContext;
use Illuminate\Console\OutputStyle;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

test('construction sets readonly properties', function() {
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
	);
	$context->base_branch = 'main';

	expect($context->session_id)->toBe('abc123')
		->and($context->project_name)->toBe('my-app')
		->and($context->project_dir)->toBe('/path/to/app')
		->and($context->base_branch)->toBe('main');
});

test('mutable properties default to null', function() {
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
	);

	expect($context->vm_name)->toBeNull()
		->and($context->vm_ip)->toBeNull()
		->and($context->clone_path)->toBeNull()
		->and($context->clone_branch)->toBeNull()
		->and($context->proxy_name)->toBeNull()
		->and($context->services)->toBeNull()
		->and($context->on_exit)->toBeNull()
		->and($context->tunnel_ports)->toBe([80, 8080, 3306, 6379])
		->and($context->tunnel_process)->toBeNull();
});

test('mutable properties can be set', function() {
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
	);

	$context->vm_name = 'clave-abc123';
	$context->vm_ip = '192.168.64.5';

	expect($context->vm_name)->toBe('clave-abc123')
		->and($context->vm_ip)->toBe('192.168.64.5');
});
