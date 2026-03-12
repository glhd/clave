<?php

namespace App\Prompts\Renderers;

use App\Prompts\Heading;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Laravel\Prompts\Themes\Default\Renderer;

class HeaderRenderer extends Renderer
{
	use InteractsWithStrings;

	public function __invoke(Heading $header): string
	{
		$width = $header->terminal()->cols() - 6;
		$lines = explode(PHP_EOL, $this->mbWordwrap($header->message, $width - 2));
		
		$draw = ['h' => '─', 'v' => '│', 'tl' => '╭', 'tr' => '╮', 'bl' => '╰', 'br' => '╯'];
		
		$this->line($this->cyan($draw['tl'].str_repeat($draw['h'], $width).$draw['tr']));
		
		foreach ($lines as $line) {
			$line = $this->pad($line, $width - 2);
			$this->line($this->cyan("{$draw['v']} ").$this->white($line).$this->cyan(" {$draw['v']}"));
		}
		
		$this->line($this->cyan($draw['bl'].str_repeat($draw['h'], $width).$draw['br']));
		
		return (string) $this;
	}
}
