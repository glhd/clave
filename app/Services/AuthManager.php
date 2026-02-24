<?php

namespace App\Services;

use function Laravel\Prompts\textarea;

class AuthManager
{
	public function resolve(): ?array
	{
		$api_key = config('clave.anthropic_api_key');
		if ($api_key) {
			return ['type' => 'api_key', 'value' => $api_key];
		}
		
		$oauth_token = config('clave.oauth_token');
		if ($oauth_token) {
			return ['type' => 'oauth', 'value' => $oauth_token];
		}
		
		$stored_token = $this->loadStoredToken();
		if ($stored_token) {
			return ['type' => 'oauth', 'value' => $stored_token];
		}
		
		return null;
	}
	
	public function hasAuth(): bool
	{
		return $this->resolve() !== null;
	}
	
	public function statusInfo(): array
	{
		$auth = $this->resolve();
		
		if ($auth === null) {
			return ['method' => 'none'];
		}
		
		$info = ['method' => $auth['type']];
		
		if ($auth['type'] === 'api_key') {
			$info['source'] = 'ANTHROPIC_API_KEY environment variable';
			
			return $info;
		}
		
		if (config('clave.oauth_token')) {
			$info['source'] = 'CLAUDE_CODE_OAUTH_TOKEN environment variable';
			
			return $info;
		}
		
		$info['source'] = 'Stored token ('.config('clave.auth_file').')';
		
		$auth_file = config('clave.auth_file');
		if (file_exists($auth_file)) {
			$data = json_decode(file_get_contents($auth_file), true);
			$info['stored_at'] = $data['stored_at'] ?? null;
		}
		
		return $info;
	}
	
	public function setupToken(): bool
	{
		$exit_code = 0;
		passthru('claude setup-token', $exit_code);

		if ($exit_code !== 0) {
			return false;
		}

		$token = textarea(
			label: 'Paste the OAuth token shown above (it may wrap across multiple lines)',
			placeholder: 'sk-ant-oat01-...',
			rows: 3,
			validate: function(string $value) {
				$cleaned = preg_replace('/\s+/', '', $value);

				return str_starts_with($cleaned, 'sk-ant-')
					? null
					: 'Token should start with sk-ant-';
			},
		);

		return $this->storeToken(preg_replace('/\s+/', '', $token));
	}
	
	public function clearToken(): void
	{
		$auth_file = config('clave.auth_file');
		
		if (file_exists($auth_file)) {
			unlink($auth_file);
		}
	}
	
	public function loadStoredToken(): ?string
	{
		$auth_file = config('clave.auth_file');
		
		if (! file_exists($auth_file)) {
			return null;
		}
		
		$data = json_decode(file_get_contents($auth_file), true);
		
		return $data['token'] ?? null;
	}
	
	public function storeToken(string $token): bool
	{
		if (! $token) {
			return false;
		}

		$auth_file = config('clave.auth_file');
		$auth_dir = dirname($auth_file);

		if (! is_dir($auth_dir)) {
			mkdir($auth_dir, 0700, true);
		}

		$data = [
			'token' => $token,
			'stored_at' => date('c'),
		];

		file_put_contents($auth_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

		return true;
	}
}
