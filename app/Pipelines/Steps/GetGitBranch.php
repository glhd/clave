<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use App\Support\GitManager;
use Closure;
use Illuminate\Filesystem\Filesystem;

class GetGitBranch
{
	public function __construct(
		protected Filesystem $fs,
		protected GitManager $git,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		if (! $this->git->isRepo($context->project_dir)) {
			$context->abort('This directory is not a git repository.');
		}
		
		$context->base_branch = $this->git->currentBranch($context->project_dir);
		
		return $next($context);
	}
}
