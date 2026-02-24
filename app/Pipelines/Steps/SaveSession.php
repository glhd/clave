<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Models\Session;
use Closure;

class SaveSession implements Step, ProgressAware
{
	use AcceptsProgress;

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Saving session...');
		$context->session = Session::create([
			'session_id' => $context->session_id,
			'project_dir' => $context->project_dir,
			'project_name' => $context->project_name,
			'branch' => $context->base_branch,
			'started_at' => now(),
		]);
		
		return $next($context);
	}
}
