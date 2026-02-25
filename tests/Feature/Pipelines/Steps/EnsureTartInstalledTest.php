<?php

use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use App\Pipelines\Steps\EnsureTartInstalled;
use App\Support\DependencyManager;
use Illuminate\Support\Facades\Process;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

function makeContext(): SessionContext
{
	return new SessionContext(
		session_id: 'test-123',
		project_name: 'test-project',
		project_dir: '/path/to/project',
	);
}

test('passes through when tart is already installed', function () {
	Process::fake([
		'which tart' => Process::result(output: '/usr/local/bin/tart', exitCode: 0),
	]);

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	$next_called = false;
	$result = $step->handle($context, function ($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($result)->toBe($context);
});

test('aborts with manual instructions when no package manager is available', function () {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
		'which pkgx' => Process::result(exitCode: 1),
		'which brew' => Process::result(exitCode: 1),
	]);

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	expect(fn () => $step->handle($context, fn ($ctx) => $ctx))
		->toThrow(AbortedPipelineException::class, 'Tart must be installed before you can use Clave.');
});

test('installs tart via pkgx when selected', function () {
	$mock = Mockery::mock(DependencyManager::class);
	$mock->shouldReceive('isTartInstalled')->andReturn(false, true);
	$mock->shouldReceive('isPkgxInstalled')->andReturn(true);
	$mock->shouldReceive('isHomebrewInstalled')->andReturn(false);
	$mock->shouldReceive('installTartViaPkgx')->once()->andReturn(true);
	app()->instance(DependencyManager::class, $mock);

	Prompt::fake([Key::ENTER]); // select first option (pkgx)

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	$next_called = false;
	$step->handle($context, function ($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
});

test('installs tart via homebrew when selected', function () {
	$mock = Mockery::mock(DependencyManager::class);
	$mock->shouldReceive('isTartInstalled')->andReturn(false, true);
	$mock->shouldReceive('isPkgxInstalled')->andReturn(false);
	$mock->shouldReceive('isHomebrewInstalled')->andReturn(true);
	$mock->shouldReceive('installTartViaHomebrew')->once()->andReturn(true);
	app()->instance(DependencyManager::class, $mock);

	Prompt::fake([Key::ENTER]); // select first option (homebrew)

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	$next_called = false;
	$step->handle($context, function ($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
});

test('aborts when user selects manual instructions', function () {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
		'which pkgx' => Process::result(exitCode: 1),
		'which brew' => Process::result(output: '/opt/homebrew/bin/brew', exitCode: 0),
	]);

	Prompt::fake([Key::DOWN, Key::ENTER]); // select second option (manual)

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	expect(fn () => $step->handle($context, fn ($ctx) => $ctx))
		->toThrow(AbortedPipelineException::class, 'Tart must be installed before you can use Clave.');
});

test('aborts when pkgx installation fails', function () {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
		'which pkgx' => Process::result(output: '/usr/local/bin/pkgx', exitCode: 0),
		'which brew' => Process::result(exitCode: 1),
		'pkgx install tart' => Process::result(exitCode: 1),
	]);

	Prompt::fake([Key::DOWN, Key::ENTER]); // select pkgx

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	expect(fn () => $step->handle($context, fn ($ctx) => $ctx))
		->toThrow(AbortedPipelineException::class, 'Tart must be installed before you can use Clave.');
});

test('aborts when homebrew installation fails', function () {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
		'which pkgx' => Process::result(exitCode: 1),
		'which brew' => Process::result(output: '/opt/homebrew/bin/brew', exitCode: 0),
		'brew install cirruslabs/cli/tart' => Process::result(exitCode: 1),
	]);

	Prompt::fake([Key::DOWN, Key::ENTER]); // select homebrew

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	expect(fn () => $step->handle($context, fn ($ctx) => $ctx))
		->toThrow(AbortedPipelineException::class, 'Tart must be installed before you can use Clave.');
});

test('aborts when tart not in PATH after successful pkgx install', function () {
	$mock = Mockery::mock(DependencyManager::class);
	$mock->shouldReceive('isTartInstalled')->andReturn(false); // never available
	$mock->shouldReceive('isPkgxInstalled')->andReturn(true);
	$mock->shouldReceive('isHomebrewInstalled')->andReturn(false);
	$mock->shouldReceive('installTartViaPkgx')->once()->andReturn(true);
	app()->instance(DependencyManager::class, $mock);

	Prompt::fake([Key::ENTER]); // select first option (pkgx)

	$step = app(EnsureTartInstalled::class);
	$context = makeContext();

	expect(fn () => $step->handle($context, fn ($ctx) => $ctx))
		->toThrow(AbortedPipelineException::class, 'Tart is not available in $PATH. You may need to restart your shell.');
});
