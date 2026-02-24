<?php

namespace App\Support;

use Illuminate\Support\Str;
use Random\Randomizer;

/** @mixin \BackedEnum */
trait EnumHelpers
{
	public static function names(): array
	{
		return array_column(self::cases(), 'name');
	}
	
	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}
	
	public static function coerce(string|int|self $value): self
	{
		return $value instanceof self ? $value : self::from($value);
	}
	
	public static function tryCoerce(string|int|null|self $value): ?self
	{
		if (null === $value) {
			return $value;
		}
		
		return $value instanceof self ? $value : self::tryFrom($value);
	}
	
	public static function random(): self
	{
		$cases = self::cases();
		
		$keys = (new Randomizer())->pickArrayKeys($cases, 1);
		
		return $cases[$keys[0]];
	}
	
	public static function toSelectArray(): array
	{
		$cases = self::cases();
		$result = [];
		
		foreach ($cases as $case) {
			$result[$case->value] = $case->label();
		}
		
		return $result;
	}
	
	public function label(): string
	{
		return Str::headline($this->name);
	}
	
	public function is(string|int|null|self $other): bool
	{
		return static::tryCoerce($other) === $this;
	}
	
	public function isNot(string|int|self $other): bool
	{
		return ! $this->is($other);
	}
	
	/** @param iterable<int|string|self> $values */
	public function in(iterable $values): bool
	{
		foreach ($values as $value) {
			if ($this->is($value)) {
				return true;
			}
		}
		
		return false;
	}
	
	/** @param iterable<int|string|self> $values */
	public function notIn(iterable $values): bool
	{
		foreach ($values as $value) {
			if ($this->is($value)) {
				return false;
			}
		}
		
		return true;
	}
}
