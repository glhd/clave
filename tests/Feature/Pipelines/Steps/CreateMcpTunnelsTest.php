<?php

use App\Data\SessionContext;
use App\Pipelines\Steps\CreateSshTunnels;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function makeTunnelContext(): SessionContext
{
	return new SessionContext(
		session_id: 'test-123',
		project_name: 'test-project',
		project_dir: '/path/to/project',
	);
}

test('skips when no mcp tunnel ports', function() {
	Process::fake();

	$step = app(CreateSshTunnels::class);
	$context = makeTunnelContext();
	$context->tunnel_ports = [];

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->tunnel_process)->toBeNull();

	Process::assertNothingRan();
});

test('creates reverse tunnels for mcp ports', function() {
	Process::fake();

	$step = app(CreateSshTunnels::class);
	$context = makeTunnelContext();
	$context->tunnel_ports = [8080, 9090];

	app(\App\Support\SshExecutor::class)->setHost('192.168.64.5');

	$next_called = false;
	$step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($context->tunnel_process)->not->toBeNull();

	Process::assertRan(
		fn(PendingProcess $process) => in_array('-R', $process->command)
		&& in_array('8080:localhost:8080', $process->command)
		&& in_array('9090:localhost:9090', $process->command)
		&& in_array('-N', $process->command)
	);
});
