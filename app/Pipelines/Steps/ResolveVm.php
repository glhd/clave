<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use function App\home_path;
use App\Support\TartManager;
use Closure;
use Illuminate\Filesystem\Filesystem;

class ResolveVm extends Step
{
	public function __construct(
		protected TartManager $tart,
		protected Filesystem $fs,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$vm_name = SessionContext::vmNameForProject($context->project_dir);
		$state = $this->tart->state($vm_name);
		$base_vm = $context->project_config->baseVmName();
		
		if ($context->fresh) {
			$this->handleFresh($context, $vm_name, $state, $base_vm);
		} elseif ($state === 'suspended') {
			$this->handleSuspended($context, $vm_name, $base_vm);
		} elseif ($state === 'running') {
			$this->handleRunning($context, $base_vm);
		} elseif ($state === 'stopped') {
			$this->handleStopped($context, $vm_name, $base_vm);
		} else {
			$this->cloneFresh($context, $vm_name, $base_vm);
		}
		
		return $next($context);
	}
	
	protected function handleFresh(SessionContext $context, string $vm_name, ?string $state, string $base_vm): void
	{
		if ($state !== null) {
			$this->checklist("Removing existing VM '{$vm_name}'...")
				->run(function() use ($vm_name, $state) {
					if ($state === 'running') {
						$this->tart->stop($vm_name);
					}
					$this->tart->delete($vm_name);
				});
		}
		
		$this->cloneFresh($context, $vm_name, $base_vm);
	}
	
	protected function handleSuspended(SessionContext $context, string $vm_name, string $base_vm): void
	{
		$metadata = $this->readMetadata($vm_name);
		
		if (($metadata['base_vm'] ?? null) !== $base_vm) {
			$this->checklist('Base VM changed, replacing suspended VM...')
				->run(fn() => $this->tart->delete($vm_name));
			
			$this->cloneFresh($context, $vm_name, $base_vm);
			return;
		}
		
		$this->checklist("Resuming suspended VM '{$vm_name}'...")
			->run(fn() => null);
		
		$context->vm_name = $vm_name;
		$context->resumed = true;
	}
	
	protected function handleRunning(SessionContext $context, string $base_vm): void
	{
		$ephemeral_name = "clave-{$context->session_id}";
		
		$this->checklist('VM already running, creating ephemeral VM...')
			->run(function() use ($ephemeral_name, $base_vm) {
				$this->tart->clone($base_vm, $ephemeral_name);
				$this->tart->randomizeMac($ephemeral_name);
			});
		
		$context->vm_name = $ephemeral_name;
		$context->ephemeral = true;
		
		$this->configureVm($context, $ephemeral_name);
	}
	
	protected function handleStopped(SessionContext $context, string $vm_name, string $base_vm): void
	{
		$this->checklist("Removing stopped VM '{$vm_name}'...")
			->run(fn() => $this->tart->delete($vm_name));
		
		$this->cloneFresh($context, $vm_name, $base_vm);
	}
	
	protected function cloneFresh(SessionContext $context, string $vm_name, string $base_vm): void
	{
		$this->checklist("Cloning VM '{$base_vm}'...")
			->run(function() use ($base_vm, $vm_name) {
				$this->tart->clone($base_vm, $vm_name);
				$this->tart->randomizeMac($vm_name);
			});
		
		$context->vm_name = $vm_name;
		
		$this->configureVm($context, $vm_name);
		$this->writeMetadata($vm_name, $base_vm, $context->project_dir);
	}
	
	protected function configureVm(SessionContext $context, string $vm_name): void
	{
		$cpus = $context->project_config->cpus ?? config('clave.vm.cpus');
		$memory = $context->project_config->memory ?? config('clave.vm.memory');
		$display = config('clave.vm.display');
		
		if ($cpus || $memory || $display) {
			$this->tart->set($vm_name, $cpus, $memory, $display);
		}
	}
	
	protected function metadataPath(string $vm_name): string
	{
		return home_path(".config/clave/vms/{$vm_name}.json");
	}
	
	protected function readMetadata(string $vm_name): array
	{
		$path = $this->metadataPath($vm_name);
		
		if (! $this->fs->exists($path)) {
			return [];
		}
		
		return json_decode($this->fs->get($path), true) ?? [];
	}
	
	protected function writeMetadata(string $vm_name, string $base_vm, string $project_dir): void
	{
		$path = $this->metadataPath($vm_name);
		
		$this->fs->ensureDirectoryExists(dirname($path));
		$this->fs->put($path, json_encode([
			'base_vm' => $base_vm,
			'project_dir' => $project_dir,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
}
