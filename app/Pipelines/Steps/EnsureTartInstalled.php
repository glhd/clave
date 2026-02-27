<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\DependencyManager;
use Closure;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class EnsureTartInstalled extends Step
{
	public function __construct(
		protected DependencyManager $dependencies,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$installed = $this->checklist('Checking for tart...')
			->run(fn() => $this->dependencies->isTartInstalled());
		
		if ($installed) {
			return $next($context);
		}
		
		warning('Tart is not installed. Tart is required to run Clave.');
		
		$options = [];
		
		if ($this->dependencies->isPkgxInstalled()) {
			$options['pkgx'] = 'Install automatically via pkgx/pkgm';
		}
		
		if ($this->dependencies->isHomebrewInstalled()) {
			$options['homebrew'] = 'Install automatically via Homebrew';
		}
		
		$options['manual'] = 'Show manual installation instructions';
		
		$choice = 1 === count($options) ? array_key_first($options) : select('How would you like to install Tart?', $options);
		
		$success = match ($choice) {
			'pkgx' => $this->installViaPkgx(),
			'homebrew' => $this->installViaHomebrew(),
			default => $this->showManualInstructions(),
		};
		
		if (! $success) {
			$context->abort('Tart must be installed before you can use Clave.');
		}
		
		if (! $this->dependencies->isTartInstalled()) {
			$context->abort('Tart is not available in $PATH. You may need to restart your shell.');
		}
		
		return $next($context);
	}
	
	protected function installViaPkgx(): bool
	{
		return $this->checklist('Installing tart via pkgx...')
			->run(fn() => $this->dependencies->installTartViaPkgx());
	}
	
	protected function installViaHomebrew(): bool
	{
		return $this->checklist('Installing Tart via Homebrew...')
			->run(fn() => $this->dependencies->installTartViaHomebrew());
	}
	
	protected function showManualInstructions(): false
	{
		$instructions = <<<'INSTRUCTIONS'
		Tart is not installed. Install it using one of the following methods:

		  Via pkgx:
		    pkgm install tart

		  Via Homebrew:
		    brew install cirruslabs/cli/tart

		  Manually:
		    curl -LO https://github.com/cirruslabs/tart/releases/latest/download/tart.tar.gz
		    tar -xzf tart.tar.gz
		    sudo mv tart /usr/local/bin/
		    sudo chmod +x /usr/local/bin/tart

		For more information, visit: https://tart.run
		INSTRUCTIONS;
		
		note($instructions);
		
		return false;
	}
}
