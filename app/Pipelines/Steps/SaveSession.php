<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use App\Models\Session;
use Closure;

class SaveSession implements Step
{
	public function handle(SessionContext $context, Closure $next): mixed
	{
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
