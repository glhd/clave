<?php

use App\Data\ProjectConfig;
use Illuminate\Filesystem\Filesystem;

test('default construction has no customizations', function() {
	$config = new ProjectConfig();

	expect($config->base_image)->toBeNull()
		->and($config->provision)->toBe([])
		->and($config->hasCustomizations())->toBeFalse();
});

test('hasCustomizations returns true when base_image is set', function() {
	$config = new ProjectConfig(base_image: 'ghcr.io/custom/image:latest');

	expect($config->hasCustomizations())->toBeTrue();
});

test('hasCustomizations returns true when provision is set', function() {
	$config = new ProjectConfig(provision: ['sudo apt-get install -y postgresql']);

	expect($config->hasCustomizations())->toBeTrue();
});

test('fromProjectDir returns defaults when no .clave.json exists', function() {
	$fs = Mockery::mock(Filesystem::class);
	$fs->shouldReceive('exists')->with('/path/to/project/.clave.json')->andReturn(false);

	$config = ProjectConfig::fromProjectDir('/path/to/project', $fs);

	expect($config->base_image)->toBeNull()
		->and($config->provision)->toBe([])
		->and($config->hasCustomizations())->toBeFalse();
});

test('fromProjectDir parses .clave.json with base_image', function() {
	$fs = Mockery::mock(Filesystem::class);
	$fs->shouldReceive('exists')->with('/path/to/project/.clave.json')->andReturn(true);
	$fs->shouldReceive('get')->with('/path/to/project/.clave.json')->andReturn(json_encode([
		'base_image' => 'ghcr.io/custom/image:latest',
	]));

	$config = ProjectConfig::fromProjectDir('/path/to/project', $fs);

	expect($config->base_image)->toBe('ghcr.io/custom/image:latest')
		->and($config->provision)->toBe([]);
});

test('fromProjectDir parses .clave.json with provision steps', function() {
	$fs = Mockery::mock(Filesystem::class);
	$fs->shouldReceive('exists')->with('/path/to/project/.clave.json')->andReturn(true);
	$fs->shouldReceive('get')->with('/path/to/project/.clave.json')->andReturn(json_encode([
		'provision' => [
			'sudo apt-get install -y postgresql',
			'sudo systemctl enable postgresql',
		],
	]));

	$config = ProjectConfig::fromProjectDir('/path/to/project', $fs);

	expect($config->base_image)->toBeNull()
		->and($config->provision)->toBe([
			'sudo apt-get install -y postgresql',
			'sudo systemctl enable postgresql',
		]);
});

test('fromProjectDir parses full .clave.json', function() {
	$fs = Mockery::mock(Filesystem::class);
	$fs->shouldReceive('exists')->with('/path/to/project/.clave.json')->andReturn(true);
	$fs->shouldReceive('get')->with('/path/to/project/.clave.json')->andReturn(json_encode([
		'base_image' => 'ghcr.io/custom/image:latest',
		'provision' => ['sudo apt-get install -y redis-server'],
	]));

	$config = ProjectConfig::fromProjectDir('/path/to/project', $fs);

	expect($config->base_image)->toBe('ghcr.io/custom/image:latest')
		->and($config->provision)->toBe(['sudo apt-get install -y redis-server'])
		->and($config->hasCustomizations())->toBeTrue();
});

test('fromProjectDir handles invalid json gracefully', function() {
	$fs = Mockery::mock(Filesystem::class);
	$fs->shouldReceive('exists')->with('/path/to/project/.clave.json')->andReturn(true);
	$fs->shouldReceive('get')->with('/path/to/project/.clave.json')->andReturn('not valid json');

	$config = ProjectConfig::fromProjectDir('/path/to/project', $fs);

	expect($config->base_image)->toBeNull()
		->and($config->provision)->toBe([]);
});
