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

		$tmp_name = 'clave-tmp-'.bin2hex(random_bytes(4));

		$this->trap([SIGINT, SIGTERM], function() use ($tart, $tmp_name) {
			$this->newLine();
			$this->warn('Interrupted — cleaning up temp VM...');
			$tart->stop($tmp_name);
			$tart->delete($tmp_name);
			exit(1);
		});

		$this->info("Pulling OCI image: {$image}");
		$tart->clone($image, $tmp_name);

		$cpus = config('clave.vm.cpus');
		$memory = config('clave.vm.memory');
		$display = config('clave.vm.display');
		$tart->set($tmp_name, $cpus, $memory, $display);

		$password = config('clave.ssh.password');
		$ssh->usePassword($password);

		$script_dir = sys_get_temp_dir().'/clave-provision-'.bin2hex(random_bytes(4));
		mkdir($script_dir, 0700, true);
		file_put_contents($script_dir.'/provision.sh', ProvisioningPipeline::toScript());

		try {
			$this->info('Booting VM for provisioning...');
			$tart->runBackground($tmp_name, ['provision' => $script_dir]);

			$this->info('Waiting for VM to be ready...');
			$tart->waitForReady($tmp_name, $ssh, 120);

			$this->info('Mounting provisioning script...');
			$ssh->run('sudo mkdir -p /mnt/provision && sudo mount -t virtiofs provision /mnt/provision');

			$this->info('Running provisioning script...');
			$ssh->run('sudo bash /mnt/provision/provision.sh', 600);

			$this->info('Stopping VM...');
			$tart->stop($tmp_name);
		} catch (\Throwable $e) {
			$this->error('Provisioning failed: '.$e->getMessage());
			$tart->stop($tmp_name);
			$tart->delete($tmp_name);

			throw $e;
		} finally {
			@unlink($script_dir.'/provision.sh');
			@rmdir($script_dir);
		}

		if ($tart->exists($base_vm)) {
			$this->info("Replacing existing base image '{$base_vm}'...");
			$tart->delete($base_vm);
		}

		$tart->rename($tmp_name, $base_vm);

		$this->info('Base image provisioned successfully.');

		return self::SUCCESS;
	}
}
