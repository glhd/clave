<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\SshExecutor;
use App\Support\TartManager;
use Closure;
use RuntimeException;
use Throwable;

class BootVm extends Step
{
	protected int $timeout = 90;
	
	public function __construct(
		protected TartManager $tart,
		protected SshExecutor $ssh,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$mount_path = $context->clone_path ?? $context->project_dir;
		
		$this->checklist("Booting VM '{$context->vm_name}'...")
			->run(fn() => $this->tart->runBackground($context->vm_name, [$mount_path]));

		$this->ssh->usePassword(config('clave.ssh.password'));

		$this->checklist('Waiting for VM IP address...')
			->run(function() use ($context) {
				$ip = $this->tart->ip($context->vm_name, $this->timeout);
				$context->vm_ip = $ip;
				$this->ssh->setHost($ip);
			});

		$this->checklist('Waiting for SSH...')
			->run(fn() => $this->waitForSsh($context));

		$this->checklist('Mounting shared directories...')
			->run(fn() => $this->mountSharedDirectories($context));
		
		return $next($context);
	}
	
	protected function mountSharedDirectories(SessionContext $context): void
	{
		$mount_timeout = 30;
		$start = time();
		$last_error = '';

		$mount_point = '/srv/project';
		$project_dir = $context->project_dir;

		$this->ssh->run("sudo mkdir -p {$mount_point}");

		while (time() - $start < $mount_timeout) {
			try {
				if (! $this->isMounted($mount_point)) {
					$result = $this->ssh->run("sudo mount -t virtiofs com.apple.virtio-fs.automount {$mount_point} 2>&1; true");
					$output = trim($result->output());

					if ($output && $output !== $last_error) {
						$last_error = $output;
					}
				}

				$this->ssh->run("mountpoint -q {$mount_point}");

				$this->ssh->run("sudo mkdir -p {$project_dir}");
				$this->ssh->run("sudo mount --bind {$mount_point} {$project_dir}");

				return;
			} catch (Throwable) {
				sleep(2);
			}
		}

		$diag = $last_error;

		try {
			$result = $this->ssh->run(implode("\n", [
				"echo '=== {$mount_point} ==='",
				"ls -la {$mount_point} 2>&1",
				'echo "=== fstab ==="',
				'cat /etc/fstab 2>&1',
				'echo "=== current mounts ==="',
				'mount 2>&1',
				'echo "=== dmesg virtiofs ==="',
				'dmesg 2>&1 | grep -i virtiofs || echo "(none)"',
				'true',
			]));
			$diag .= "\n".$result->output();
		} catch (Throwable) {
		}

		throw new RuntimeException(
			"Timed out after {$mount_timeout}s waiting for VirtioFS mount on VM '{$context->vm_name}'"
			.($diag ? "\nDiagnostics:\n{$diag}" : '')
		);
	}
	
	protected function isMounted(string $path): bool
	{
		try {
			$this->ssh->run("mountpoint -q {$path}");
			return true;
		} catch (Throwable) {
			return false;
		}
	}

	protected function waitForSsh(SessionContext $context): void
	{
		$start = time();

		while (time() - $start < $this->timeout) {
			if ($this->ssh->test()) {
				return;
			}

			sleep(2);
		}
		
		$error = $this->ssh->lastError();
		
		throw new RuntimeException(
			"Timed out after {$this->timeout}s waiting for SSH on VM '{$context->vm_name}' at {$context->vm_ip}"
			.($error ? "\nLast error: {$error}" : '')
		);
	}
}
