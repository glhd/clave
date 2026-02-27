<?php

namespace App\Support;

use App\Prompts\ChecklistItem;
use function App\checklist;
use function App\header;
use App\Data\OnExit;
use App\Data\SessionContext;
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

		if ($context->upgrade_version_available) {
			warning("A new version of Clave is available (v{$context->upgrade_version_available}). Update with:\n\n  curl -fsSL https://clave.run | sh");
		}
	}

	protected function checklist(string $item): ChecklistItem
	{
		return checklist('Cleaning up virtual machine', $item);
	}

	protected function unproxy(SessionContext $context): void
	{
		if (! $context->proxy_name) {
			return;
		}

		$this->checklist('Removing Herd proxy...')
			->run(fn() => $this->herd->unproxy($context->proxy_name));
	}

	protected function killTunnels(SessionContext $context): void
	{
		if ($context->tunnel_process === null) {
			return;
		}

		$this->checklist('Stopping MCP tunnels...')
			->run(fn() => $context->tunnel_process->stop());
	}

	protected function stopVm(SessionContext $context): void
	{
		if ($context->vm_name === null) {
			return;
		}

		$this->checklist('Stopping VM...')
			->run(fn() => $this->tart->stop($context->vm_name));
	}

	protected function deleteVm(SessionContext $context): void
	{
		if ($context->vm_name === null) {
			return;
		}

		$this->checklist('Deleting VM...')
			->run(fn() => $this->tart->delete($context->vm_name));
	}

	protected function handleClone(SessionContext $context): void
	{
		if ($context->clone_path === null) {
			return;
		}

		if (! $this->git->hasChanges($context->clone_path, $context->base_branch)) {
			$this->checklist('Discarding clone (no changes)...')
				->run(fn() => $this->git->removeClone($context->clone_path));
			return;
		}

		$action = $context->on_exit;

		$action ??= OnExit::coerce(select(
			label: 'What would you like to do with the session changes?',
			options: OnExit::toSelectArray(),
			default: OnExit::Merge->value,
		));

		$label = match ($action) {
			OnExit::Merge => 'Merging and cleaning up clone...',
			OnExit::Discard => 'Discarding clone...',
			default => 'Keeping clone branch',
		};

		$this->checklist($label)
			->run(fn() => match ($action) {
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
			});
	}
}
