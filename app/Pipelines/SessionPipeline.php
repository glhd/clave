<?php

namespace App\Pipelines;

use App\Data\SessionContext;
use App\Prompts\ChecklistItem;
use function App\checklist;
use function App\header;
use App\Pipelines\Steps\Step;
use Closure;
use Illuminate\Pipeline\Pipeline;
use UnexpectedValueException;

abstract class SessionPipeline extends Pipeline
{
	abstract protected function label(): string;

	abstract protected function steps(): array;

	public function __invoke(SessionContext $context)
	{
		return $this->send($context)->through($this->steps())->thenReturn();
	}

	protected function carry(): Closure
	{
		return function($stack, $pipe) {
			return function($passable) use ($stack, $pipe) {
				if (! is_string($pipe) || ! is_a($pipe, Step::class, true)) {
					throw new UnexpectedValueException('All steps must implement the Step interface.');
				}

				$step = $this->step($pipe);
				$step->setPipelineName($this->label());

				return $this->handleCarry($step->handle($passable, $stack));
			};
		};
	}

	/** @param class-string<Step> $pipe */
	protected function step(string $pipe): Step
	{
		return $this->getContainer()->make($pipe);
	}
}
