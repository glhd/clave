<?php

namespace App\Pipelines\Steps;

use App\Data\ProjectConfig;
use App\Data\SessionContext;
use Closure;
use Illuminate\Filesystem\Filesystem;

class LoadProjectConfig extends Step
{
	public function __construct(
		protected Filesystem $fs,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->checklist('Loading project config...')
			->run(fn() => $context->project_config = ProjectConfig::fromProjectDir($context->project_dir, $this->fs));

		return $next($context);
	}
}
