<?php

namespace App\Commands;

use App\Support\TartManager;
use Illuminate\Filesystem\Filesystem;
use LaravelZero\Framework\Commands\Command;
use function App\home_path;
use function Laravel\Prompts\confirm;

class CleanCommand extends Command
{
	protected $signature = 'clean
		{--all : Also delete base VMs}
		{--force : Skip confirmation}';
	
	protected $description = 'Stop and delete all suspended/stopped Clave session VMs';
	
	public function handle(TartManager $tart, Filesystem $fs): int
	{
		$include_base = (bool) $this->option('all');
		
		$vms = collect($tart->list())
			->filter(function(array $vm) use ($include_base) {
				$name = $vm['name'] ?? '';
				
				if (! str_starts_with($name, 'clave-')) {
					return false;
				}
				
				if (! $include_base && str_starts_with($name, 'clave-base')) {
					return false;
				}
				
				$state = $vm['state'] ?? '';
				
				return in_array($state, ['suspended', 'stopped']);
			})
			->values();
		
		if ($vms->isEmpty()) {
			$this->info('No VMs to clean up.');
			
			return self::SUCCESS;
		}
		
		$this->table(
			['VM Name', 'State'],
			$vms->map(fn(array $vm) => [$vm['name'], $vm['state']])->toArray(),
		);
		
		if (! $this->option('force') && ! confirm("Delete {$vms->count()} VM(s)?", default: false)) {
			$this->info('Cancelled.');
			
			return self::SUCCESS;
		}
		
		foreach ($vms as $vm) {
			$name = $vm['name'];
			$this->info("Deleting {$name}...");
			$tart->delete($name);

			$metadata_path = home_path(".config/clave/vms/{$name}.json");
			if ($fs->exists($metadata_path)) {
				$fs->delete($metadata_path);
			}
		}
		
		$this->info('Done.');
		
		return self::SUCCESS;
	}
}
