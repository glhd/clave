<?php

namespace App\Commands;

use App\Services\AuthManager;
use LaravelZero\Framework\Commands\Command;

class AuthCommand extends Command
{
	protected $signature = 'auth {--status : Show current auth method} {--clear : Remove stored token}';
	
	protected $description = 'Manage Claude Code authentication for Clave sessions';
	
	public function handle(AuthManager $auth): int
	{
		if ($this->option('status')) {
			return $this->showStatus($auth);
		}
		
		if ($this->option('clear')) {
			return $this->clearToken($auth);
		}
		
		return $this->runSetup($auth);
	}
	
	protected function showStatus(AuthManager $auth): int
	{
		$info = $auth->statusInfo();
		
		if ($info['method'] === 'none') {
			$this->warn('No authentication configured.');
			$this->line('Run `clave auth` to set up a Claude Code token.');
			
			return self::SUCCESS;
		}
		
		$method_label = match ($info['method']) {
			'api_key' => 'API Key',
			'oauth' => 'OAuth Token',
		};
		
		$this->info("Auth method: {$method_label}");
		$this->line("Source: {$info['source']}");
		
		if (isset($info['stored_at'])) {
			$this->line("Stored at: {$info['stored_at']}");
		}
		
		return self::SUCCESS;
	}
	
	protected function clearToken(AuthManager $auth): int
	{
		$auth->clearToken();
		$this->info('Stored authentication token has been removed.');
		
		return self::SUCCESS;
	}
	
	protected function runSetup(AuthManager $auth): int
	{
		if (! $auth->setupToken()) {
			$this->error('Authentication setup failed. Ensure `claude` CLI is installed and try again.');
			
			return self::FAILURE;
		}
		
		$this->info('Authentication configured successfully.');
		
		return self::SUCCESS;
	}
}
