<?php

use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use App\Pipelines\Steps\EnsureTartInstalled;
use App\Support\InstallationManager;
use Illuminate\Support\Facades\Process;

test('passes through when tart is installed', function() {
	Process::fake([
		'which tart' => Process::result(output: '/usr/local/bin/tart', exitCode: 0),
	]);

	$step = app(EnsureTartInstalled::class);
	$context = new SessionContext(
		session_id: 'test-123',
		project_name: 'test-project',
		project_dir: '/path/to/project',
	);

	$next_called = false;
	$result = $step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($result)->toBe($context);
});

test('aborts when tart not installed and homebrew not available', function() {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
		'which brew' => Process::result(exitCode: 1),
	]);

	$step = app(EnsureTartInstalled::class);
	$context = new SessionContext(
		session_id: 'test-123',
		project_name: 'test-project',
		project_dir: '/path/to/project',
	);

	expect(fn() => $step->handle($context, fn($ctx) => $ctx))
		->toThrow(AbortedPipelineException::class, 'Tart installation is required to continue.');
});
