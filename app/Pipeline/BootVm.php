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
		$context->status("  Sharing: {$mount_path}");
		$this->tart->runBackground($context->vm_name, [$mount_path]);

		$this->ssh->usePassword(config('clave.ssh.password'));

		$context->status('Waiting for VM IP address...');
		$ip = $this->tart->ip($context->vm_name, $this->timeout);
		$context->vm_ip = $ip;
		$this->ssh->setHost($ip);
		$context->status("  VM IP: {$ip}");

		$context->status('Waiting for SSH...');
		$this->waitForSsh($context);
		$context->status('  SSH ready');

		$context->status('Mounting shared directories...');
		$this->mountSharedDirectories($context);
		$context->status('  Mounted /srv/project');

		return $next($context);
	}

	protected function mountSharedDirectories(SessionContext $context): void
	{
		$mount_timeout = 30;
		$start = time();
		$last_error = '';

		$this->ssh->run('sudo mkdir -p /srv/project');

		while (time() - $start < $mount_timeout) {
			try {
				$result = $this->ssh->run('sudo mount -t virtiofs com.apple.virtio-fs.automount /srv/project 2>&1; true');
				$output = trim($result->output());

				if ($output && $output !== $last_error) {
					$last_error = $output;
					$context->status("  Mount: {$output}");
				}

				$this->ssh->run('mountpoint -q /srv/project');

				return;
			} catch (\Throwable) {
				$elapsed = time() - $start;
				$context->status("  Waiting for VirtioFS mount ({$elapsed}s)...");
				sleep(2);
			}
		}

		$diag = $last_error;

		try {
			$result = $this->ssh->run(implode("\n", [
				'echo "=== /srv/project ==="',
				'ls -la /srv/ 2>&1',
				'echo "=== fstab ==="',
				'cat /etc/fstab 2>&1',
				'echo "=== current mounts ==="',
				'mount 2>&1',
				'echo "=== dmesg virtiofs ==="',
				'dmesg 2>&1 | grep -i virtiofs || echo "(none)"',
				'true',
			]));
			$diag .= "\n".$result->output();
		} catch (\Throwable) {
		}

		throw new RuntimeException(
			"Timed out after {$mount_timeout}s waiting for VirtioFS mount on VM '{$context->vm_name}'"
			.($diag ? "\nDiagnostics:\n{$diag}" : '')
		);
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
