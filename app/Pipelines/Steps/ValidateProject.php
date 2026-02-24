<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use Closure;
use Illuminate\Filesystem\Filesystem;

class ValidateProject implements Step, ProgressAware
{
	use AcceptsProgress;
	
	public function __construct(
		protected Filesystem $fs,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Checking whether project is supported...');
		
		if ($this->fs->missing($context->project_dir.'/artisan')) {
			$context->abort('This does not appear to be a Laravel project (no artisan file found).');
		}
		
		return $next($context);
	}
}
