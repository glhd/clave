<?php

namespace App\Support;

use App\Data\OnExit;
use App\Data\SessionContext;
use App\Models\Session;
use Laravel\Prompts\Progress;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class SessionTeardown
{
	public function __construct(
		protected TartManager $tart,
		protected GitManager $git,
		protected HerdManager $herd,
	) {
	}
	
	protected bool $completed = false;
	
	public function __invoke(SessionContext $context): void
	{
		if ($this->completed) {
			return;
		}
		
		$this->completed = true;
		
		$steps = [
			$this->unproxy(...),
			$this->killMcpTunnels(...),
			$this->killTunnel(...),
			$this->stopVm(...),
			$this->deleteVm(...),
			$this->handleClone(...),
			$this->deleteSession(...),
		];
		
		$progress = progress('Cleaning up...', count($steps));
		
		foreach ($steps as $step) {
			rescue(fn() => $step($context, $progress));
			$progress->advance();
		}
		
		$progress->finish();
		
		if ($context->upgrade_version_available) {
			warning("A new version of Clave is available (v{$context->upgrade_version_available}). Update with:\n\n  curl -fsSL https://clave.run | sh");
		}
	}
	
	protected function unproxy(SessionContext $context, Progress $progress): void
	{
		if (! $context->proxy_name) {
			return;
		}
		
		$this->herd->unproxy($context->proxy_name);
		
		$progress->hint("Removed Herd proxy: {$context->proxy_name}");
	}
	
	protected function killMcpTunnels(SessionContext $context, Progress $progress): void
	{
		if ($context->mcp_tunnel_process === null) {
			return;
		}

		$context->mcp_tunnel_process->stop();

		$progress->hint('Stopped MCP tunnels');
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
	
	protected function handleClone(SessionContext $context, Progress $progress): void
	{
		if ($context->clone_path === null) {
			return;
		}
		
		if (! $this->git->hasChanges($context->clone_path, $context->base_branch)) {
			$this->git->removeClone($context->clone_path);
			$progress->hint("Discarded (no changes): {$context->clone_branch}");
			return;
		}
		
		$action = $context->on_exit;
		
		$action ??= OnExit::coerce(select(
			label: 'What would you like to do with the session changes?',
			options: OnExit::toSelectArray(),
			default: OnExit::Merge->value,
		));
		
		match ($action) {
			OnExit::Merge => $this->git->mergeAndCleanClone(
				$context->project_dir,
				$context->clone_path,
				$context->clone_branch,
				$context->base_branch,
			),
			OnExit::Discard => $this->git->removeClone(
				$context->clone_path,
			),
			default => null,
		};
		
		$label = match ($action) {
			OnExit::Merge => 'Merged and cleaned up',
			OnExit::Discard => 'Discarded',
			default => 'Kept',
		};
		
		$progress->hint("{$label}: {$context->clone_branch}");
	}
	
	protected function deleteSession(SessionContext $context, Progress $progress): void
	{
		Session::where('session_id', $context->session_id)->delete();
	}
}
