<?php

namespace App\Pipelines\Steps;

use App\Commands\ProvisionCommand;
use App\Data\SessionContext;
use App\Support\TartManager;
use Closure;
use Illuminate\Filesystem\Filesystem;

class EnsureVmExists implements Step
{
	use ProvidesProgressHints;
	
	public function __construct(
		protected Filesystem $fs,
		protected TartManager $tart,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$base_vm = config('clave.base_vm');
		
		$this->hint("Checking for base VM '{$base_vm}'...");
		
		if (! $this->tart->exists($base_vm)) {
			$this->hint('Provisioning base VM...');
			
			if (0 !== $context->command->call(ProvisionCommand::class)) {
				$context->abort('Unable to provision the base VM.');
			}
		}
		
		return $next($context);
	}
}
