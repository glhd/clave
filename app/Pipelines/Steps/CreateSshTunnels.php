<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\SshExecutor;
use Closure;

class CreateSshTunnels extends Step
{
	public function __construct(
		protected SshExecutor $ssh,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		if (empty($context->tunnel_ports) && $context->proxy_socket_path === null) {
			return $next($context);
		}
		
		$socket_forward = $context->proxy_socket_path !== null
			? '/home/admin/.clave/proxy.sock:'.$context->proxy_socket_path
			: null;
		
		$this->checklist('Creating MCP tunnels...')
			->run(fn() => $context->tunnel_process = $this->ssh->startTunnels($context->tunnel_ports, $socket_forward));
		
		return $next($context);
	}
}
