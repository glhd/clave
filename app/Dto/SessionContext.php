<?php

namespace App\Dto;

use App\Exceptions\AbortedPipelineException;
use App\Models\Session;
use Illuminate\Process\InvokedProcess;
use LaravelZero\Framework\Commands\Command;

class SessionContext
{
	public ?string $base_branch = null;
	
	public ?string $vm_name = null;
	
	public ?string $vm_ip = null;
	
	public ?string $worktree_path = null;
	
	public ?string $worktree_branch = null;
	
	public ?string $proxy_name = null;
	
	public ?int $tunnel_port = null;
	
	public ?InvokedProcess $tunnel_process = null;
	
	public ?ServiceConfig $services = null;
	
	public ?Session $session = null;
	
	public function __construct(
		public readonly string $session_id,
		public readonly string $project_name,
		public readonly string $project_dir,
		public ?OnExit $on_exit = null,
		public ?Command $command = null,
	) {
	}
	
	public function info(string $message): void
	{
		$this->command?->info($message);
	}
	
	public function warn($message): void
	{
		$this->command?->warn($message);
	}
	
	public function error(string $message): void
	{
		$this->command?->error($message);
	}
	
	public function abort(string $message): never
	{
		throw new AbortedPipelineException($message);
	}
}
