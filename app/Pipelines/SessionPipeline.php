<?php

namespace App\Pipelines;

use App\Data\SessionContext;
use App\Pipelines\Steps\ProgressAware;
use App\Pipelines\Steps\Step;
use App\Prompts\ClaveStatus;
use Closure;
use Illuminate\Pipeline\Pipeline;
use Throwable;
use UnexpectedValueException;

abstract class SessionPipeline extends Pipeline
{
	abstract protected function label(): string;

	abstract protected function steps(): array;

	public function __invoke(SessionContext $context)
	{
		$steps = $this->steps();

		$status = app(ClaveStatus::class);
		$status->start($this->label(), count($steps));

		try {
			return $this->send($context)->through($steps)->thenReturn();
		} finally {
			$this->clearProgress();
		}
	}

	protected function carry(): Closure
	{
		return function($stack, $pipe) {
			return function($passable) use ($stack, $pipe) {
				$status = app(ClaveStatus::class);
				$status->advance();

				try {
					if (! is_string($pipe) || ! is_a($pipe, Step::class, true)) {
						throw new UnexpectedValueException('All steps must implement the Step interface.');
					}

					$step = $this->step($pipe);

					if ($step instanceof ProgressAware) {
						$step->setProgress($status);
					}

					return $this->handleCarry($step->handle($passable, $stack));
				} catch (Throwable $e) {
					$this->clearProgress();
					return $this->handleException($passable, $e);
				}
			};
		};
	}

	/** @param class-string<Step> $pipe */
	protected function step(string $pipe): Step
	{
		return $this->getContainer()->make($pipe);
	}

	protected function clearProgress(): void
	{
		app(ClaveStatus::class)->finish('Done');
	}
}
