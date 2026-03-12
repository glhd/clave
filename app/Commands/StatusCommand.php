<?php

namespace App\Commands;

use App\Support\TartManager;
use Illuminate\Filesystem\Filesystem;
use LaravelZero\Framework\Commands\Command;
use function App\user_config_path;

class StatusCommand extends Command
{
	protected $signature = 'status';
	
	protected $description = 'List all Clave session VMs and their state';
	
	public function handle(TartManager $tart, Filesystem $fs): int
	{
		$vms = collect($tart->list())
			->filter(fn(array $vm) => str_starts_with($vm['name'] ?? '', 'clave-')
				&& ! str_starts_with($vm['name'] ?? '', 'clave-base'))
			->values();
		
		if ($vms->isEmpty()) {
			$this->info('No Clave session VMs found.');
			
			return self::SUCCESS;
		}
		
		$rows = $vms->map(function(array $vm) use ($fs) {
			$name = $vm['name'];
			$state = $vm['state'] ?? 'unknown';
			$metadata_path = user_config_path("vms/{$name}.json");
			$project = '-';
			
			if ($fs->exists($metadata_path)) {
				$metadata = json_decode($fs->get($metadata_path), true) ?? [];
				$project = $metadata['project_dir'] ?? '-';
			}
			
			return [$name, $state, $project];
		});
		
		$this->table(['VM Name', 'State', 'Project'], $rows->toArray());
		
		return self::SUCCESS;
	}
}
