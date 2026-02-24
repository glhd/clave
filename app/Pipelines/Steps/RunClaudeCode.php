<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\AuthManager;
use App\Support\SshExecutor;
use Closure;

class RunClaudeCode implements Step, ProgressAware
{
	use AcceptsProgress;

	public function __construct(
		protected SshExecutor $ssh,
		protected AuthManager $auth,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$project_dir = '/srv/project';

		$this->hint('Starting Claude Code...');

		$resolved = $this->auth->resolve();

		if ($resolved !== null && $resolved['type'] === 'oauth') {
			$this->hint('Writing credentials file...');
			$this->writeCredentialsFile($resolved['value']);
		}

		$env = '';
		if ($resolved !== null) {
			$env_var = match ($resolved['type']) {
				'api_key' => 'ANTHROPIC_API_KEY',
				'oauth' => 'CLAUDE_CODE_OAUTH_TOKEN',
			};
			$env = $env_var.'='.$resolved['value'].' ';
		}

		$inner = "cd {$project_dir} && {$env}claude --dangerously-skip-permissions";
		
		$this->progress->finish();

		$this->ssh->interactive(
			'bash -l -c '.escapeshellarg($inner)
		);

		return $next($context);
	}

	protected function writeCredentialsFile(string $token): void
	{
		$credentials = base64_encode(json_encode([
			'claudeAiOauth' => [
				'accessToken' => $token,
				'refreshToken' => '',
				'expiresAt' => (time() + 86400 * 365) * 1000,
				'scopes' => ['user:inference', 'user:profile'],
			],
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$settings = base64_encode(json_encode([
			'hasCompletedOnboarding' => true,
			'shiftEnterKeyBindingInstalled' => true,
			'theme' => 'light',
			'skipDangerousModePermissionPrompt' => true,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$this->ssh->run(
			'mkdir -p ~/.claude'
			." && echo {$credentials} | base64 -d > ~/.claude/.credentials.json"
			." && echo {$settings} | base64 -d > ~/.claude.json"
			." && echo {$settings} | base64 -d > ~/.claude/settings.json"
		);
	}
}
