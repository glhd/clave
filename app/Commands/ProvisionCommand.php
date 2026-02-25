<?php

namespace App\Commands;

use App\Prompts\ClaveStatus;
use App\Support\ProvisioningPipeline;
use App\Support\SshExecutor;
use App\Support\TartManager;
use LaravelZero\Framework\Commands\Command;

class ProvisionCommand extends Command
{
	protected $signature = 'provision {--force : Re-provision even if base image exists} {--image= : OCI image to pull}';

	protected $description = 'Provision the base VM image for Clave sessions';

	public function handle(TartManager $tart, SshExecutor $ssh): int
	{
		$base_vm = config('clave.base_vm');
		$image = $this->option('image') ?? config('clave.base_image');

		if (! $this->option('force') && $tart->exists($base_vm)) {
			$this->info("Base image '{$base_vm}' already exists. Use --force to re-provision.");

			return self::SUCCESS;
		}

		$tmp_name = 'clave-tmp-'.bin2hex(random_bytes(4));
		$script_dir = null;

		$this->trap([SIGINT, SIGTERM], function() use ($tart, $tmp_name) {
			$this->newLine();
			$this->warn('Interrupted — cleaning up temp VM...');
			$tart->stop($tmp_name);
			$tart->delete($tmp_name);
			exit(1);
		});

		$status = app(ClaveStatus::class);
		$status->start('Provisioning base VM', 8);

		try {
			$status->advance()->hint('Pulling OCI image...');
			$tart->clone($image, $tmp_name);

			$status->advance()->hint('Configuring VM...');
			$cpus = config('clave.vm.cpus');
			$memory = config('clave.vm.memory');
			$display = config('clave.vm.display');
			$tart->set($tmp_name, $cpus, $memory, $display);

			$password = config('clave.ssh.password');
			$ssh->usePassword($password);

			$script_dir = sys_get_temp_dir().'/clave-provision-'.bin2hex(random_bytes(4));
			mkdir($script_dir, 0700, true);
			file_put_contents($script_dir.'/provision.sh', ProvisioningPipeline::toScript());

			$status->advance()->hint('Booting VM...');
			$tart->runBackground($tmp_name, [$script_dir]);

			$status->advance()->hint('Waiting for VM to be ready...');
			$tart->waitForReady($tmp_name, $ssh, 120);

			$status->advance()->hint('Mounting provisioning script...');
			$ssh->run('sudo mkdir -p /mnt/provision && sudo mount -t virtiofs com.apple.virtio-fs.automount /mnt/provision');

			$status->advance()->hint('Running provisioning script...');
			$ssh->run('sudo bash /mnt/provision/provision.sh', 600);

			$status->advance()->hint('Stopping VM...');
			$tart->stop($tmp_name);

			$status->advance()->hint('Finalizing base image...');
			if ($tart->exists($base_vm)) {
				$tart->delete($base_vm);
			}
			$tart->rename($tmp_name, $base_vm);
		} catch (\Throwable $e) {
			$tart->stop($tmp_name);
			$tart->delete($tmp_name);

			throw $e;
		} finally {
			if ($script_dir) {
				@unlink($script_dir.'/provision.sh');
				@rmdir($script_dir);
			}
			$status->finish();
		}

		return self::SUCCESS;
	}
}
