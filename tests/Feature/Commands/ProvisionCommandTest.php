<?php

use App\Support\SshExecutor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function fakeProvisionProcesses(array $vm_list): void
{
	Process::fake(fn(PendingProcess $process) => match (true) {
		$process->command === ['tart', 'list', '--format', 'json'] => Process::result(output: json_encode($vm_list)),
		$process->command[0] === 'tart' && $process->command[1] === 'ip' => Process::result(output: "192.168.64.10\n"),
		default => Process::result(output: 'ok'),
	});
}

beforeEach(function() {
	$mock_ssh = Mockery::mock(SshExecutor::class);
	$mock_ssh->shouldReceive('usePassword')->andReturnSelf();
	$mock_ssh->shouldReceive('setHost')->andReturnSelf();
	$mock_ssh->shouldReceive('test')->andReturn(true);
	$mock_ssh->shouldReceive('run')->andReturnNull();
	$this->app->instance(SshExecutor::class, $mock_ssh);
});

test('provisioning does not delete other base VMs', function() {
	fakeProvisionProcesses([
		['Name' => 'clave-base-abc12345'],
		['Name' => 'clave-base'],
		['Name' => 'clave-base-old11111'],
		['Name' => 'clave-session-xyz'],
	]);

	$this->artisan('provision', [
		'--base-vm' => 'clave-base-abc12345',
		'--force' => true,
	]);

	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-base']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-base']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-base-old11111']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-base-old11111']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-session-xyz']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-session-xyz']);
});

test('provisioning replaces existing base VM with same name', function() {
	fakeProvisionProcesses([
		['Name' => 'clave-base-abc12345'],
	]);

	$this->artisan('provision', [
		'--base-vm' => 'clave-base-abc12345',
		'--force' => true,
	]);

	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-base-abc12345']);
});
