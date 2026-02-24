<?php

use App\Dto\SessionContext;
use Symfony\Component\Console\Output\BufferedOutput;

test('construction sets readonly properties', function() {
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
		base_branch: 'main',
	);

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
		base_branch: 'main',
	);

	expect($context->vm_name)->toBeNull()
		->and($context->vm_ip)->toBeNull()
		->and($context->worktree_path)->toBeNull()
		->and($context->worktree_branch)->toBeNull()
		->and($context->proxy_name)->toBeNull()
		->and($context->tunnel_port)->toBeNull()
		->and($context->tunnel_process)->toBeNull()
		->and($context->services)->toBeNull()
		->and($context->on_exit)->toBeNull();
});

test('mutable properties can be set', function() {
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
		base_branch: 'main',
	);

	$context->vm_name = 'clave-abc123';
	$context->vm_ip = '192.168.64.5';

	expect($context->vm_name)->toBe('clave-abc123')
		->and($context->vm_ip)->toBe('192.168.64.5');
});

test('status writes to output', function() {
	$output = new BufferedOutput();
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
		base_branch: 'main',
		output: $output,
	);

	$context->status('Hello world');

	expect($output->fetch())->toContain('Hello world');
});

test('status is safe without output', function() {
	$context = new SessionContext(
		session_id: 'abc123',
		project_name: 'my-app',
		project_dir: '/path/to/app',
		base_branch: 'main',
	);

	$context->status('No output set');

	expect(true)->toBeTrue();
});
