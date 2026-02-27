<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\GitManager;
use Closure;

class CloneRepo extends Step
{
	public function __construct(protected GitManager $git)
	{
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		if (! $context->isolate) {
			return $next($context);
		}

		$clone_branch = "clave/s-{$context->session_id}";
		$clone_path = $this->cloneBasePath().'/s-'.$context->session_id;
		
		$this->checklist('Cloning repo for session...')
			->run(fn() => $this->git->cloneLocal($context->project_dir, $clone_path, $context->base_branch, $clone_branch));
		
		$context->clone_path = $clone_path;
		$context->clone_branch = $clone_branch;
		
		return $next($context);
	}
	
	protected function cloneBasePath(): string
	{
		return ($_SERVER['HOME'] ?? getenv('HOME')).'/.clave/repos';
	}
}
