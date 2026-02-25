<?php

use App\Support\InstallationManager;
use Illuminate\Support\Facades\Process;

test('isTartInstalled returns true when tart is in PATH', function() {
	Process::fake([
		'which tart' => Process::result(output: '/usr/local/bin/tart', exitCode: 0),
	]);

	$manager = app(InstallationManager::class);

	expect($manager->isTartInstalled())->toBeTrue();
});

test('isTartInstalled returns false when tart is not in PATH', function() {
	Process::fake([
		'which tart' => Process::result(exitCode: 1),
	]);

	$manager = app(InstallationManager::class);

	expect($manager->isTartInstalled())->toBeFalse();
});

test('isHomebrewInstalled returns true when brew is in PATH', function() {
	Process::fake([
		'which brew' => Process::result(output: '/opt/homebrew/bin/brew', exitCode: 0),
	]);

	$manager = app(InstallationManager::class);

	expect($manager->isHomebrewInstalled())->toBeTrue();
});

test('isHomebrewInstalled returns false when brew is not in PATH', function() {
	Process::fake([
		'which brew' => Process::result(exitCode: 1),
	]);

	$manager = app(InstallationManager::class);

	expect($manager->isHomebrewInstalled())->toBeFalse();
});

test('installTartViaHomebrew runs correct command', function() {
	Process::fake([
		'brew install cirruslabs/cli/tart' => Process::result(exitCode: 0),
	]);

	$manager = app(InstallationManager::class);
	$result = $manager->installTartViaHomebrew();

	expect($result->successful())->toBeTrue();

	Process::assertRan('brew install cirruslabs/cli/tart');
});

test('getManualInstructions returns formatted string', function() {
	$manager = app(InstallationManager::class);
	$instructions = $manager->getManualInstructions();

	expect($instructions)
		->toContain('Tart Installation Instructions')
		->toContain('macOS 13.0 or later')
		->toContain('Apple Silicon')
		->toContain('brew install cirruslabs/cli/tart')
		->toContain('https://github.com/cirruslabs/tart');
});
