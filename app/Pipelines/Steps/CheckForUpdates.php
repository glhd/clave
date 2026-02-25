<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use Closure;
use Illuminate\Support\Facades\Http;
use function Laravel\Prompts\warning;

class CheckForUpdates implements Step
{
	use ProvidesProgressHints;

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Checking for updates...');

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

		return $next($context);
	}
}
