<?php

namespace App\Commands;

use App\Dto\OnExit;
use App\Dto\SessionContext;
use App\Models\Session;
use App\Pipeline\BootVm;
use App\Pipeline\CloneVm;
use App\Pipeline\CreateWorktree;
use App\Pipeline\RunClaudeCode;
use App\Services\GitManager;
use App\Services\SessionTeardown;
use App\Services\SshExecutor;
use App\Services\TartManager;
use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class DefaultCommand extends Command
{
	protected $signature = 'default {--on-exit= : Action on exit: keep, merge, discard}';
	
	protected $description = 'Start a Clave session in the current Laravel project';
	
	protected $hidden = true;
	
	public function handle(
		GitManager $git,
		TartManager $tart,
		SshExecutor $ssh,
		SessionTeardown $teardown,
	): int {
		if (! $this->preflight($git, $tart, $ssh)) {
			return self::FAILURE;
		}
		
		$this->call('migrate', ['--force' => true]);
		
		$session_id = Str::random(8);
		$project_dir = getcwd();
		$project_name = basename($project_dir);
		$base_branch = $git->currentBranch($project_dir);
		
		$context = new SessionContext(
			session_id: $session_id,
			project_name: $project_name,
			project_dir: $project_dir,
			base_branch: $base_branch,
			output: $this->output,
		);
		
		$context->on_exit = OnExit::tryFrom($this->option('on-exit') ?? '');
		
		Session::create([
			'session_id' => $session_id,
			'project_dir' => $project_dir,
			'project_name' => $project_name,
			'branch' => $base_branch,
			'started_at' => now(),
		]);
		
		$this->info("Starting Clave session: {$session_id}");
		$this->info("  Project: {$project_name} ({$base_branch})");
		
		$this->trap([SIGINT, SIGTERM], function() use ($context, $teardown) {
			$this->newLine();
			$this->info('Shutting down...');
			$teardown($context, $this);
		});
		
		try {
			Pipeline::send($context)
				->through([
					CreateWorktree::class,
					CloneVm::class,
					BootVm::class,
					RunClaudeCode::class,
				])
				->thenReturn();
		} finally {
			$this->newLine();
			$this->info('Cleaning up...');
			$teardown($context, $this);
		}
		
		return self::SUCCESS;
	}
	
	protected function preflight(GitManager $git, TartManager $tart, SshExecutor $ssh): bool
	{
		$cwd = getcwd();
		
		if (! file_exists($cwd.'/artisan')) {
			$this->error('This does not appear to be a Laravel project (no artisan file found).');
			
			return false;
		}
		
		if (! $git->isRepo($cwd)) {
			$this->error('This directory is not a git repository.');
			
			return false;
		}
		
		$base_vm = config('clave.base_vm');
		if (! $tart->exists($base_vm)) {
			$this->info("Base VM image '{$base_vm}' not found. Provisioning...");
			
			if (self::FAILURE === $this->call('provision')) {
				return false;
			}
		}
		
		if (! file_exists($ssh->keyPath())) {
			$this->error("SSH key not found at {$ssh->keyPath()}.");
			$this->info("Run 'clave provision --force' to generate a key and re-provision the base VM.");
			
			return false;
		}
		
		return true;
	}
}
