<?php

namespace App\Services;

use App\Dto\OnExit;
use App\Dto\SessionContext;
use App\Models\Session;
use Illuminate\Console\Command;
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
	
	public function __invoke(SessionContext $context, ?Command $command = null): void
	{
		if ($this->completed) {
			return;
		}
		
		$this->completed = true;
		
		$this->unproxy($context);
		$this->killTunnel($context);
		$this->stopVm($context);
		$this->deleteVm($context);
		$this->handleWorktree($context, $command);
		$this->deleteSession($context);
	}
	
	protected function unproxy(SessionContext $context): void
	{
		if ($context->proxy_name === null) {
			return;
		}
		
		rescue(fn() => $this->herd->unproxy($context->proxy_name));
		$context->status("  Removed Herd proxy: {$context->proxy_name}");
	}
	
	protected function killTunnel(SessionContext $context): void
	{
		if ($context->tunnel_process === null) {
			return;
		}
		
		rescue(fn() => $context->tunnel_process->stop());
		$context->status('  Stopped SSH tunnel');
	}
	
	protected function stopVm(SessionContext $context): void
	{
		if ($context->vm_name === null) {
			return;
		}
		
		rescue(fn() => $this->tart->stop($context->vm_name));
		$context->status("  Stopped VM: {$context->vm_name}");
	}
	
	protected function deleteVm(SessionContext $context): void
	{
		if ($context->vm_name === null) {
			return;
		}
		
		rescue(fn() => $this->tart->delete($context->vm_name));
		$context->status("  Deleted VM: {$context->vm_name}");
	}
	
	protected function handleWorktree(SessionContext $context, ?Command $command): void
	{
		if ($context->worktree_path === null) {
			return;
		}
		
		$action = $context->on_exit;
		
		if ($action === null && $command !== null) {
			$action = OnExit::coerce(select(
				label: 'What would you like to do with the worktree?',
				options: OnExit::toSelectArray(),
				default: OnExit::Keep->value,
			));
		}
		
		$action ??= OnExit::Keep;
		
		rescue(function() use ($context, $action) {
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
			
			$context->status("  {$label} worktree: {$context->worktree_branch}");
		});
	}
	
	protected function deleteSession(SessionContext $context): void
	{
		rescue(fn() => Session::where('session_id', $context->session_id)->delete());
	}
}
