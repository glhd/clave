<?php

namespace App\Pipeline;

use App\Dto\SessionContext;
use App\Services\SshExecutor;
use App\Services\TartManager;
use Closure;
use RuntimeException;

class BootVm
{
	protected int $timeout = 90;

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

		$this->ssh->usePassword(config('clave.ssh.password'));

		$context->status('Waiting for VM IP address...');
		$ip = $this->tart->ip($context->vm_name, $this->timeout);
		$context->vm_ip = $ip;
		$this->ssh->setHost($ip);
		$context->status("  VM IP: {$ip}");

		$context->status('Waiting for SSH...');
		$this->waitForSsh($context);
		$context->status('  SSH ready');

		$this->ssh->run('sudo mount -a');

		return $next($context);
	}

	protected function waitForSsh(SessionContext $context): void
	{
		$start = time();
		$attempts = 0;

		while (time() - $start < $this->timeout) {
			$attempts++;

			if ($this->ssh->test()) {
				return;
			}

			$elapsed = time() - $start;
			$error = $this->ssh->lastError();
			$context->status("  Attempt {$attempts} failed ({$elapsed}s elapsed)"
				.($error ? ": {$error}" : ''));

			sleep(2);
		}

		$error = $this->ssh->lastError();

		throw new RuntimeException(
			"Timed out after {$this->timeout}s waiting for SSH on VM '{$context->vm_name}' at {$context->vm_ip}"
			.($error ? "\nLast error: {$error}" : '')
		);
	}
}
