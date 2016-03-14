<?php

/**
 * Class NodeStatistic
 */
class NodeStatistic
{
	const CPU_LOAD_KEY = 'system.cpu.util[,idle]';
	const MEM_LOAD_KEY = 'vm.memory.size[available]';
	const MEM_TOTAL_KEY = 'vm.memory.size[total]';
	const NET_PACKETS_IN_KEY = 'net.if.in[eth0,packets]';
	const NET_PACKETS_OUT_KEY = 'net.if.out[eth0,packets]';
	const DISK_WRITE_OPS = 'iostat.node.mdstatall[8]';
	const DISK_READ_OPS = 'iostat.node.mdstatall[4]';
	/** @var ZabbixConnector */
	private $connector;
	/** @var CacheAdapter */
	private $cache;

	/**
	 * @var string
	 */
	private static $url;
	/**
	 * @var string
	 */
	private static $login;
	/**
	 * @var string
	 */
	private static $pass;

	/**
	 * NodeStatistic constructor.
	 */
	public function __construct($url, $login, $pass)
	{
		self::$url       = $url;
		self::$login     = $login;
		self::$pass      = $pass;
		$this->connector = new ZabbixConnector(self::$url, self::$login, self::$pass);
		$this->cache     = new CacheAdapter();
	}

	/**
	 * Получаем список хостов
	 *
	 * @param array $filter
	 * @param array $output
	 *
	 * @return array
	 */
	public function getNodes($filter = array(), $output = array('hostid', 'host', 'name', 'status', 'disable_until'))
	{
		$this->connector->method          = 'host.get';
		$this->connector->query['output'] = $output;
		if (!empty($filter)) {
			$this->connector->query['filter'] = $filter;
		}

		$nodes = $this->connector->call();

		return $nodes;
	}

	/**
	 * Получение сетевых интерфейсов
	 *
	 * @param int|array $hostids
	 *
	 * @return array
	 */
	public function getInterfaces($hostids)
	{
		$this->connector->method           = 'hostinterface.get';
		$this->connector->query['output']  = 'extend';
		$this->connector->query['hostids'] = $hostids;
		$result                            = $this->connector->call();

		return $result;
	}

	/**
	 * Получение списка счетчиков
	 *
	 * @param int               $hostId
	 * @param null|string|array $search
	 * @param array|string      $output
	 *
	 * @return array
	 */
	public function getCounters($hostId, $search = null, $output = array('itemid', 'key_', 'name', 'description'))
	{
		$this->connector->method           = 'item.get';
		$this->connector->query['hostids'] = $hostId;
		if ($search) {
			$this->connector->query['search']['name'] = $search;
		}

		$this->connector->query['sortfield'] = 'name';
		$this->connector->query['output']    = $output;
		$res                                 = $this->connector->call();

		return $res;
	}

	/**
	 * Возвращает счетчик по его идентификатору в заббиксе
	 *
	 * @param int        $hostId
	 * @param string     $counterKey
	 * @param null|array $filter
	 * @param array      $output
	 *
	 * @return array|bool
	 */
	public function getCounter($hostId, $counterKey, $filter = null, $output = array('itemid', 'key_', 'name', 'description', 'value_type'))
	{
		$counter = $this->cache->get($hostId . '#' . $counterKey);
		if (!$counter) {
			$counters = $this->getCounters($hostId, $filter, $output);
			foreach ($counters as $item) {
				$this->cache->set($hostId . '#' . $item['key_'], $item);
				if ($counterKey === $item['key_']) {
					$counter = $item;
				}
			}
		}

		return $counter;
	}

	/**
	 * Получение данных статискики
	 *
	 * @param int          $counterId
	 * @param int          $valueType
	 * @param int          $limit
	 * @param string|array $output
	 *
	 * @return array
	 */
	public function getHistory($counterId, $valueType, $limit = 20, $output = 'extend')
	{
		$this->connector->method             = 'history.get';
		$this->connector->query['itemids']   = $counterId;
		$this->connector->query["sortfield"] = "clock";
		$this->connector->query["sortorder"] = "DESC";
		$this->connector->query["limit"]     = $limit;
		$this->connector->query['output']    = $output;
		$this->connector->query['history']   = $valueType;
		$res                                 = $this->connector->call();

		return $res;
	}

	/**
	 * Возвращает статистику по загрузке CPU
	 *
	 * @param array $host
	 *
	 * @return array|bool
	 */
	public function getCpuLoad($host)
	{
		$data = $this->getData($host['hostid'], self::CPU_LOAD_KEY, array('cpu'));
		foreach ($data as $key => $item) {
			// бля того что б высчитывать user+system+... делаем 100% - неиспользуемое время
			$data[$key]['value'] = 100 - $item['value'];
		}

		return $data;
	}

	/**
	 * Возвращает количество доступной памяти
	 *
	 * @param array $host
	 *
	 * @return array|bool
	 */
	public function getMemLoad($host)
	{
		$data = $this->getData($host['hostid'], self::MEM_LOAD_KEY, array('memory'));

		return $data;
	}

	/**
	 * Возвращает сколько памяти всего на ноде
	 *
	 * @param array $host
	 *
	 * @return bool|int
	 */
	public function getMemTotal($host)
	{
		$data = $this->getData($host['hostid'], self::MEM_TOTAL_KEY, array('memory'), 1);
		if (is_array($data) && count($data)) {
			return $data[0]['value'];
		}

		return false;
	}

	/**
	 * Возвращает данные по идентификатору счетчика
	 *
	 * @param int    $hostId
	 * @param string $counterKey
	 * @param int    $limit
	 *
	 * @return array|bool
	 */
	private function getData($hostId, $counterKey, $filter = null, $limit = 60)
	{
		$counter = $this->getCounter($hostId, $counterKey, $filter);

		if (!$counter) {
			return false;
		}

		$data = $this->getHistory($counter['itemid'], $counter['value_type'], $limit);

		return $data;
	}

	/**
	 * Возвращает статистику по diskIops
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	public function getIops($host)
	{
		$data = array(
			'read'  => $this->getData($host['hostid'], self::DISK_READ_OPS),
			'write' => $this->getData($host['hostid'], self::DISK_WRITE_OPS),
		);

		return $data;
	}

	/**
	 * Возвращает статистику сети (в пакетах)
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	public function getNetPkg($host)
	{
		$data = array(
			'in'  => $this->getData($host['hostid'], self::NET_PACKETS_IN_KEY),
			'out' => $this->getData($host['hostid'], self::NET_PACKETS_OUT_KEY),
		);

		return $data;
	}
}