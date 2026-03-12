<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use Closure;
use function Laravel\Prompts\note;

class PrintGatewayLink extends Step
{
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$user = config('clave.ssh.user');
		$port = config('clave.ssh.port');
		$params = http_build_query([
			'type' => 'ssh',
			'deploy' => 'false',
			'host' => "{$user}@{$context->vm_ip}",
			'port' => $port,
			'projectPath' => '/srv/project',
			'idePath' => '/opt/phpstorm',
		]);
		
		$link = "jetbrains-gateway://connect#{$params}";
		
		note("JetBrains Gateway: {$link}");
		
		return $next($context);
	}
}
