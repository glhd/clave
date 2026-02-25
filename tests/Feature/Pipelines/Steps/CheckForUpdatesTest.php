<?php

use App\Data\SessionContext;
use App\Pipelines\Steps\CheckForUpdates;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function makeUpdateContext(): SessionContext
{
	return new SessionContext(
		session_id: 'test-123',
		project_name: 'test-project',
		project_dir: '/path/to/project',
	);
}

test('passes through silently when current version matches latest', function() {
	$current_version = config('app.version');

	Http::fake([
		'api.github.com/repos/glhd/clave/releases/latest' => Http::response([
			'tag_name' => "v{$current_version}",
		]),
	]);


	$step = app(CheckForUpdates::class);
	$context = makeUpdateContext();

	$next_called = false;
	$result = $step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($result)->toBe($context);
	expect($context->upgrade_version_available)->toBeNull();

	Http::assertSentCount(1);
});

test('stores upgrade version when newer version is available', function() {
	Http::fake([
		'api.github.com/repos/glhd/clave/releases/latest' => Http::response([
			'tag_name' => 'v99.99.99',
		]),
	]);


	$step = app(CheckForUpdates::class);
	$context = makeUpdateContext();

	$next_called = false;
	$result = $step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($result)->toBe($context);
	expect($context->upgrade_version_available)->toBe('99.99.99');

	Http::assertSentCount(1);
});

test('passes through silently when the HTTP request fails', function() {
	Http::fake(fn() => throw new ConnectionException('Connection timed out'));

	$step = app(CheckForUpdates::class);
	$context = makeUpdateContext();

	$next_called = false;
	$result = $step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($result)->toBe($context);
	expect($context->upgrade_version_available)->toBeNull();
});

test('passes through silently when response is missing tag_name', function() {
	Http::fake([
		'api.github.com/repos/glhd/clave/releases/latest' => Http::response([
			'message' => 'Not Found',
		]),
	]);


	$step = app(CheckForUpdates::class);
	$context = makeUpdateContext();

	$next_called = false;
	$result = $step->handle($context, function($ctx) use (&$next_called) {
		$next_called = true;

		return $ctx;
	});

	expect($next_called)->toBeTrue();
	expect($result)->toBe($context);
	expect($context->upgrade_version_available)->toBeNull();

	Http::assertSentCount(1);
});
