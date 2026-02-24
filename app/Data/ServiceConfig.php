<?php

namespace App\Data;

class ServiceConfig
{
	public static function hostServices(string $gateway_ip): self
	{
		return new self(
			mysql_host: $gateway_ip,
			mysql_port: 3306,
			redis_host: $gateway_ip,
			redis_port: 6379,
		);
	}

	public static function localServices(): self
	{
		return new self(
			mysql_host: '127.0.0.1',
			mysql_port: 3306,
			redis_host: '127.0.0.1',
			redis_port: 6379,
		);
	}

	public function __construct(
		public readonly string $mysql_host,
		public readonly int $mysql_port,
		public readonly string $redis_host,
		public readonly int $redis_port,
	) {
	}
}
