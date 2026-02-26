<?php

namespace App\Support;

use App\Data\OnExit;
use App\Data\SessionContext;
use function App\header;
use App\Models\Session;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class SessionTeardown
{
	protected bool $completed = false;

	public function __construct(
		protected TartManager $tart,
		protected GitManager $git,
		protected HerdManager $herd,
	) {
	}

	public function __invoke(SessionContext $context): void
	{
		if ($this->completed) {
			return;
		}

		$this->completed = true;

		header('Cleaning Up');

		rescue(fn() => $this->unproxy($context));
		rescue(fn() => $this->killTunnels($context));
		rescue(fn() => $this->stopVm($context));
		rescue(fn() => $this->deleteVm($context));
		rescue(fn() => $this->handleClone($context));
		rescue(fn() => $this->deleteSession($context));

		if ($context->upgrade_version_available) {
			warning("A new version of Clave is available (v{$context->upgrade_version_available}). Update with:\n\n  curl -fsSL https://clave.run | sh");
		}
	}

	protected function unproxy(SessionContext $context): void
	{
		if (! $context->proxy_name) {
			return;
		}

		$this->herd->unproxy($context->proxy_name);

		note("Removed Herd proxy: {$context->proxy_name}");
	}

	protected function killTunnels(SessionContext $context): void
	{
		if ($context->tunnel_process === null) {
			return;
		}

		$context->tunnel_process->stop();

		note('Stopped MCP tunnels');
	}

	protected function stopVm(SessionContext $context): void
	{
		if ($context->vm_name === null) {
			return;
		}

		$this->tart->stop($context->vm_name);

		note("Stopped VM: {$context->vm_name}");
	}

	protected function deleteVm(SessionContext $context): void
	{
		if ($context->vm_name === null) {
			return;
		}

		$this->tart->delete($context->vm_name);

		note("Deleted VM: {$context->vm_name}");
	}

	protected function handleClone(SessionContext $context): void
	{
		if ($context->clone_path === null) {
			return;
		}

		if (! $this->git->hasChanges($context->clone_path, $context->base_branch)) {
			$this->git->removeClone($context->clone_path);
			note("Discarded (no changes): {$context->clone_branch}");
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

		note("{$label}: {$context->clone_branch}");
	}

	protected function deleteSession(SessionContext $context): void
	{
		Session::where('session_id', $context->session_id)->delete();
	}
}
