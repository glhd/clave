<?php

namespace App\Support;

use App\Data\OnExit;
use App\Data\SessionContext;
use App\Models\Session;
use Laravel\Prompts\Progress;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;

class SessionTeardown
{
	public function __construct(
		protected TartManager $tart,
		protected GitManager $git,
		protected HerdManager $herd,
	) {
	}
	
	protected bool $completed = false;
	
	public function run(SessionContext $context): void
	{
		if ($this->completed) {
			return;
		}
		
		$this->completed = true;
		
		$steps = [
			$this->unproxy(...),
			$this->killTunnel(...),
			$this->stopVm(...),
			$this->deleteVm(...),
			$this->handleWorktree(...),
			$this->deleteSession(...),
		];
		
		$progress = progress('Cleaning up...', count($steps));
		
		foreach ($steps as $step) {
			rescue(fn() => $step($context, $progress));
			$progress->advance();
		}
		
		$progress->finish();
	}
	
	protected function unproxy(SessionContext $context, Progress $progress): void
	{
		if (! $context->proxy_name) {
			return;
		}
		
		$this->herd->unproxy($context->proxy_name);
		
		$progress->hint("Removed Herd proxy: {$context->proxy_name}");
	}
	
	protected function killTunnel(SessionContext $context, Progress $progress): void
	{
		if ($context->tunnel_process === null) {
			return;
		}
		
		$context->tunnel_process->stop();
		
		$progress->hint('Stopped SSH tunnel');
	}
	
	protected function stopVm(SessionContext $context, Progress $progress): void
	{
		if ($context->vm_name === null) {
			return;
		}
		
		$this->tart->stop($context->vm_name);
		
		$progress->hint("Stopped VM: {$context->vm_name}");
	}
	
	protected function deleteVm(SessionContext $context, Progress $progress): void
	{
		if ($context->vm_name === null) {
			return;
		}
		
		$this->tart->delete($context->vm_name);
		
		$progress->hint("Deleted VM: {$context->vm_name}");
	}
	
	protected function handleWorktree(SessionContext $context, Progress $progress): void
	{
		if ($context->worktree_path === null) {
			return;
		}
		
		$action = $context->on_exit;
		
		$action ??= OnExit::coerce(select(
			label: 'What would you like to do with the worktree?',
			options: OnExit::toSelectArray(),
			default: OnExit::Keep->value,
		));
		
		match ($action) {
			OnExit::Merge => $this->git->mergeAndCleanWorktree(
				$context->project_dir,
				$context->worktree_path,
				$context->worktree_branch,
				$context->base_branch,
			),
			OnExit::Discard => $this->git->removeWorktree(
				$context->project_dir,
				$context->worktree_path,
			),
			default => null,
		};
		
		$label = match ($action) {
			OnExit::Merge => 'Merged and cleaned up',
			OnExit::Discard => 'Discarded',
			default => 'Kept',
		};
		
		$progress->hint("{$label} worktree: {$context->worktree_branch}");
	}
	
	protected function deleteSession(SessionContext $context, Progress $progress): void
	{
		Session::where('session_id', $context->session_id)->delete();
	}
}
