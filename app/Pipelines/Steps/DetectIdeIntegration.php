<?php

namespace App\Pipelines\Steps;

use App\Data\IdeContext;
use App\Data\SessionContext;
use Closure;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class DetectIdeIntegration extends Step
{
	protected string $home;
	
	public function __construct(
		protected Filesystem $fs,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->checklist('Checking for IDE integration...')
			->run(function() use ($context) {
				$context->ide = $this->detect($context->project_dir);
				
				if ($context->ide) {
					$context->tunnel_ports[] = $context->ide->port;
					$context->tunnel_ports = array_unique($context->tunnel_ports);
				}
			});
		
		return $next($context);
	}
	
	protected function detect(string $project_dir): ?IdeContext
	{
		if ($port = $this->detectFromEnv()) {
			return new IdeContext(port: $port);
		}
		
		return $this->detectFromLockFiles($project_dir);
	}
	
	protected function detectFromEnv(): ?int
	{
		$port = getenv('CLAUDE_CODE_SSE_PORT');
		
		if ($port === false || ! is_numeric($port)) {
			return null;
		}
		
		return (int) $port;
	}
	
	protected function detectFromLockFiles(string $project_dir): ?IdeContext
	{
		$ide_dir = $this->homePath('.claude/ide');
		
		if (! $this->fs->isDirectory($ide_dir)) {
			return null;
		}
		
		$lock_files = $this->fs->glob($ide_dir.'/*.lock');
		
		foreach ($lock_files as $lock_file) {
			$result = $this->parseLockFile($lock_file, $project_dir);
			
			if ($result !== null) {
				return $result;
			}
		}
		
		return null;
	}
	
	protected function parseLockFile(string $lock_file, string $project_dir): ?IdeContext
	{
		try {
			$data = json_decode($this->fs->get($lock_file), true);
			
			if (! is_array($data)) {
				return null;
			}
			
			$workspace_folders = $data['workspaceFolders'] ?? [];
			
			if (! in_array($project_dir, $workspace_folders, true)) {
				return null;
			}
			
			$filename = pathinfo($lock_file, PATHINFO_FILENAME);
			
			if (! is_numeric($filename)) {
				return null;
			}
			
			return new IdeContext(
				port: (int) $filename,
				ide_name: $data['ideName'] ?? 'JetBrains',
				transport: $data['transport'] ?? 'ws',
				auth_token: $data['authToken'] ?? null,
			);
		} catch (Throwable) {
			return null;
		}
	}
	
	protected function homePath(string $path): string
	{
		$this->home ??= ($_SERVER['HOME'] ?? getenv('HOME'));
		
		return $this->home.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
	}
}
