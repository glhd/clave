<?php

namespace App\Commands;

use App\Facades\Progress;
use App\Support\AuthManager;
use App\Support\ClaveProgress;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

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
			warning('No authentication configured.');
			note('Run `clave auth` to set up a Claude Code token.');
			
			return self::SUCCESS;
		}

		$method_label = match ($info['method']) {
			'api_key' => 'API Key',
			'oauth' => 'OAuth Token',
		};
		
		info("Auth method: {$method_label}");
		note("Source: {$info['source']}");
		
		if (isset($info['stored_at'])) {
			note("Stored at: {$info['stored_at']}");
		}
		
		return self::SUCCESS;
	}
	
	protected function clearToken(AuthManager $auth): int
	{
		$progress = Progress::start('Clearing authentication...', 1);
		
		$auth->clearToken();
		
		$progress->hint('Token removed')->advance();
		$progress->finish();
		
		return self::SUCCESS;
	}
	
	protected function runSetup(AuthManager $auth): int
	{
		$progress = Progress::start('Setting up authentication', 1);
		$progress->hint('Authenticating in Claude Code...');
		
		try {
			Progress::suspend();
			
			if (! $auth->setupToken()) {
				error('Authentication setup failed. Ensure `claude` CLI is installed and try again.');
				
				return self::FAILURE;
			}
			
			Progress::resume();
			
			$progress->advance()->hint('Authentication configured');
			
			return self::SUCCESS;
		} finally {
			$progress->finish();
		}
	}
}
