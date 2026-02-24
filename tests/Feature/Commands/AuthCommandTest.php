<?php

beforeEach(function() {
	$this->temp_dir = sys_get_temp_dir().'/clave-test-'.bin2hex(random_bytes(4));
	mkdir($this->temp_dir, 0700, true);
	$this->auth_file = $this->temp_dir.'/auth.json';
	config()->set('clave.auth_file', $this->auth_file);
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', null);
});

afterEach(function() {
	@unlink($this->auth_file);
	@rmdir($this->temp_dir);
});

test('auth --status shows no auth when unconfigured', function() {
	$this->artisan('auth', ['--status' => true])
		->expectsOutputToContain('No authentication configured')
		->assertSuccessful();
});

test('auth --status shows api key when configured', function() {
	config()->set('clave.anthropic_api_key', 'sk-ant-api03-test');
	
	$this->artisan('auth', ['--status' => true])
		->expectsOutputToContain('API Key')
		->assertSuccessful();
});

test('auth --status shows oauth token when configured', function() {
	config()->set('clave.oauth_token', 'sk-ant-oat01-test');
	
	$this->artisan('auth', ['--status' => true])
		->expectsOutputToContain('OAuth Token')
		->assertSuccessful();
});

test('auth --status shows stored token info', function() {
	file_put_contents($this->auth_file, json_encode([
		'token' => 'sk-ant-oat01-stored',
		'stored_at' => '2026-02-24T10:00:00-05:00',
	]));
	
	$this->artisan('auth', ['--status' => true])
		->expectsOutputToContain('OAuth Token')
		->expectsOutputToContain('Stored token')
		->assertSuccessful();
});

test('auth --clear removes stored token', function() {
	file_put_contents($this->auth_file, json_encode(['token' => 'test']));
	
	$this->artisan('auth', ['--clear' => true])
		->expectsOutputToContain('removed')
		->assertSuccessful();
	
	expect(file_exists($this->auth_file))->toBeFalse();
});
