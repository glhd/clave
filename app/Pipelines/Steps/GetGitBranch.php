<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use App\Support\GitManager;
use Closure;

class GetGitBranch implements Step, ProgressAware
{
	use AcceptsProgress;
	
	public function __construct(
		protected GitManager $git,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Checking for git repo...');
		
		if (! $this->git->isRepo($context->project_dir)) {
			$context->abort('This directory is not a git repository.');
		}
		
		$context->base_branch = $this->git->currentBranch($context->project_dir);
		
		$this->hint("On branch '{$context->base_branch}'");
		
		return $next($context);
	}
}
