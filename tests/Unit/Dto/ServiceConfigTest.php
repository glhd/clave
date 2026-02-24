<?php

use App\Dto\ServiceConfig;

test('host services uses gateway ip', function() {
	$config = ServiceConfig::hostServices('192.168.64.1');

	expect($config->mysql_host)->toBe('192.168.64.1')
		->and($config->mysql_port)->toBe(3306)
		->and($config->redis_host)->toBe('192.168.64.1')
		->and($config->redis_port)->toBe(6379);
});

test('local services uses localhost', function() {
	$config = ServiceConfig::localServices();

	expect($config->mysql_host)->toBe('127.0.0.1')
		->and($config->mysql_port)->toBe(3306)
		->and($config->redis_host)->toBe('127.0.0.1')
		->and($config->redis_port)->toBe(6379);
});

test('properties are readonly', function() {
	$config = ServiceConfig::localServices();

	$reflection = new ReflectionClass($config);
	$property = $reflection->getProperty('mysql_host');

	expect($property->isReadOnly())->toBeTrue();
});
