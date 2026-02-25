<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\TartManager;
use Closure;
use Illuminate\Support\Facades\Process;

class CheckForTart implements Step, ProgressAware
{
	use AcceptsProgress;

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Checking for Tart...');

		if (! TartManager::isInstalled()) {
			$context->abort($this->installationMessage());
		}

		return $next($context);
	}

	protected function installationMessage(): string
	{
		$has_brew = Process::run('which brew')->successful();
		$has_pkgx = Process::run('which pkgx')->successful();

		$message = "Tart is required but not installed.\n\nInstall it with:\n";

		if ($has_brew) {
			$message .= "  brew install cirruslabs/cli/tart\n";
		}

		if ($has_pkgx) {
			$message .= "  pkgx install tart\n";
		}

		if (! $has_brew && ! $has_pkgx) {
			$message .= "  brew install cirruslabs/cli/tart\n";
			$message .= "\nSee https://tart.run for more information.";
		}

		return $message;
	}
}
