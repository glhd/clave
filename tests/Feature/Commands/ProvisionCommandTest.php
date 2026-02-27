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

test('cleanup deletes stale base VMs after provisioning', function() {
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

	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-base']);
	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-base']);
	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-base-old11111']);
	Process::assertRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-base-old11111']);
});

test('cleanup does not delete current base VM', function() {
	fakeProvisionProcesses([
		['Name' => 'clave-base-abc12345'],
	]);

	$this->artisan('provision', [
		'--base-vm' => 'clave-base-abc12345',
		'--force' => true,
	]);

	// Cleanup calls stop then delete; the pre-rename flow only calls delete.
	// If stop was never called on the current VM, cleanup correctly skipped it.
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-base-abc12345']);
});

test('cleanup does not delete non-base VMs', function() {
	fakeProvisionProcesses([
		['Name' => 'clave-base-abc12345'],
		['Name' => 'clave-session-xyz'],
		['Name' => 'my-other-vm'],
	]);

	$this->artisan('provision', [
		'--base-vm' => 'clave-base-abc12345',
		'--force' => true,
	]);

	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'clave-session-xyz']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'clave-session-xyz']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'stop', 'my-other-vm']);
	Process::assertNotRan(fn(PendingProcess $p) => $p->command === ['tart', 'delete', 'my-other-vm']);
});
