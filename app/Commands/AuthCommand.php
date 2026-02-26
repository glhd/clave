<?php

namespace App\Commands;

use App\Support\AuthManager;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;
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
		$auth->clearToken();

		note('Token removed');

		return self::SUCCESS;
	}

	protected function runSetup(AuthManager $auth): int
	{
		note('Authenticating in Claude Code...');

		if (! $auth->setupToken()) {
			error('Authentication setup failed. Ensure `claude` CLI is installed and try again.');

			return self::FAILURE;
		}

		note('Authentication configured');

		return self::SUCCESS;
	}
}
