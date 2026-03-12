<?php

namespace App\Agents;

use App\Data\SessionContext;
use App\Support\AuthManager;
use App\Support\SshExecutor;

class ClaudeCode
{
	protected const array ALLOWED_ENV_VARS = [
		'COLORTERM',
		'FORCE_COLOR',
		'GIT_AUTHOR_EMAIL',
		'GIT_AUTHOR_NAME',
		'GIT_COMMITTER_EMAIL',
		'GIT_COMMITTER_NAME',
		'LANG',
		'LC_ALL',
		'LC_CTYPE',
		'NO_COLOR',
		'TZ',
		'VISUAL',
	];

	public function __construct(
		protected SshExecutor $ssh,
		protected AuthManager $auth,
	) {
	}

	public function __invoke(SessionContext $context): void
	{
		$env = $this->env($context);
		$model = $context->project_config->model;
		
		$flags = '--dangerously-skip-permissions';

		if ($model) {
			$flags .= ' --model '.escapeshellarg($model);
		}

		foreach ($context->claude_flags as $flag) {
			$flags .= ' '.escapeshellarg($flag);
		}
		
		$inner = "cd {$context->project_dir} && {$env}claude {$flags}";
		
		$this->ssh->interactive('bash -l -c '.escapeshellarg($inner));
	}
	
	protected function env(SessionContext $context): string
	{
		$allowed = array_unique([
			...self::ALLOWED_ENV_VARS,
			...$context->project_config->env,
		]);
		
		$env = [];
		
		foreach ($allowed as $var) {
			$value = getenv($var);
			if ($value !== false) {
				$env[] = $var.'='.escapeshellarg($value);
			}
		}
		
		if ($resolved = $this->auth->resolve()) {
			$env_var = match ($resolved['type']) {
				'api_key' => 'ANTHROPIC_API_KEY',
				'oauth' => 'CLAUDE_CODE_OAUTH_TOKEN',
			};
			$env[] = $env_var.'='.escapeshellarg($resolved['value']);
		}
		
		if ($context->ide) {
			$env[] = 'CLAUDE_CODE_SSE_PORT='.escapeshellarg((string) $context->ide->port);
			$env[] = 'ENABLE_IDE_INTEGRATION=true';
		}
		
		return $env ? implode(' ', $env).' ' : '';
	}
}
