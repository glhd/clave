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
					'sudo apt-get install -y curl git unzip software-properties-common jq socat',
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
			// 'phpstorm' => [
			// 	'label' => 'Installing PhpStorm remote dev backend',
			// 	'commands' => [
			// 		<<<'BASH'
			// 		PHPSTORM_URL=$(curl -fsSL 'https://data.services.jetbrains.com/products?code=PS&release.type=release&_p=1&_n=1' \
			// 		  | jq -r '.[0].releases[0].downloads.linuxARM64.link')
			// 		curl -fsSL "$PHPSTORM_URL" | sudo tar xz -C /opt
			// 		sudo mv /opt/PhpStorm-* /opt/phpstorm
			// 		sudo -H -u admin /opt/phpstorm/bin/remote-dev-server.sh registerBackendLocationForGateway
			// 		BASH,
			// 	],
			// ],
			'claudeCode' => [
				'label' => 'Installing Claude Code',
				'commands' => [
					<<<'BASH'
					for attempt in 1 2 3; do
					  if sudo -H -u admin bash -c "curl -fsSL https://claude.ai/install.sh | bash"; then
					    break
					  elif [ "$attempt" -lt 3 ]; then
					    echo "Install attempt $attempt failed, retrying in 30s..."
					    sleep 30
					  else
					    echo "Claude Code installation failed after $attempt attempts"
					    exit 1
					  fi
					done
					BASH,
					'echo \'export PATH="$HOME/.local/bin:$PATH"\' >> /home/admin/.bashrc',
					'sudo -H -u admin bash -l -c "claude --version"',
				],
			],
			'claveProxy' => [
				'label' => 'Installing clave-exec shim runner',
				'commands' => [
					<<<'BASH'
					sudo tee /usr/local/bin/clave-exec > /dev/null << 'SCRIPT'
					#!/usr/bin/env bash
					set -euo pipefail
					
					SOCKET="${CLAVE_PROXY_SOCKET:-/home/admin/.clave/proxy.sock}"
					CMD="$(basename "$0")"
					
					if [[ "$CMD" == "clave-exec" ]]; then
					    CMD="$1"
					    shift
					fi
					
					ARGS_JSON=$([ $# -gt 0 ] && printf '%s\n' "$@" | jq -R . | jq -s . || echo '[]')
					PAYLOAD=$(jq -cn --arg cmd "$CMD" --arg cwd "$(pwd)" --argjson args "$ARGS_JSON" \
					    '{cmd: $cmd, args: $args, cwd: $cwd}')
					
					RESPONSE=$(printf '%s\n' "$PAYLOAD" | socat - "UNIX-CONNECT:${SOCKET}")
					
					{
					    read -r STDOUT_B64
					    read -r STDERR_B64
					    read -r EXIT_CODE
					} < <(printf '%s' "$RESPONSE" | jq -r '(.stdout // "" | @base64), (.stderr // "" | @base64), (.exit_code // 1 | tostring)')
					
					printf '%s' "$STDOUT_B64" | base64 -d
					printf '%s' "$STDERR_B64" | base64 -d >&2
					exit "${EXIT_CODE:-1}"
					SCRIPT
					BASH,
					'sudo chmod +x /usr/local/bin/clave-exec',
					'sudo mkdir -p /home/admin/.clave/shims',
					'sudo chown -R admin:admin /home/admin/.clave',
					'echo \'export PATH="/home/admin/.clave/shims:$PATH"\' | sudo tee /etc/profile.d/clave-shims.sh',
				],
			],
			'git' => [
				'label' => 'Configuring git',
				'commands' => [
					'git config --global user.name "Clave"',
					'git config --global user.email "noreply@clave.run"',
				],
			],
			'projectDirectories' => [
				'label' => 'Creating project directories',
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
