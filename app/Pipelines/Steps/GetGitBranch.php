<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\GitManager;
use Closure;

class GetGitBranch extends Step
{
	public function __construct(
		protected GitManager $git,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->checklist('Checking for git repo...')
			->run(function() use ($context) {
				if (! $this->git->isRepo($context->project_dir)) {
					$context->abort('This directory is not a git repository.');
				}

				$context->base_branch = $this->git->currentBranch($context->project_dir);
			});
		
		return $next($context);
	}
}
