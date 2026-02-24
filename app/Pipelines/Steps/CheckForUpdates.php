<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\UpdateChecker;
use Closure;
use function Laravel\Prompts\warning;

class CheckForUpdates implements Step, ProgressAware
{
	use AcceptsProgress;

	public function __construct(
		protected UpdateChecker $update_checker,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Checking for updates...');

		$current_version = config('app.version');
		$latest = $this->update_checker->check($current_version);

		if ($latest !== null) {
			warning("Clave {$latest} is available (current: {$current_version}). Run `composer global update glhd/clave` to update.");
		}

		return $next($context);
	}
}
