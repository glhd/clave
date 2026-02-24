<?php

namespace App\Pipeline;

use App\Dto\SessionContext;
use App\Services\SshExecutor;
use App\Services\TartManager;
use Closure;

class BootVm
{
	public function __construct(
		protected TartManager $tart,
		protected SshExecutor $ssh,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$mount_path = $context->worktree_path ?? $context->project_dir;

		$context->status("Booting VM: {$context->vm_name}");
		$this->tart->runBackground($context->vm_name, [
			'project' => $mount_path,
		]);

		$context->status('Waiting for VM to be ready...');
		$this->tart->waitForReady($context->vm_name, $this->ssh);

		$context->vm_ip = $this->tart->ip($context->vm_name);
		$context->status("  VM ready at {$context->vm_ip}");

		return $next($context);
	}
}
