<?php

namespace App\Commands;

use App\Prompts\ClaveStatus;
use App\Support\AuthManager;
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
		$status = app(ClaveStatus::class);
		$status->start('Clearing authentication', 1);

		$auth->clearToken();
		$status->advance()->hint('Token removed');
		$status->finish();

		return self::SUCCESS;
	}

	protected function runSetup(AuthManager $auth): int
	{
		$status = app(ClaveStatus::class);
		$status->start('Setting up authentication', 1);

		try {
			if (! $auth->setupToken()) {
				$this->error('Authentication setup failed. Ensure `claude` CLI is installed and try again.');

				return self::FAILURE;
			}

			$status->advance()->hint('Authentication configured');

			return self::SUCCESS;
		} finally {
			$status->finish();
		}
	}
}
