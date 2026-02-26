<?php

use App\Support\ProvisioningPipeline;

test('toScript generates valid bash script', function() {
	$script = ProvisioningPipeline::toScript();

	expect($script)->toStartWith("#!/usr/bin/env bash\n")
		->and($script)->toContain('set -euo pipefail')
		->and($script)->toContain("echo '==> Updating base system...'");
});

test('toScript includes extra commands when provided', function() {
	$extra = [
		'sudo apt-get install -y postgresql',
		'sudo systemctl enable postgresql',
	];

	$script = ProvisioningPipeline::toScript($extra);

	expect($script)->toContain("echo '==> Running project provisioning...'")
		->and($script)->toContain('sudo apt-get install -y postgresql')
		->and($script)->toContain('sudo systemctl enable postgresql');
});

test('toScript does not include project provisioning section without extra commands', function() {
	$script = ProvisioningPipeline::toScript();

	expect($script)->not->toContain('Running project provisioning');
});

test('toScript appends extra commands after standard steps', function() {
	$extra = ['echo "custom step"'];

	$script = ProvisioningPipeline::toScript($extra);

	$standard_pos = strpos($script, 'Configuring VirtioFS mounts');
	$custom_pos = strpos($script, 'Running project provisioning');

	expect($standard_pos)->toBeLessThan($custom_pos);
});
