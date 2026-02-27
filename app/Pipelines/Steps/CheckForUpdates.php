<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use Closure;
use Illuminate\Support\Facades\Http;

class CheckForUpdates extends Step
{
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->checklist('Checking for updates…')
			->run(function() use ($context) {
				try {
					$response = Http::timeout(3)->get('https://api.github.com/repos/glhd/clave/releases/latest');
					
					$latest_version = ltrim($response->json('tag_name') ?? '', 'v');
					$current_version = ltrim(config('app.version'), 'v');
					
					if ($latest_version && version_compare($current_version, $latest_version, '<')) {
						$context->upgrade_version_available = $latest_version;
					}
				} catch (\Throwable) {
					// Silently ignore network failures
				}
			});
		
		return $next($context);
	}
}
