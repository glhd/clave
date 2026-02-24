<?php

use App\Services\AuthManager;

beforeEach(function() {
	$this->auth = new AuthManager();
	$this->temp_dir = sys_get_temp_dir().'/clave-test-'.bin2hex(random_bytes(4));
	mkdir($this->temp_dir, 0700, true);
	$this->auth_file = $this->temp_dir.'/auth.json';
	config()->set('clave.auth_file', $this->auth_file);
});

afterEach(function() {
	@unlink($this->auth_file);
	@rmdir($this->temp_dir);
});

test('resolve returns api_key when ANTHROPIC_API_KEY is set', function() {
	config()->set('clave.anthropic_api_key', 'sk-ant-api03-test');
	config()->set('clave.oauth_token', null);
	
	$result = $this->auth->resolve();
	
	expect($result)->toBe(['type' => 'api_key', 'value' => 'sk-ant-api03-test']);
});

test('resolve returns oauth when CLAUDE_CODE_OAUTH_TOKEN is set', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', 'sk-ant-oat01-test');
	
	$result = $this->auth->resolve();
	
	expect($result)->toBe(['type' => 'oauth', 'value' => 'sk-ant-oat01-test']);
});

test('resolve returns stored token when no env vars set', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', null);
	
	file_put_contents($this->auth_file, json_encode([
		'token' => 'sk-ant-oat01-stored',
		'stored_at' => '2026-02-24T10:00:00-05:00',
	]));
	
	$result = $this->auth->resolve();
	
	expect($result)->toBe(['type' => 'oauth', 'value' => 'sk-ant-oat01-stored']);
});

test('resolve returns null when no auth configured', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', null);
	
	expect($this->auth->resolve())->toBeNull();
});

test('api_key takes priority over oauth_token', function() {
	config()->set('clave.anthropic_api_key', 'sk-ant-api03-test');
	config()->set('clave.oauth_token', 'sk-ant-oat01-test');
	
	$result = $this->auth->resolve();
	
	expect($result['type'])->toBe('api_key');
});

test('oauth_token env takes priority over stored token', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', 'sk-ant-oat01-env');
	
	file_put_contents($this->auth_file, json_encode([
		'token' => 'sk-ant-oat01-stored',
	]));
	
	$result = $this->auth->resolve();
	
	expect($result['value'])->toBe('sk-ant-oat01-env');
});

test('hasAuth returns true when auth is available', function() {
	config()->set('clave.anthropic_api_key', 'sk-ant-api03-test');
	
	expect($this->auth->hasAuth())->toBeTrue();
});

test('hasAuth returns false when no auth configured', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', null);
	
	expect($this->auth->hasAuth())->toBeFalse();
});

test('clearToken removes auth file', function() {
	file_put_contents($this->auth_file, json_encode(['token' => 'test']));
	
	expect(file_exists($this->auth_file))->toBeTrue();
	
	$this->auth->clearToken();
	
	expect(file_exists($this->auth_file))->toBeFalse();
});

test('clearToken does not error when file missing', function() {
	expect(file_exists($this->auth_file))->toBeFalse();
	
	$this->auth->clearToken();
	
	expect(file_exists($this->auth_file))->toBeFalse();
});

test('loadStoredToken returns null when file missing', function() {
	expect($this->auth->loadStoredToken())->toBeNull();
});

test('loadStoredToken returns token from valid auth file', function() {
	file_put_contents($this->auth_file, json_encode([
		'token' => 'sk-ant-oat01-stored',
		'stored_at' => '2026-02-24T10:00:00-05:00',
	]));
	
	expect($this->auth->loadStoredToken())->toBe('sk-ant-oat01-stored');
});

test('loadStoredToken returns null when file has no token key', function() {
	file_put_contents($this->auth_file, json_encode(['stored_at' => 'now']));
	
	expect($this->auth->loadStoredToken())->toBeNull();
});

test('storeToken writes token to auth file', function() {
	$this->auth->storeToken('sk-ant-oat01-test');

	expect(file_exists($this->auth_file))->toBeTrue();

	$data = json_decode(file_get_contents($this->auth_file), true);

	expect($data['token'])->toBe('sk-ant-oat01-test')
		->and($data['stored_at'])->not->toBeNull();
});

test('storeToken returns false for empty token', function() {
	expect($this->auth->storeToken(''))->toBeFalse();
	expect(file_exists($this->auth_file))->toBeFalse();
});

test('statusInfo returns none when no auth configured', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', null);
	
	expect($this->auth->statusInfo())->toBe(['method' => 'none']);
});

test('statusInfo returns api_key source', function() {
	config()->set('clave.anthropic_api_key', 'sk-ant-api03-test');
	
	$info = $this->auth->statusInfo();
	
	expect($info['method'])->toBe('api_key')
		->and($info['source'])->toBe('ANTHROPIC_API_KEY environment variable');
});

test('statusInfo returns oauth env source', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', 'sk-ant-oat01-test');
	
	$info = $this->auth->statusInfo();
	
	expect($info['method'])->toBe('oauth')
		->and($info['source'])->toBe('CLAUDE_CODE_OAUTH_TOKEN environment variable');
});

test('statusInfo returns stored token source with timestamp', function() {
	config()->set('clave.anthropic_api_key', null);
	config()->set('clave.oauth_token', null);
	
	file_put_contents($this->auth_file, json_encode([
		'token' => 'sk-ant-oat01-stored',
		'stored_at' => '2026-02-24T10:00:00-05:00',
	]));
	
	$info = $this->auth->statusInfo();
	
	expect($info['method'])->toBe('oauth')
		->and($info['source'])->toContain('Stored token')
		->and($info['stored_at'])->toBe('2026-02-24T10:00:00-05:00');
});
