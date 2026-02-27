<?php

namespace App\Commands;

use function App\header;
use App\Support\ProvisioningPipeline;
use App\Support\SshExecutor;
use App\Support\TartManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class ProvisionCommand extends Command
{
	protected $signature = 'provision
		{--force : Re-provision even if base image exists}
		{--image= : OCI image to pull}
		{--base-vm= : Name for the provisioned base VM}
		{--provision= : JSON array of extra provisioning commands}';

	protected $description = 'Provision the base VM image for Clave sessions';

	public function handle(
		TartManager $tart,
		SshExecutor $ssh,
		Filesystem $fs,
	): int {
		$base_vm = $this->option('base-vm') ?? config('clave.base_vm');

		if (! $this->option('force') && $tart->exists($base_vm)) {
			note("Base image '{$base_vm}' already exists. Use --force to re-provision.");

			return self::SUCCESS;
		}

		$tmp_id = Str::random(12);
		$tmp_name = "clave-tmp-{$tmp_id}";
		$script_dir = null;

		$this->trap([SIGINT, SIGTERM], function() use ($tart, $tmp_name) {
			warning('Cleaning up...');
			$tart->stop($tmp_name);
			$tart->delete($tmp_name);
			exit(1);
		});

		header('Provisioning Base VM');

		try {
			$image = $this->option('image') ?? config('clave.base_image');
			note("Cloning '{$image}'...");
			$tart->clone($image, $tmp_name);

			note('Configuring VM...');

			$tart->set(
				name: $tmp_name,
				cpus: config('clave.vm.cpus'),
				memory: config('clave.vm.memory'),
				display: config('clave.vm.display')
			);

			$ssh->usePassword(config('clave.ssh.password'));

			$extra_provision = $this->option('provision')
				? json_decode($this->option('provision'), true) ?? []
				: [];

			$script_dir = sys_get_temp_dir().'/clave-provision-'.$tmp_id;
			$fs->ensureDirectoryExists($script_dir, 0700);
			$fs->put("{$script_dir}/provision.sh", ProvisioningPipeline::toScript($extra_provision));

			note('Booting VM...');
			$tart->runBackground($tmp_name, [$script_dir]);

			note('Waiting for VM to be ready...');
			$tart->waitForReady($tmp_name, $ssh, 120);

			note('Provisioning (may take a while)...');
			$ssh->run('sudo mkdir -p /mnt/provision && sudo mount -t virtiofs com.apple.virtio-fs.automount /mnt/provision');
			$ssh->run('sudo bash /mnt/provision/provision.sh', 600);

			note('Stopping VM...');
			$tart->stop($tmp_name);

			note('Finalizing base image...');
			if ($tart->exists($base_vm)) {
				$tart->delete($base_vm);
			}
			$tart->rename($tmp_name, $base_vm);

			$this->cleanupStaleBaseVms($tart, $base_vm);
		} catch (Throwable $e) {
			$tart->stop($tmp_name);
			$tart->delete($tmp_name);

			throw $e;
		} finally {
			if ($script_dir) {
				$fs->deleteDirectory($script_dir);
			}
		}

		return self::SUCCESS;
	}

	protected function cleanupStaleBaseVms(TartManager $tart, string $current_base_vm): void
	{
		$prefix = config('clave.base_vm');

		foreach ($tart->list() as $vm) {
			$name = $vm['Name'];

			if ($name === $current_base_vm) {
				continue;
			}

			if ($name === $prefix || str_starts_with($name, $prefix.'-')) {
				$tart->stop($name);
				$tart->delete($name);
			}
		}
	}
}
