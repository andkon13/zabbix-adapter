<?php

/**
 * Class CacheAdapter
 * Когда нибуть у нас будет нормальный эеш...
 */
class CacheAdapter
{
	/**
	 * @var array
	 */
	static private $data = array();

	private $sessionId = '0000sessionForCache0000';

	/**
	 * CacheAdapter constructor.
	 */
	public function __construct()
	{
		global $db;
		$data       = $db->fetch('select sessions_data from sessions where sessions_id = "' . $this->sessionId . '"', 0);
		$data       = json_decode($data, true);
		self::$data = ($data) ? $data : array();
	}

	public function __destruct()
	{
		global $db;
		$data = array('sessions_data' => json_encode(self::$data), 'sessions_id' => $this->sessionId);
		$db->insert('sessions', $data, array_keys($data), true);
	}

	/**
	 * @param string $key
	 *
	 * @return bool|mixed
	 */
	public function get($key)
	{
		return (array_key_exists($key, self::$data)) ? self::$data[$key] : false;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function set($key, $value)
	{
		self::$data[$key] = $value;
	}
}
