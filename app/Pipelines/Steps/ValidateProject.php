<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use Closure;
use Illuminate\Filesystem\Filesystem;

class ValidateProject
{
	public function __construct(
		protected Filesystem $fs,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		if ($this->fs->missing($context->project_dir.'/artisan')) {
			$context->abort('This does not appear to be a Laravel project (no artisan file found).');
		}
		
		return $next($context);
	}
}
