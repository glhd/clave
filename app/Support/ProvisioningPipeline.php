<?php

namespace App\Support;

class ProvisioningPipeline
{
	public static function steps(): array
	{
		return [
			'baseSystem' => [
				'label' => 'Updating base system',
				'commands' => [
					'sudo apt-get update -y',
					'sudo apt-get upgrade -y',
					'sudo apt-get install -y curl git unzip software-properties-common',
				],
			],
			'php' => [
				'label' => 'Installing PHP',
				'commands' => [
					'sudo add-apt-repository -y ppa:ondrej/php',
					'sudo apt-get update -y',
					'sudo apt-get install -y php8.4 php8.4-cli php8.4-common php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-mysql php8.4-redis php8.4-sqlite3 php8.4-bcmath php8.4-gd php8.4-intl',
				],
			],
			'composer' => [
				'label' => 'Installing Composer',
				'commands' => [
					'curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer',
					'composer --version',
				],
			],
			'nginx' => [
				'label' => 'Installing Nginx',
				'commands' => [
					'sudo apt-get install -y nginx',
					'sudo systemctl enable nginx',
				],
			],
			'mysql' => [
				'label' => 'Installing MySQL client',
				'commands' => [
					'sudo apt-get install -y mysql-client',
				],
			],
			'node' => [
				'label' => 'Installing Node.js',
				'commands' => [
					'curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -',
					'sudo apt-get install -y nodejs',
				],
			],
			'claudeCode' => [
				'label' => 'Installing Claude Code',
				'commands' => [
					'sudo -H -u admin bash -c "curl -fsSL https://claude.ai/install.sh | bash"',
					'echo \'export PATH="$HOME/.local/bin:$PATH"\' >> /home/admin/.bashrc',
					'sudo -H -u admin bash -l -c "claude --version"',
				],
			],
			'git' => [
				'label' => 'Configuring git',
				'commands' => [
					'git config --global user.name "Clave"',
					'git config --global user.email "noreply@clave.run"',
				],
			],
			'laravelDirectories' => [
				'label' => 'Creating Laravel directories',
				'commands' => [
					'sudo mkdir -p /srv/project',
					'sudo chown -R admin:admin /srv/project',
				],
			],
			'virtiofsMounts' => [
				'label' => 'Configuring VirtioFS mounts',
				'commands' => [
					'echo "com.apple.virtio-fs.automount /srv/project virtiofs rw,nofail 0 0" | sudo tee -a /etc/fstab',
				],
			],
		];
	}

	public static function hash(array $extra_commands = []): string
	{
		return substr(md5(static::toScript($extra_commands)), 0, 8);
	}

	public static function toScript(array $extra_commands = []): string
	{
		$lines = ['#!/usr/bin/env bash', 'set -euo pipefail', ''];

		foreach (static::steps() as $step) {
			$lines[] = "echo '==> {$step['label']}...'";
			foreach ($step['commands'] as $command) {
				$lines[] = $command;
			}
			$lines[] = '';
		}

		if ($extra_commands) {
			$lines[] = "echo '==> Running project provisioning...'";
			foreach ($extra_commands as $command) {
				$lines[] = $command;
			}
			$lines[] = '';
		}

		return implode("\n", $lines)."\n";
	}
}
