<?php

use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use App\Pipelines\Steps\CheckForTart;
use Illuminate\Support\Facades\Process;

beforeEach(function() {
	$this->step = app(CheckForTart::class);
	$this->context = new SessionContext(
		session_id: 'test-123',
		project_name: 'test-project',
		project_dir: '/tmp/test-project',
	);
	$this->next = fn($context) => $context;
});

test('passes when tart is installed', function() {
	Process::fake([
		'which tart' => Process::result(output: '/opt/homebrew/bin/tart'),
	]);

	$result = $this->step->handle($this->context, $this->next);

	expect($result)->toBe($this->context);
});

test('aborts when tart is not installed and brew is available', function() {
	Process::fake([
		'which tart' => Process::result(output: '', exitCode: 1),
		'which brew' => Process::result(output: '/opt/homebrew/bin/brew'),
		'which pkgx' => Process::result(output: '', exitCode: 1),
	]);

	$this->step->handle($this->context, $this->next);
})->throws(AbortedPipelineException::class, 'brew install cirruslabs/cli/tart');

test('aborts when tart is not installed and pkgx is available', function() {
	Process::fake([
		'which tart' => Process::result(output: '', exitCode: 1),
		'which brew' => Process::result(output: '', exitCode: 1),
		'which pkgx' => Process::result(output: '/usr/local/bin/pkgx'),
	]);

	$this->step->handle($this->context, $this->next);
})->throws(AbortedPipelineException::class, 'pkgx install tart');

test('aborts with both instructions when brew and pkgx are available', function() {
	Process::fake([
		'which tart' => Process::result(output: '', exitCode: 1),
		'which brew' => Process::result(output: '/opt/homebrew/bin/brew'),
		'which pkgx' => Process::result(output: '/usr/local/bin/pkgx'),
	]);

	try {
		$this->step->handle($this->context, $this->next);
	} catch (AbortedPipelineException $e) {
		expect($e->getMessage())
			->toContain('brew install cirruslabs/cli/tart')
			->toContain('pkgx install tart');

		return;
	}

	$this->fail('Expected AbortedPipelineException was not thrown.');
});

test('aborts with default instructions when neither brew nor pkgx is available', function() {
	Process::fake([
		'which tart' => Process::result(output: '', exitCode: 1),
		'which brew' => Process::result(output: '', exitCode: 1),
		'which pkgx' => Process::result(output: '', exitCode: 1),
	]);

	try {
		$this->step->handle($this->context, $this->next);
	} catch (AbortedPipelineException $e) {
		expect($e->getMessage())
			->toContain('brew install cirruslabs/cli/tart')
			->toContain('https://tart.run');

		return;
	}

	$this->fail('Expected AbortedPipelineException was not thrown.');
});
