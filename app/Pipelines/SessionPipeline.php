<?php

namespace App\Pipelines;

use App\Data\SessionContext;
use App\Facades\Progress;
use App\Pipelines\Steps\Step;
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
		
		Progress::start($this->label(), count($steps));
		
		try {
			return $this->send($context)->through($steps)->thenReturn();
		} finally {
			Progress::finish();
		}
	}
	
	protected function carry(): Closure
	{
		return function($stack, $pipe) {
			return function($passable) use ($stack, $pipe) {
				Progress::advance();
				
				try {
					if (! is_string($pipe) || ! is_a($pipe, Step::class, true)) {
						throw new UnexpectedValueException('All steps must implement the Step interface.');
					}
					
					$step = $this->step($pipe);
					
					return $this->handleCarry($step->handle($passable, $stack));
				} catch (Throwable $e) {
					Progress::cleanup();
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
}
