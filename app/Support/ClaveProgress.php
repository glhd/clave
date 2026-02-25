<?php

namespace App\Support;

use BadMethodCallException;
use Illuminate\Support\Traits\ForwardsCalls;
use Laravel\Prompts\Progress;

/** @mixin Progress */
class ClaveProgress
{
	use ForwardsCalls;
	
	protected int $refs = 0;
	
	protected ?Progress $progress = null;
	
	public function start(string $label = '', int $steps = 0): static
	{
		if (0 === $this->refs) {
			$this->progress = new Progress($label, $steps);
			$this->progress->start();
		} else {
			$this->progress->total += $steps;
			$this->progress->render();
		}
		
		$this->refs++;
		
		return $this;
	}
	
	public function advance(int $step = 1): static
	{
		if (! $this->progress) {
			throw new BadMethodCallException('Cannot advance an un-started progress.');
		}
		
		$this->progress->advance($step);
		
		return $this;
	}
	
	public function hint(string $hint): static
	{
		$this->progress?->hint($hint);
		$this->progress?->render();
		
		return $this;
	}
	
	public function finish(): static
	{
		if (0 === $this->refs) {
			throw new BadMethodCallException('Cannot finish an already-finished progress bar.');
		}
		
		$this->refs--;
		
		if (0 === $this->refs) {
			$this->progress->finish();
			$this->progress = null;
		}
		
		return $this;
	}
	
	public function cleanup(): static
	{
		while ($this->refs) {
			$this->finish();
		}
		
		return $this;
	}
	
	public function __call(string $name, array $arguments)
	{
		return $this->forwardDecoratedCallTo($this->progress, $name, $arguments);
	}
}
