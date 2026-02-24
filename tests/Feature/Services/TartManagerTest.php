<?php

use App\Services\TartManager;
use Illuminate\Support\Facades\Process;

beforeEach(function() {
	$this->tart = new TartManager();
});

test('clone runs correct command', function() {
	Process::fake();

	$this->tart->clone('ghcr.io/cirruslabs/ubuntu:latest', 'clave-base');

	Process::assertRan('tart clone ghcr.io/cirruslabs/ubuntu:latest clave-base');
});

test('stop runs correct command', function() {
	Process::fake();

	$this->tart->stop('clave-abc123');

	Process::assertRan('tart stop clave-abc123');
});

test('delete runs correct command', function() {
	Process::fake();

	$this->tart->delete('clave-abc123');

	Process::assertRan('tart delete clave-abc123');
});

test('exists returns true when vm exists', function() {
	Process::fake([
		'tart get clave-base' => Process::result(output: '{}'),
	]);

	expect($this->tart->exists('clave-base'))->toBeTrue();
});

test('exists returns false when vm does not exist', function() {
	Process::fake([
		'tart get missing-vm' => Process::result(exitCode: 1),
	]);

	expect($this->tart->exists('missing-vm'))->toBeFalse();
});

test('set builds command with options', function() {
	Process::fake();

	$this->tart->set('clave-base', cpus: 4, memory: 8192, display: 'none');

	Process::assertRan('tart set clave-base --cpu 4 --memory 8192 --display none');
});

test('set builds command with partial options', function() {
	Process::fake();

	$this->tart->set('clave-base', cpus: 8);

	Process::assertRan('tart set clave-base --cpu 8');
});

test('runBackground starts vm with dir mounts', function() {
	Process::fake();

	$this->tart->runBackground('clave-abc123', ['project' => '/path/to/worktree']);

	Process::assertRan('tart run clave-abc123 --no-graphics --dir=project:/path/to/worktree');
});

test('ip returns valid ip address', function() {
	Process::fake([
		'tart ip my-vm' => Process::result(output: "192.168.64.5\n"),
	]);

	expect($this->tart->ip('my-vm'))->toBe('192.168.64.5');
});

test('list returns parsed json', function() {
	Process::fake([
		'tart list --format json' => Process::result(output: '[{"name":"clave-base"}]'),
	]);

	expect($this->tart->list())->toBe([['name' => 'clave-base']]);
});
