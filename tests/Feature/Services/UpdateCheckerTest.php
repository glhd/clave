<?php

use App\Support\UpdateChecker;

beforeEach(function() {
	$this->cache_path = UpdateChecker::cachePath();
	$this->cache_dir = dirname($this->cache_path);
	$this->original_cache = file_exists($this->cache_path) ? file_get_contents($this->cache_path) : null;
	$this->dir_existed = is_dir($this->cache_dir);

	if (! $this->dir_existed) {
		mkdir($this->cache_dir, 0755, true);
	}
});

afterEach(function() {
	if ($this->original_cache !== null) {
		file_put_contents($this->cache_path, $this->original_cache);
	} elseif (file_exists($this->cache_path)) {
		unlink($this->cache_path);
	}

	if (! $this->dir_existed && is_dir($this->cache_dir)) {
		@rmdir($this->cache_dir);
	}
});

test('check returns null for unreleased version', function() {
	$checker = new UpdateChecker();

	expect($checker->check('unreleased'))->toBeNull();
});

test('check returns null when current version matches latest', function() {
	$checker = Mockery::mock(UpdateChecker::class)->makePartial();
	$checker->shouldReceive('getLatestVersion')->andReturn('v1.0.0');

	expect($checker->check('v1.0.0'))->toBeNull();
});

test('check returns null when current version is newer', function() {
	$checker = Mockery::mock(UpdateChecker::class)->makePartial();
	$checker->shouldReceive('getLatestVersion')->andReturn('v1.0.0');

	expect($checker->check('v1.1.0'))->toBeNull();
});

test('check returns latest version when update is available', function() {
	$checker = Mockery::mock(UpdateChecker::class)->makePartial();
	$checker->shouldReceive('getLatestVersion')->andReturn('v2.0.0');

	expect($checker->check('v1.0.0'))->toBe('v2.0.0');
});

test('check normalizes version with v prefix', function() {
	$checker = Mockery::mock(UpdateChecker::class)->makePartial();
	$checker->shouldReceive('getLatestVersion')->andReturn('v1.2.0');

	expect($checker->check('1.1.0'))->toBe('v1.2.0');
});

test('check returns null when latest version is unavailable', function() {
	$checker = Mockery::mock(UpdateChecker::class)->makePartial();
	$checker->shouldReceive('getLatestVersion')->andReturn(null);

	expect($checker->check('v1.0.0'))->toBeNull();
});

test('getLatestVersion returns cached version when cache is fresh', function() {
	file_put_contents($this->cache_path, json_encode([
		'version' => 'v3.0.0',
		'checked_at' => time(),
	]));

	$checker = new UpdateChecker();

	expect($checker->getLatestVersion())->toBe('v3.0.0');
});

test('getLatestVersion ignores expired cache', function() {
	file_put_contents($this->cache_path, json_encode([
		'version' => 'v3.0.0',
		'checked_at' => time() - 100000,
	]));

	$checker = Mockery::mock(UpdateChecker::class)->makePartial();
	$checker->shouldAllowMockingProtectedMethods();
	$checker->shouldReceive('fetchLatestVersion')->once()->andReturn('v4.0.0');

	expect($checker->getLatestVersion())->toBe('v4.0.0');
});
