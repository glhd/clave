<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use App\Support\TartManager;
use Closure;

class CloneVm
{
	public function __construct(protected TartManager $tart)
	{
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$vm_name = "clave-{$context->session_id}";
		$base_vm = config('clave.base_vm');

		$context->info("Cloning VM: {$base_vm} → {$vm_name}");
		$this->tart->clone($base_vm, $vm_name);
		$this->tart->randomizeMac($vm_name);

		$context->vm_name = $vm_name;

		$cpus = config('clave.vm.cpus');
		$memory = config('clave.vm.memory');
		$display = config('clave.vm.display');

		if ($cpus || $memory || $display) {
			$this->tart->set($vm_name, $cpus, $memory, $display);
		}

		return $next($context);
	}
}
