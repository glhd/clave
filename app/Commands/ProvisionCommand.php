<?php

namespace App\Commands;

use App\Services\ProvisioningPipeline;
use App\Services\SshExecutor;
use App\Services\TartManager;
use LaravelZero\Framework\Commands\Command;

class ProvisionCommand extends Command
{
	protected $signature = 'provision {--force : Re-provision even if base image exists} {--image= : OCI image to pull}';

	protected $description = 'Provision the base VM image for Clave sessions';

	public function handle(TartManager $tart, SshExecutor $ssh): int
	{
		$base_vm = config('clave.base_vm');
		$image = $this->option('image') ?? config('clave.base_image');

		if ($tart->exists($base_vm) && ! $this->option('force')) {
			$this->info("Base image '{$base_vm}' already exists. Use --force to re-provision.");

			return self::SUCCESS;
		}

		if ($tart->exists($base_vm) && $this->option('force')) {
			$this->info("Deleting existing base image '{$base_vm}'...");
			$tart->stop($base_vm);
			$tart->delete($base_vm);
		}

		$this->info("Pulling OCI image: {$image}");
		$tart->clone($image, $base_vm);

		$cpus = config('clave.vm.cpus');
		$memory = config('clave.vm.memory');
		$display = config('clave.vm.display');
		$tart->set($base_vm, $cpus, $memory, $display);

		$this->info('Booting VM for provisioning...');
		$tart->runBackground($base_vm);

		$this->info('Waiting for VM to be ready...');
		$tart->waitForReady($base_vm, $ssh, 120);

		$password = config('clave.ssh.password');
		$ssh->usePassword($password);

		$steps = ProvisioningPipeline::steps();

		foreach ($steps as $key => $step) {
			$this->info("  [{$key}] {$step['label']}...");

			foreach ($step['commands'] as $command) {
				$this->line("    > {$command}");
				$ssh->run($command, 300);
			}
		}

		$this->injectSshKey($ssh);

		$this->info('Stopping VM...');
		$tart->stop($base_vm);

		$this->info('Base image provisioned successfully.');

		return self::SUCCESS;
	}

	protected function injectSshKey(SshExecutor $ssh): void
	{
		$key_path = config('clave.ssh.key');
		$pub_key_path = $key_path.'.pub';

		if (! file_exists($pub_key_path)) {
			$this->warn("Public key not found at {$pub_key_path}. Skipping SSH key injection.");

			return;
		}

		$pub_key = trim(file_get_contents($pub_key_path));

		$this->info('  Injecting SSH public key...');
		$ssh->run("echo '{$pub_key}' >> ~/.ssh/authorized_keys");
	}
}
