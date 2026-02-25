<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\InstallationManager;
use Closure;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class EnsureTartInstalled implements Step, ProgressAware
{
	use AcceptsProgress;

	public function __construct(
		protected InstallationManager $installation,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Checking for Tart installation...');

		if ($this->installation->isTartInstalled()) {
			return $next($context);
		}

		warning('Tart is not installed. Tart is required to run Clave.');

		if ($this->installation->isHomebrewInstalled()) {
			$choice = select(
				label: 'How would you like to install Tart?',
				options: [
					'auto' => 'Install automatically via Homebrew',
					'manual' => 'Show manual installation instructions',
				],
			);

			if ($choice === 'auto') {
				return $this->installViaBrew($context, $next);
			}
		}

		$this->showManualInstructionsAndAbort($context);
	}

	protected function installViaBrew(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Installing Tart via Homebrew...');

		$result = $this->installation->installTartViaHomebrew();

		if (! $result->successful()) {
			warning('Homebrew installation failed.');
			$this->showManualInstructionsAndAbort($context);
		}

		if (! $this->installation->isTartInstalled()) {
			warning('Tart was installed but is not available in PATH. You may need to restart your shell.');
			$this->showManualInstructionsAndAbort($context);
		}

		info('Tart installed successfully!');

		return $next($context);
	}

	protected function showManualInstructionsAndAbort(SessionContext $context): never
	{
		info($this->installation->getManualInstructions());

		$context->abort('Tart installation is required to continue.');
	}
}
