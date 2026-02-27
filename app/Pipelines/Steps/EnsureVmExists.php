<?php

namespace App\Pipelines\Steps;

use App\Commands\ProvisionCommand;
use App\Data\SessionContext;
use App\Support\TartManager;
use Closure;
use Illuminate\Filesystem\Filesystem;

class EnsureVmExists extends Step
{
	public function __construct(
		protected Filesystem $fs,
		protected TartManager $tart,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$base_vm = $context->project_config->baseVmName();

		$this->hint("Checking for base VM '{$base_vm}'...");

		if (! $this->tart->exists($base_vm)) {
			$this->hint('Provisioning base VM...');

			$args = ['--base-vm' => $base_vm];

			if ($context->project_config->base_image) {
				$args['--image'] = $context->project_config->base_image;
			}

			if ($context->project_config->provision) {
				$args['--provision'] = json_encode($context->project_config->provision);
			}

			if (0 !== $context->command->call(ProvisionCommand::class, $args)) {
				$context->abort('Unable to provision the base VM.');
			}
		}

		return $next($context);
	}
}
