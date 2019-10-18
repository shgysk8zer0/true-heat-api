<?php

class Template
{
	const PREFIX = '{%';

	const SUFFIX = '%}';

	private $_data = [];

	private $_file    = '';

	final public function __construct(string $fname)
	{
		if (file_exists($fname)) {
			$this->_file = file_get_contents($fname);
		} else {
			throw new \Exception("File not found: {$fname}");
		}
	}

	final public function __isset(string $key): bool
	{
		return array_key_exists($this->_convert($key), $this->_data);
	}

	final public function __unset(string $key): void
	{
		unset($this->_data[$this->_convert($key)]);
	}

	final public function __get(string $key):? string
	{
		return $this->_data[$this->_convert($key)] ?? null;
	}

	final public function __set(string $key, string $value): void
	{
		$this->_data[$this->_convert($key)] = $value;
	}

	final public function __toString(): string
	{
		return str_replace(array_keys($this->_data), array_values($this->_data), $this->_file);
	}

	final private function _convert(string $in): string
	{
		return self::PREFIX . strtoupper($in) . self::SUFFIX;
	}
}
