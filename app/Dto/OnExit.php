<?php

namespace App\Dto;

enum OnExit: string
{
	case Keep = 'keep';
	case Merge = 'merge';
	case Discard = 'discard';
}
