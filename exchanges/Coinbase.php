<?php
	
	namespace Exchange;
	
	class Coinbase extends \Exchange {
		
		protected $url = 'https://api.pro.coinbase.com';
		
		public $balances = [];
		
		public $lastbidprice = 0, $lastaskprice = 0;
		
		public function __construct ($config, $sandbox = false) {
			
			parent::__construct ($config);
			
			if ($sandbox) $this->url = 'https://api-public.sandbox.pro.coinbase.com';
			
		}
		
		function getCharts ($base, $quote) {
			
			$data = $this->request ('products/'.$this->pair ($base, $quote).'/ticker');
			
			$out = [];
			
			$out['ask'] = $data['ask'];
			$out['bid'] = $data['bid'];
			
			$this->lastaskprice = $out['ask'];
			$this->lastbidprice = $out['bid'];
			
			$data = $this->request ('products/'.$this->pair ($base, $quote).'/stats');
			
			$out['24h_low'] = $data['low'];
			$out['24h_high'] = $data['high'];
			$out['24h_open'] = $data['open'];
			$out['24h_average'] = round (($data['low'] + $data['high']) / 2, 2);
			
			return $out;
			
		}
		
		function printPrices () {
			return [$this->lastaskprice, $this->lastbidprice, $this->lastaskprice - $this->lastbidprice];
		}
		
		function getBalances () {
			
			if (!$this->balances) {
				
				$data = $this->request ('balances');
				
				foreach ($data as $d)
					$this->balances[$d['currency']] = $d;
				
			}
			
			return $this->balances;
			
		}
		
		function getAccountInfo ($cur) {
			return $this->getBalances ()[$cur];
		}
		
		function createOrder ($type, $base, $quote, $amount, $price) {
			
			return $this->request ('orders', [
				
				'product_id' => $this->pair ($base, $quote),
				'size' => $amount,
				'price' => $price,
				'side' => $type,
				
			]);
			
		}
		
		function isOrderDone ($id) {
			
			$info = $this->getOrderInfo ($id);
			return ($info && $info['status'] == 'done');
			
		}
		
		function getOrderInfo ($id) {
			return $this->request ('orders/'.$id);
		}
		
		function getHolds ($id) {
			return $this->request ('balances/'.$id.'/holds');
		}
		
		protected function request ($path, $postdata = []) {
			
			$curl = curl_init ();
			
			$options = [
				
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL => $this->url.'/'.$path,
				CURLOPT_USERAGENT => 'PHPtrader',
				CURLOPT_SSL_VERIFYPEER => false,
				
			];
			
			if ($postdata) {
				
				$elements = [];
				foreach ($postdata as $key => $pd)
				$elements[] = $key.'='.$pd;
				
				$options[CURLOPT_POST] = 1;
				$options[CURLOPT_POSTFIELDS] = json_encode ($postdata);
				
			}
			
			$options[CURLOPT_HTTPHEADER] = [
				
				'CB-ACCESS-KEY: '.$this->config['key'],
				'CB-ACCESS-SIGN: '.$this->signature (($postdata ? 'POST' : 'GET'), $options[CURLOPT_POSTFIELDS]),
				'CB-ACCESS-TIMESTAMP: '.time (),
				'CB-ACCESS-PASSPHRASE: '.$this->config['passphrase'],
				'Content-Type: application/json',
				
			];
			
			curl_setopt_array ($curl, $options);
			
			$resp = curl_exec ($curl);
			
			if (curl_errno ($curl)) return false;
			
			curl_close ($curl);
			
			$data = json_decode ($resp, true);
			
			if ($data['message'])
				throw new \Exception ($data['message']);
			else
				return $data;
			
		}
		
		protected function signature ($method, $postdata) {
			
			$what = time ().$method.'/'.$this->path.$postdata;
			return base64_encode (hash_hmac ('sha256', $what, base64_decode ($this->config['secret']), true));
			
		}
		
	}