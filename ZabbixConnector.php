<?php

/**
 * Class ZabbixConnector
 */
class ZabbixConnector
{
	public $method;
	protected $access_token;
	protected $url;
	public $query;
	public $last_error;

	/**
	 * ZabbixConnector constructor.
	 *
	 * @param string $url
	 * @param string $login
	 * @param string $pass
	 */
	public function __construct($url, $login, $pass)
	{
		$this->url               = $url;
		$this->query['user']     = $login;
		$this->query['password'] = $pass;
		$this->method            = 'user.login';
		$this->access_token      = $this->call();
	}

	/**
	 * @return mixed
	 */
	public function call()
	{
		$data['jsonrpc'] = '2.0';
		$data['method']  = $this->method;
		$data['params']  = $this->query;
		$this->query     = '';
		if (!empty($this->access_token)) {
			$data['auth'] = $this->access_token;
		}

		$data['id'] = rand(1, 100);
		$data       = json_encode($data, JSON_PRETTY_PRINT);
		$res        = $this->exec($data);

		return (array_key_exists('result', $res)) ? $res['result'] : false;
	}

	/**
	 * @param $query
	 *
	 * @return mixed
	 */
	protected function exec($query)
	{
		$http = curl_init($this->url);
		curl_setopt($http, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($http, CURLOPT_POSTFIELDS, $query);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($http, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($http, CURLOPT_PROXYUSERPWD, 'login:pass');
		curl_setopt($http, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($http, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		$response         = curl_exec($http);
		$this->last_error = curl_error($http);
		curl_close($http);
		$data = json_decode($response, true);

		return $data;
	}
}
