<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Support\AuthManager;
use App\Support\SshExecutor;
use Closure;

class SetupClaudeCode implements Step
{
	use ProvidesProgressHints;

	protected array $home_claude_json = [];

	protected array $home_claude_settings = [];

	public function __construct(
		protected SshExecutor $ssh,
		protected AuthManager $auth,
	) {
	}

	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->home_claude_json = $this->readHomeJson('/.claude.json');
		$this->home_claude_settings = $this->readHomeJson('/.claude/settings.json');

		$this->hint('Writing Claude Code config...');
		$this->writeConfigFiles($this->auth->resolve());

		$this->copyClaudeMd();

		$context->mcp_tunnel_ports = $this->extractMcpPorts();

		return $next($context);
	}

	protected function readHomeJson(string $relative_path): array
	{
		$path = getenv('HOME').$relative_path;

		if (! file_exists($path)) {
			return [];
		}

		return json_decode(file_get_contents($path), true) ?? [];
	}

	protected function writeConfigFiles(?array $resolved): void
	{
		$claude_json = array_filter([
			'autoUpdates' => $this->home_claude_json['autoUpdates'] ?? null,
			'mcpServers' => $this->home_claude_json['mcpServers'] ?? null,
			'showSpinnerTree' => $this->home_claude_json['showSpinnerTree'] ?? null,
			'theme' => $this->home_claude_json['theme'] ?? 'light',
			'hasCompletedOnboarding' => true,
			'shiftEnterKeyBindingInstalled' => true,
		], fn($value) => $value !== null);

		$settings_json = [
			'skipDangerousModePermissionPrompt' => true,
		];

		if (isset($this->home_claude_settings['alwaysThinkingEnabled'])) {
			$settings_json['alwaysThinkingEnabled'] = $this->home_claude_settings['alwaysThinkingEnabled'];
		}

		$claude_json_encoded = base64_encode(json_encode($claude_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		$settings_json_encoded = base64_encode(json_encode($settings_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$command = 'mkdir -p ~/.claude'
			." && echo {$claude_json_encoded} | base64 -d > ~/.claude.json"
			." && echo {$settings_json_encoded} | base64 -d > ~/.claude/settings.json";

		if ($resolved !== null && $resolved['type'] === 'oauth') {
			$credentials = base64_encode(json_encode([
				'claudeAiOauth' => [
					'accessToken' => $resolved['value'],
					'refreshToken' => '',
					'expiresAt' => (time() + 86400 * 365) * 1000,
					'scopes' => ['user:inference', 'user:profile'],
				],
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

			$command .= " && echo {$credentials} | base64 -d > ~/.claude/.credentials.json";
		}

		$this->ssh->run($command);
	}

	protected function extractMcpPorts(): array
	{
		$mcp_servers = $this->home_claude_json['mcpServers'] ?? [];
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

	protected function copyClaudeMd(): void
	{
		$path = getenv('HOME').'/.claude/CLAUDE.md';

		if (! file_exists($path)) {
			return;
		}

		$content = base64_encode(file_get_contents($path));
		$this->ssh->run("echo {$content} | base64 -d > ~/.claude/CLAUDE.md");
	}
}
