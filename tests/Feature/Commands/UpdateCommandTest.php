<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('bails when not running as a PHAR', function() {
	$this->artisan('update')
		->expectsOutputToContain('Not running as a PHAR')
		->assertFailed();
});

test('reports already up to date when versions match', function() {
	$current_version = config('app.version');

	Http::fake([
		'api.github.com/repos/glhd/clave/releases/latest' => Http::response([
			'tag_name' => "v{$current_version}",
		]),
	]);

	config()->set('clave.phar_path', '/usr/local/bin/clave');

	$this->artisan('update')
		->expectsOutputToContain('Already up to date')
		->assertSuccessful();

	Http::assertSentCount(1);
});

test('fails gracefully on network error', function() {
	Http::fake(fn() => throw new ConnectionException('Connection timed out'));

	config()->set('clave.phar_path', '/usr/local/bin/clave');

	$this->artisan('update')
		->expectsOutputToContain('Could not reach GitHub')
		->assertFailed();
});

test('fails when API response is missing tag_name', function() {
	Http::fake([
		'api.github.com/repos/glhd/clave/releases/latest' => Http::response([
			'message' => 'Not Found',
		]),
	]);

	config()->set('clave.phar_path', '/usr/local/bin/clave');

	$this->artisan('update')
		->expectsOutputToContain('Unexpected response from GitHub')
		->assertFailed();

	Http::assertSentCount(1);
});
