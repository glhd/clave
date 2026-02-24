<?php

namespace App\Data;

use App\Support\EnumHelpers;
use Illuminate\Support\Str;

enum OnExit: string
{
	use EnumHelpers;
	
	case Keep = 'keep';
	case Merge = 'merge';
	case Discard = 'discard';
	
	public function label(): string
	{
		return match ($this) {
			OnExit::Keep => 'Keep clone',
			OnExit::Merge => 'Merge and clean up',
			OnExit::Discard => 'Discard changes',
			default => Str::headline($this->name),
		};
	}
}
