<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\TartManager;
use Closure;

class CloneVm implements Step
{
	use ProvidesProgressHints;
	
	public function __construct(protected TartManager $tart)
	{
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$vm_name = "clave-{$context->session_id}";
		$base_vm = config('clave.base_vm');
		
		$this->hint("Cloning VM '{$base_vm}'...");
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
