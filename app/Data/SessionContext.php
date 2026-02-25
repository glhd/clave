<?php

namespace App\Data;

use App\Exceptions\AbortedPipelineException;
use App\Models\Session;
use Illuminate\Process\InvokedProcess;
use LaravelZero\Framework\Commands\Command;

class SessionContext
{
	public ?string $upgrade_version_available = null;
	
	public ?string $base_branch = null;
	
	public ?string $vm_name = null;
	
	public ?string $vm_ip = null;
	
	public ?string $clone_path = null;
	
	public ?string $clone_branch = null;
	
	public ?string $proxy_name = null;
	
	public ?int $tunnel_port = null;
	
	public ?InvokedProcess $tunnel_process = null;
	
	public ?ServiceConfig $services = null;
	
	public Recipe $recipe = Recipe::Unknown;

	public ?Session $session = null;
	
	public function __construct(
		public readonly string $session_id,
		public readonly string $project_name,
		public readonly string $project_dir,
		public ?OnExit $on_exit = null,
		public ?Command $command = null,
	) {
	}
	
	public function abort(string $message): never
	{
		throw new AbortedPipelineException($message);
	}
}
