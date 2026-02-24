<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use App\Support\AuthManager;
use Closure;

class CheckClaudeAuthentication implements Step, ProgressAware
{
	use AcceptsProgress;

	public function __construct(
		protected AuthManager $auth,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Verifying Claude authentication...');

		if (! $this->auth->hasAuth()) {
			$this->hint('Setting up Claude Code token...');
			
			if (! $this->auth->setupToken()) {
				$context->warn('Authentication setup was not completed. Claude on the VM may prompt for login.');
			}
		}
		
		return $next($context);
	}
}
