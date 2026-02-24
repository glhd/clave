<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use App\Support\GitManager;
use Closure;

class CreateWorktree
{
	public function __construct(protected GitManager $git)
	{
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$branch = "clave/s-{$context->session_id}";
		$path = $context->project_dir.'/.clave/wt/s-'.$context->session_id;

		$this->git->ensureIgnored($context->project_dir, '.clave/');

		$context->info("Creating worktree: {$branch}");
		$this->git->createWorktree($context->project_dir, $path, $branch);

		$context->worktree_path = $path;
		$context->worktree_branch = $branch;

		return $next($context);
	}
}
