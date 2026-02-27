<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\AuthManager;
use App\Support\SshExecutor;
use Closure;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class SetupClaudeCode extends Step
{
	protected string $home;
	
	public function __construct(
		protected SshExecutor $ssh,
		protected AuthManager $auth,
		protected Filesystem $fs,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->checklist('Writing Claude Code config...')
			->run(function() use ($context) {
				$config = $this->readConfig('.claude.json');
				$settings = $this->readConfig('.claude/settings.json');
				$md = $this->readConfig('.claude/CLAUDE.md');

				$this->writeConfigFiles($config, $settings, $md, $this->auth->resolve());

				$context->tunnel_ports = array_unique(array_merge($context->tunnel_ports, $this->extractMcpPorts($config)));
			});
		
		return $next($context);
	}
	
	protected function readConfig(string $relative_path, ?bool $json = null): string|array
	{
		$json ??= str_ends_with($relative_path, '.json');
		
		try {
			$path = $this->homePath($relative_path);
			
			if (! file_exists($path)) {
				return $json ? [] : '';
			}
			
			return $json ? $this->fs->json($path) : $this->fs->get($path);
		} catch (Throwable) {
			return $json ? [] : '';
		}
	}
	
	protected function writeConfigFiles(array $config, array $settings, string $md, ?array $auth): void
	{
		$claude_json = array_filter([
			'autoUpdates' => $config['autoUpdates'] ?? null,
			'mcpServers' => $config['mcpServers'] ?? null,
			'showSpinnerTree' => $config['showSpinnerTree'] ?? null,
			'theme' => $config['theme'] ?? 'light',
			'hasCompletedOnboarding' => true,
			'shiftEnterKeyBindingInstalled' => true,
		], fn($value) => $value !== null);
		
		$settings_json = array_filter([
			'skipDangerousModePermissionPrompt' => true,
			'alwaysThinkingEnabled' => $settings['alwaysThinkingEnabled'] ?? null,
		], fn($value) => $value !== null);
		
		$claude_json_encoded = base64_encode(json_encode($claude_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		$settings_json_encoded = base64_encode(json_encode($settings_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		
		$command = 'mkdir -p ~/.claude'
			." && echo {$claude_json_encoded} | base64 -d > ~/.claude.json"
			." && echo {$settings_json_encoded} | base64 -d > ~/.claude/settings.json";
		
		if ($auth !== null && $auth['type'] === 'oauth') {
			$credentials_encoded = base64_encode(json_encode([
				'claudeAiOauth' => [
					'accessToken' => $auth['value'],
					'refreshToken' => '',
					'expiresAt' => (time() + 86400 * 365) * 1000,
					'scopes' => ['user:inference', 'user:profile'],
				],
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			
			$command .= " && echo {$credentials_encoded} | base64 -d > ~/.claude/.credentials.json";
		}
		
		if (! empty($md)) {
			$md_encoded = base64_encode($md);
			$command .= " && echo {$md_encoded} | base64 -d > ~/.claude/CLAUDE.md";
		}
		
		$this->ssh->run($command);
	}
	
	protected function extractMcpPorts(array $config): array
	{
		$mcp_servers = $config['mcpServers'] ?? [];
		$ports = [];
		
		foreach ($mcp_servers as $server) {
			$url = $server['url'] ?? null;
			
			if ($url === null) {
				continue;
			}
			
			$parsed = parse_url($url);
			$host = $parsed['host'] ?? null;
			$port = $parsed['port'] ?? null;
			
			if ($port !== null && in_array($host, ['localhost', '127.0.0.1'])) {
				$ports[] = $port;
			}
		}
		
		return array_unique($ports);
	}
	
	protected function homePath(string $path): string
	{
		$this->home ??= ($_SERVER['HOME'] ?? getenv('HOME'));
		
		return $this->home.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
	}
}
