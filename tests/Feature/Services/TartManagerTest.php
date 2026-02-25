<?php

use App\Support\TartManager;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

beforeEach(function() {
	$this->tart = new TartManager();
});

test('clone runs correct command', function() {
	Process::fake();

	$this->tart->clone('ghcr.io/cirruslabs/ubuntu:latest', 'clave-base');

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'clone', 'ghcr.io/cirruslabs/ubuntu:latest', 'clave-base']);
});

test('stop runs correct command', function() {
	Process::fake();

	$this->tart->stop('clave-abc123');

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'stop', 'clave-abc123']);
});

test('delete runs correct command', function() {
	Process::fake();

	$this->tart->delete('clave-abc123');

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'delete', 'clave-abc123']);
});

test('exists returns true when vm exists', function() {
	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'get', 'clave-base'] => Process::result(output: '{}'),
		default => Process::result(exitCode: 1),
	});

	expect($this->tart->exists('clave-base'))->toBeTrue();
});

test('exists returns false when vm does not exist', function() {
	Process::fake(fn() => Process::result(exitCode: 1));

	expect($this->tart->exists('missing-vm'))->toBeFalse();
});

test('set builds command with options', function() {
	Process::fake();

	$this->tart->set('clave-base', cpus: 4, memory: 8192, display: 'none');

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'set', 'clave-base', '--cpu', '4', '--memory', '8192', '--display', 'none']);
});

test('set builds command with partial options', function() {
	Process::fake();

	$this->tart->set('clave-base', cpus: 8);

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'set', 'clave-base', '--cpu', '8']);
});

test('runBackground starts vm with dir mounts', function() {
	Process::fake();

	$this->tart->runBackground('clave-abc123', ['/path/to/worktree']);

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'run', 'clave-abc123', '--no-graphics', '--dir', '/path/to/worktree']);
});

test('ip returns valid ip address', function() {
	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'ip', 'my-vm'] => Process::result(output: "192.168.64.5\n"),
		default => Process::result(),
	});

	expect($this->tart->ip('my-vm'))->toBe('192.168.64.5');
});

test('randomizeMac runs correct command', function() {
	Process::fake();

	$this->tart->randomizeMac('clave-abc123');

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'set', 'clave-abc123', '--random-mac']);
});

test('rename runs correct command', function() {
	Process::fake();

	$this->tart->rename('clave-tmp-abc', 'clave-base');

	Process::assertRan(fn(PendingProcess $process) => $process->command === ['tart', 'rename', 'clave-tmp-abc', 'clave-base']);
});

test('list returns parsed json', function() {
	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: '[{"name":"clave-base"}]'),
		default => Process::result(),
	});

	expect($this->tart->list())->toBe([['name' => 'clave-base']]);
});
