<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\AuthManager;
use Closure;
use function Laravel\Prompts\warning;

class CheckClaudeAuthentication extends Step
{
	public function __construct(
		protected AuthManager $auth,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$has_auth = $this->checklist('Verifying Claude authentication...')
			->run(fn() => $this->auth->hasAuth());

		if (! $has_auth) {
			$this->checklist('Setting up Claude Code token...')
				->run(function() {
					if (! $this->auth->setupToken()) {
						warning('Authentication setup was not completed. Claude on the VM may prompt for login.');
					}
				});
		}

		return $next($context);
	}
}
