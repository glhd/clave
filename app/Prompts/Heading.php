<?php

namespace App\Prompts;

use App\Prompts\Renderers\HeaderRenderer;
use Laravel\Prompts\Prompt;

class Heading extends Prompt
{
	public function __construct(
		public string $message
	) {
	}
	
	public function display(): void
	{
		$this->prompt();
	}
	
	public function prompt(): bool
	{
		$this->capturePreviousNewLines();
		
		$this->state = 'submit';
		
		static::output()->write($this->renderTheme());
		
		return true;
	}
	
	public function value(): bool
	{
		return true;
	}
	
	protected function getRenderer(): callable
	{
		return new HeaderRenderer($this);
	}
}
