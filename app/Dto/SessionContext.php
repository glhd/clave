<?php

namespace App\Dto;

use Illuminate\Process\InvokedProcess;
use Symfony\Component\Console\Output\OutputInterface;

class SessionContext
{
	public ?string $vm_name = null;

	public ?string $vm_ip = null;

	public ?string $worktree_path = null;

	public ?string $worktree_branch = null;

	public ?string $proxy_name = null;

	public ?int $tunnel_port = null;

	public ?InvokedProcess $tunnel_process = null;

	public ?ServiceConfig $services = null;

	public ?OnExit $on_exit = null;

	public function __construct(
		public readonly string $session_id,
		public readonly string $project_name,
		public readonly string $project_dir,
		public readonly string $base_branch,
		public ?OutputInterface $output = null,
	) {
	}

	public function status(string $message): void
	{
		$this->output?->writeln($message);
	}
}
