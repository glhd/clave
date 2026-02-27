<?php

namespace App\Commands;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;
use Phar;
use function App\heading;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class UpdateCommand extends Command
{
	use Colors;
	
	protected $signature = 'update';
	
	protected $description = 'Update Clave to the latest version';
	
	public function handle(): int
	{
		heading('Updating clave');
		
		if (! $phar_path = $this->pharPath()) {
			return $this->unavailable();
		}
		
		$current_version = ltrim(config('app.version'), 'v');
		
		try {
			$response = Http::timeout(10)->get('https://api.github.com/repos/glhd/clave/releases/latest');
		} catch (ConnectionException) {
			error('Could not reach GitHub. Check your internet connection and try again.');
			
			return $this->unavailable();
		}
		
		$latest_version = ltrim($response->json('tag_name') ?? '', 'v');
		
		if (! $latest_version) {
			return $this->unavailable();
		}
		
		if (version_compare($current_version, $latest_version, '>=')) {
			info("Already up to date (v{$current_version}).");
			
			return self::SUCCESS;
		}
		
		note("Updating Clave from v{$current_version} to v{$latest_version}...");
		
		$tag = "v{$latest_version}";
		$base_url = "https://github.com/glhd/clave/releases/download/{$tag}";
		
		$temp_path = $phar_path.'.tmp.'.getmypid();
		
		try {
			$download = Http::withOptions(['sink' => $temp_path])
				->timeout(60)
				->get("{$base_url}/clave.phar");
			
			if ($download->failed()) {
				error('Failed to download the update.');
				@unlink($temp_path);
				
				return self::FAILURE;
			}
			
			$checksum_response = Http::timeout(10)->get("{$base_url}/clave.phar.sha256");
			
			if ($checksum_response->successful()) {
				$expected_hash = trim(explode(' ', $checksum_response->body())[0]);
				$actual_hash = hash_file('sha256', $temp_path);
				
				if ($expected_hash !== $actual_hash) {
					error('Checksum verification failed. The download may be corrupted.');
					@unlink($temp_path);
					
					return self::FAILURE;
				}
			}
			
			chmod($temp_path, fileperms($phar_path));
			
			if (is_writable(dirname($phar_path)) && is_writable($phar_path)) {
				rename($temp_path, $phar_path);
			} else {
				exec('sudo mv '.escapeshellarg($temp_path).' '.escapeshellarg($phar_path), $_, $exit_code);
				
				if (0 !== $exit_code) {
					error('Failed to replace binary (permission denied). Try: sudo clave update');
					@unlink($temp_path);
					
					return self::FAILURE;
				}
			}
		} catch (\Throwable $e) {
			error("Update failed: {$e->getMessage()}");
			@unlink($temp_path);
			
			return self::FAILURE;
		}
		
		info("Successfully updated to v{$latest_version}.");
		
		return self::SUCCESS;
	}
	
	protected function pharPath(): string|false
	{
		if ($override = config('clave.phar_path')) {
			return $override;
		}
		
		return Phar::running(false) ?: false;
	}
	
	protected function unavailable(): int
	{
		note(
			<<<EOF
			{$this->red('Whoops...')} self-update is not available.
			
			To update clave, please run:
			
			  {$this->yellow('curl -fsSL https://clave.run | sh')}
			
			EOF
		);
		
		return self::FAILURE;
	}
}
