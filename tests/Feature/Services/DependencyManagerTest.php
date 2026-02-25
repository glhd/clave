<?php

use App\Support\DependencyManager;
use Illuminate\Support\Facades\Process;

test('isTartInstalled returns true when tart is in PATH', function () {
	Process::fake([
		'which tart' => Process::result(output: '/usr/local/bin/tart', exitCode: 0),
	]);

	expect(app(DependencyManager::class)->isTartInstalled())->toBeTrue();
});

test('isTartInstalled returns false when tart is not in PATH', function () {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
	]);

	expect(app(DependencyManager::class)->isTartInstalled())->toBeFalse();
});

test('isPkgxInstalled returns true when pkgx is in PATH', function () {
	Process::fake([
		'which pkgx' => Process::result(output: '/usr/local/bin/pkgx', exitCode: 0),
	]);

	expect(app(DependencyManager::class)->isPkgxInstalled())->toBeTrue();
});

test('isPkgxInstalled returns false when pkgx is not in PATH', function () {
	Process::fake([
		'which pkgx' => Process::result(exitCode: 1),
	]);

	expect(app(DependencyManager::class)->isPkgxInstalled())->toBeFalse();
});

test('isHomebrewInstalled returns true when brew is in PATH', function () {
	Process::fake([
		'which brew' => Process::result(output: '/opt/homebrew/bin/brew', exitCode: 0),
	]);

	expect(app(DependencyManager::class)->isHomebrewInstalled())->toBeTrue();
});

test('isHomebrewInstalled returns false when brew is not in PATH', function () {
	Process::fake([
		'which brew' => Process::result(exitCode: 1),
	]);

	expect(app(DependencyManager::class)->isHomebrewInstalled())->toBeFalse();
});

test('installTartViaPkgx runs correct command', function () {
	Process::fake([
		'pkgx install tart' => Process::result(exitCode: 0),
	]);

	$result = app(DependencyManager::class)->installTartViaPkgx();

	expect($result)->toBeTrue();
	Process::assertRan('pkgx install tart');
});

test('installTartViaHomebrew runs correct command', function () {
	Process::fake([
		'brew install cirruslabs/cli/tart' => Process::result(exitCode: 0),
	]);

	$result = app(DependencyManager::class)->installTartViaHomebrew();

	expect($result)->toBeTrue();
	Process::assertRan('brew install cirruslabs/cli/tart');
});
