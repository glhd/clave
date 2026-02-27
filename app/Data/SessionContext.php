<?php

namespace App\Data;

use App\Exceptions\AbortedPipelineException;
use LaravelZero\Framework\Commands\Command;

class SessionContext
{
	public ?string $upgrade_version_available = null;

	public ProjectConfig $project_config;

	public ?string $base_branch = null;
	
	public ?string $vm_name = null;
	
	public ?string $vm_ip = null;
	
	public ?string $clone_path = null;
	
	public ?string $clone_branch = null;
	
	public ?string $proxy_name = null;
	
	public ?ServiceConfig $services = null;
	
	public Recipe $recipe = Recipe::Unknown;

	public array $tunnel_ports = [80, 8080, 3306, 6379];

	public mixed $tunnel_process = null;

	public function __construct(
		public readonly string $session_id,
		public readonly string $project_name,
		public readonly string $project_dir,
		public bool $isolate = false,
		public ?OnExit $on_exit = null,
		public ?Command $command = null,
	) {
		$this->project_config = new ProjectConfig();
	}
	
	public function abort(string $message): never
	{
		throw new AbortedPipelineException($message);
	}
}
