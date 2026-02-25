<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\AuthManager;
use App\Support\SshExecutor;
use Closure;

class SetupClaudeCode implements Step, ProgressAware
{
	use AcceptsProgress;
	
	public function __construct(
		protected SshExecutor $ssh,
		protected AuthManager $auth,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$resolved = $this->auth->resolve();
		
		if ($resolved !== null && $resolved['type'] === 'oauth') {
			$this->hint('Writing credentials file...');
			$this->writeCredentialsFile($resolved['value']);
		}
		
		return $next($context);
	}
	
	protected function writeCredentialsFile(string $token): void
	{
		$credentials = base64_encode(json_encode([
			'claudeAiOauth' => [
				'accessToken' => $token,
				'refreshToken' => '',
				'expiresAt' => (time() + 86400 * 365) * 1000,
				'scopes' => ['user:inference', 'user:profile'],
			],
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		
		$settings = base64_encode(json_encode([
			'hasCompletedOnboarding' => true,
			'shiftEnterKeyBindingInstalled' => true,
			'theme' => 'light',
			'skipDangerousModePermissionPrompt' => true,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		
		// It's unclear whether .claude.json and .claude/settings.json are necessary, but
		// we'll write both just in case each matters for different versions of Claude Code.
		$this->ssh->run(
			'mkdir -p ~/.claude'
			." && echo {$credentials} | base64 -d > ~/.claude/.credentials.json"
			." && echo {$settings} | base64 -d > ~/.claude.json"
			." && echo {$settings} | base64 -d > ~/.claude/settings.json"
		);
	}
}
