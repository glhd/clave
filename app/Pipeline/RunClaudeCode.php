<?php

namespace App\Pipeline;

use App\Dto\SessionContext;
use App\Services\SshExecutor;
use Closure;

class RunClaudeCode
{
	public function __construct(protected SshExecutor $ssh)
	{
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$api_key = config('clave.anthropic_api_key');
		$project_dir = '/srv/project';

		$context->status('Starting Claude Code session...');

		$env = $api_key ? "ANTHROPIC_API_KEY={$api_key} " : '';

		$this->ssh->interactive(
			"cd {$project_dir} && {$env}claude --dangerously-skip-permissions"
		);

		return $next($context);
	}
}
