<?php

use App\Data\SessionContext;
use App\Pipelines\Steps\ResolveVm;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function makeResolveVmContext(bool $fresh = false): SessionContext
{
	return new SessionContext(
		session_id: 'test-sess',
		project_name: 'my-project',
		project_dir: '/path/to/my-project',
		fresh: $fresh,
	);
}

function fakeListOutput(string $vm_name, string $state): string
{
	return json_encode([['name' => $vm_name, 'state' => $state]]);
}

function expectedVmName(): string
{
	return SessionContext::vmNameForProject('/path/to/my-project');
}

test('clones fresh VM when no existing VM found', function() {
	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: '[]'),
		default => Process::result(),
	});

	$step = app(ResolveVm::class);
	$context = makeResolveVmContext();

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->vm_name)->toBe(expectedVmName());
	expect($context->resumed)->toBeFalse();
	expect($context->ephemeral)->toBeFalse();

	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'clone', $context->project_config->baseVmName(), expectedVmName()]);
	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'set', expectedVmName(), '--random-mac']);
});

test('resumes suspended VM when base_vm matches', function() {
	$vm_name = expectedVmName();
	$context = makeResolveVmContext();
	$base_vm = $context->project_config->baseVmName();

	$home = $_SERVER['HOME'] ?? getenv('HOME');
	$metadata_dir = "{$home}/.config/clave/vms";
	$metadata_path = "{$metadata_dir}/{$vm_name}.json";

	if (! is_dir($metadata_dir)) {
		mkdir($metadata_dir, 0755, true);
	}
	file_put_contents($metadata_path, json_encode(['base_vm' => $base_vm, 'project_dir' => '/path/to/my-project']));

	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: fakeListOutput($vm_name, 'suspended')),
		default => Process::result(),
	});

	$step = app(ResolveVm::class);

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->vm_name)->toBe($vm_name);
	expect($context->resumed)->toBeTrue();
	expect($context->ephemeral)->toBeFalse();

	Process::assertNotRan(fn(PendingProcess $p) => $p->command[1] === 'clone');

	@unlink($metadata_path);
});

test('falls back to ephemeral when VM is running', function() {
	$vm_name = expectedVmName();

	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: fakeListOutput($vm_name, 'running')),
		default => Process::result(),
	});

	$step = app(ResolveVm::class);
	$context = makeResolveVmContext();

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->vm_name)->toBe('clave-test-sess');
	expect($context->ephemeral)->toBeTrue();
	expect($context->resumed)->toBeFalse();
});

test('deletes stopped VM and clones fresh', function() {
	$vm_name = expectedVmName();

	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: fakeListOutput($vm_name, 'stopped')),
		default => Process::result(),
	});

	$step = app(ResolveVm::class);
	$context = makeResolveVmContext();

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->vm_name)->toBe($vm_name);
	expect($context->resumed)->toBeFalse();

	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', $vm_name]);
	Process::assertRan(fn(PendingProcess $p) => $p->command[1] === 'clone');
});

test('--fresh deletes existing VM and clones fresh', function() {
	$vm_name = expectedVmName();

	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: fakeListOutput($vm_name, 'suspended')),
		default => Process::result(),
	});

	$step = app(ResolveVm::class);
	$context = makeResolveVmContext(fresh: true);

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->vm_name)->toBe($vm_name);
	expect($context->resumed)->toBeFalse();

	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', $vm_name]);
	Process::assertRan(fn(PendingProcess $p) => $p->command[1] === 'clone');
});

test('replaces suspended VM when base_vm hash does not match', function() {
	$vm_name = expectedVmName();
	$context = makeResolveVmContext();

	$home = $_SERVER['HOME'] ?? getenv('HOME');
	$metadata_dir = "{$home}/.config/clave/vms";
	$metadata_path = "{$metadata_dir}/{$vm_name}.json";

	if (! is_dir($metadata_dir)) {
		mkdir($metadata_dir, 0755, true);
	}
	file_put_contents($metadata_path, json_encode(['base_vm' => 'clave-base-stale', 'project_dir' => '/path/to/my-project']));

	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: fakeListOutput($vm_name, 'suspended')),
		default => Process::result(),
	});

	$step = app(ResolveVm::class);

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->vm_name)->toBe($vm_name);
	expect($context->resumed)->toBeFalse();

	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', $vm_name]);
	Process::assertRan(fn(PendingProcess $p) => $p->command[1] === 'clone');

	@unlink($metadata_path);
});
