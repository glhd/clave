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
		if (empty($context->tunnel_ports)) {
			return $next($context);
		}
		
		$this->hint('Creating MCP tunnels...');
		
		$context->tunnel_process = $this->ssh->startTunnels($context->tunnel_ports);
		
		return $next($context);
	}
}
