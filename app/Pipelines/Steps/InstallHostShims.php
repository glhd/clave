<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\SshExecutor;
use Closure;
use Illuminate\Support\Facades\Process;

class InstallHostShims extends Step
{
	public function __construct(
		protected SshExecutor $ssh,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$shims = array_unique(array_merge(
			config('clave.proxy.shims', []),
			$context->project_config->shims,
		));
		
		if (empty($shims)) {
			return $next($context);
		}
		
		$socket_path = '/tmp/clave-'.$context->session_id.'.sock';
		
		$this->checklist('Starting host proxy daemon...')
			->run(function() use ($socket_path, $shims, &$context) {
				$shim_args = array_merge(...array_map(fn($shim) => ['--shim', $shim], $shims));
				
				$context->proxy_daemon_process = Process::start(array_merge(
					[PHP_BINARY, base_path('clave'), 'proxy:daemon', '--socket', $socket_path],
					$shim_args,
				));
				
				$deadline = microtime(true) + 5.0;
				while (! file_exists($socket_path)) {
					if (microtime(true) > $deadline) {
						$context->proxy_daemon_process->stop();
						$context->proxy_daemon_process = null;
						throw new \RuntimeException('Proxy daemon did not start within 5 seconds');
					}
					usleep(100_000);
				}
				
				$context->proxy_socket_path = $socket_path;
			});
		
		$symlinks = implode(' ',
			array_map(
				fn($shim) => '&& ln -sf /usr/local/bin/clave-exec /home/admin/.clave/shims/'.escapeshellarg($shim),
				$shims,
			));
		
		$this->checklist('Installing host shims...')
			->run(fn() => $this->ssh->run(
				"mkdir -p /home/admin/.clave/shims {$symlinks}",
			));
		
		return $next($context);
	}
}
