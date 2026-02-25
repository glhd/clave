<?php

namespace App\Agents;

use App\Data\SessionContext;
use App\Support\AuthManager;
use App\Support\SshExecutor;

class ClaudeCode
{
	public function __construct(
		protected SshExecutor $ssh,
		protected AuthManager $auth,
	) {
	}
	
	public function __invoke(SessionContext $context): void
	{
		$project_dir = '/srv/project';
		
		$env = '';
		if ($resolved = $this->auth->resolve()) {
			$env_var = match ($resolved['type']) {
				'api_key' => 'ANTHROPIC_API_KEY',
				'oauth' => 'CLAUDE_CODE_OAUTH_TOKEN',
			};
			$env = $env_var.'='.$resolved['value'].' ';
		}
		
		$inner = "cd {$project_dir} && {$env}claude --dangerously-skip-permissions";
		
		$this->ssh->interactive('bash -l -c '.escapeshellarg($inner));
	}
}
