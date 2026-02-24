<?php

namespace App\Pipelines\Steps;

use App\Commands\ProvisionCommand;
use App\Dto\SessionContext;
use App\Support\TartManager;
use Closure;
use Illuminate\Filesystem\Filesystem;

class EnsureVmExists
{
	public function __construct(
		protected Filesystem $fs,
		protected TartManager $tart,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$base_vm = config('clave.base_vm');
		
		if (! $this->tart->exists($base_vm)) {
			$context->info("Base VM image '{$base_vm}' not found. Provisioning...");
			
			if (0 !== $context->command->call(ProvisionCommand::class)) {
				$context->abort('Unable to provision the base VM.');
			}
		}
		
		return $next($context);
	}
}
