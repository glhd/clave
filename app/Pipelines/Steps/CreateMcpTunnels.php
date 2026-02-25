<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\SshExecutor;
use Closure;

class CreateMcpTunnels implements Step
{
	use ProvidesProgressHints;
	
	public function __construct(
		protected SshExecutor $ssh,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		if (empty($context->mcp_tunnel_ports)) {
			return $next($context);
		}
		
		$this->hint('Creating MCP tunnels...');
		
		$context->mcp_tunnel_process = $this->ssh->reverseTunnels($context->mcp_tunnel_ports);
		
		return $next($context);
	}
}
