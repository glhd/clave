<?php

use App\Data\ProjectConfig;

test('baseVmName returns default when no customizations', function() {
	$config = new ProjectConfig();

	expect($config->baseVmName())->toBe(config('clave.base_vm'));
});

test('baseVmName returns hashed name when customized', function() {
	$config = new ProjectConfig(base_image: 'ghcr.io/custom/image:latest');

	$name = $config->baseVmName();

	expect($name)->toStartWith(config('clave.base_vm').'-')
		->and(strlen($name))->toBe(strlen(config('clave.base_vm')) + 9); // dash + 8 hex chars
});

test('baseVmName is deterministic for same config', function() {
	$config_a = new ProjectConfig(base_image: 'ghcr.io/custom/image:latest', provision: ['cmd1']);
	$config_b = new ProjectConfig(base_image: 'ghcr.io/custom/image:latest', provision: ['cmd1']);

	expect($config_a->baseVmName())->toBe($config_b->baseVmName());
});

test('baseVmName differs for different configs', function() {
	$config_a = new ProjectConfig(base_image: 'ghcr.io/custom/image:v1');
	$config_b = new ProjectConfig(base_image: 'ghcr.io/custom/image:v2');

	expect($config_a->baseVmName())->not->toBe($config_b->baseVmName());
});
