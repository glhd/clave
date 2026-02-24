<?php

namespace App\Pipelines;

use App\Dto\SessionContext;
use App\Pipelines\Steps\ProgressAware;
use App\Pipelines\Steps\Step;
use Closure;
use Illuminate\Pipeline\Pipeline;
use Laravel\Prompts\Progress;
use Throwable;
use UnexpectedValueException;
use function Laravel\Prompts\progress;

abstract class SessionPipeline extends Pipeline
{
	protected ?Progress $progress = null;
	
	abstract protected function label(): string;
	
	abstract protected function steps(): array;
	
	public function run(SessionContext $context)
	{
		$steps = $this->steps();
		
		$this->progress = progress($this->label(), count($steps));
		$this->progress->start();
		
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
				$this->progress?->advance();
				
				try {
					if (! is_string($pipe) || ! is_a($pipe, Step::class, true)) {
						throw new UnexpectedValueException('All steps must implement the Step interface.');
					}
					
					$step = $this->step($pipe);

					if ($step instanceof ProgressAware) {
						$step->setProgress($this->progress);
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
		$this->progress?->hint('Done');
		$this->progress?->finish();
		$this->progress = null;
	}
}
