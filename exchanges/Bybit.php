<?php
	
	namespace Exchange;
	
	class Bybit extends \Exchange {
		
		public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
		public $limit = 120;
		
		public $rebate = 0;
		
		public $fees = [
			
			'USDT' => [
				
				[0.010, 0.06],
				[0.006, 0.05],
				[0.004, 0.045],
				[0.002, 0.0425],
				
			],
			
			'COIN' => [
				
				[0.0100, 0.0500], // 30d BTC Volume Maker / Taker %
				[0.0080, 0.0450],
				[0.0050, 0.0400],
				[0.0030, 0.0300],
				
			],
			
		];
		
		public $curChanges = [
			
			'1000SHIB' => 'SHIB',
			
		];
		
		public $quoteChanges = [
			
			'USDT' => 'USD',
			
		];
		
		public $intervalChanges = [
			
			'1m' => 1,
			'1h' => 60,
			'1M' => 'M',
			'1d' => 'D',
			'1w' => 'W',
			
		];
		
		public $intervalChangesTime = [
			
			'1m' => '3 hours',
			'1M' => '1 month',
			'1w' => '2 years',
			
		];
		
		protected $userKey, $futuresKey;
		
		function getName () {
			return 'bybit';
		}
		
		function getTitle () {
			return 'Bybit';
		}
		
		function setCredentials ($cred) {
			
			parent::setCredentials ($cred);
			
			if ($this->timeOffset == null) {
				
				$request = $this->getRequest (__FUNCTION__);
				
				$request->signed = false;
				
				$time = $request->connect ('v2/public/time')['time_now'];
				
				$this->timeOffset = ((number_format ($time, 0, '.', '') * 1000) - $request->milliseconds ());
				
			}
			
		}
		
		function getCharts ($base, $quote, array $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if (isset ($this->curChanges[$base]))
				$base = $this->curChanges[$base];
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'interval' => $data['interval'],
				
			];
			
			if (isset ($this->intervalChanges[$request->params['interval']]))
				$request->params['interval'] = $this->intervalChanges[$request->params['interval']];
			
			if (!isset ($data['start_time'])) {
				
				$date = new \DateTime ();
				
				if (isset ($this->intervalChangesTime[$data['interval']]))
					$request->params['from'] = $this->intervalChangesTime[$data['interval']];
				else
					$request->params['from'] = '1 month';
				
				$request->params['from'] = $date->modify ('-'.$request->params['from'])->getTimestamp ();
				
			} else $request->params['from'] = $data['start_time'];
			
			if (isset ($data['end_time']))
				$request->params['to'] = $data['end_time'];
			
			if (isset ($data['limit']))
				$request->params['limit'] = $data['limit'];
			
			$request->signed = false;
			$request->debug = 0;
			
			$summary = [];
			
			if ($prices = $request->connect ('public/linear/kline')['result'])
			foreach ($prices as $value)
				$summary[] = [
					
					'date' => $value['start_at'],
					'date_text' => self::date ($value['start_at']),
					'low' => $value['low'],
					'high' => $value['high'],
					'open' => $value['open'], // Покупка
					'close' => $value['close'], // Продажа
					
				];
			
			return $summary;
			
		}
		
		function createOrder ($type, $base, $quote, $amount, $price) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'type' => 'LIMIT',
				'side' => $type,
				'quantity' => $this->amount ($amount),
				'price' => $price,
				'timeInForce' => 'GTC',
				
			];
			
			$request->method = BybitRequest::POST;
			
			return $request->connect ('api/v3/order');
			
		}
		
		function createMarketOrder ($type, $base, $quote, $amount) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'type' => 'MARKET',
				'side' => $type,
				'quantity' => $this->amount ($amount),
				
			];
			
			$request->method = BybitRequest::POST;
			
			return $request->connect ('api/v3/order');
			
		}
		
		function getOrders ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$data = $request->connect ('api/v3/allOrders');
			
			$output = [];
			
			foreach ($data as $order)
				$output[strtolower ($order['side'])][$order['orderId']] = $this->orderData ($order);
			
			return $output;
			
		}
		
		function orderData ($order) {
			
			return [
				
				'price' => $order['price'],
				'quantity' => $order['origQty'],
				'date' => ($order['time'] / 1000),
				'done' => ($order['status'] == 'FILLED' ? 1 : 0),
				
			];
			
		}
		
		function getOrderInfo ($id) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'orderId' => $id,
				
			];
			
			return $this->orderData ($request->connect ('api/v3/order'));
			
		}
		
		function getBalance ($type, $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [];
			
			if ($quote) $request->params['coin'] = $quote;
			
			$types = [
				
				self::BALANCE_AVAILABLE => 'available_balance',
				self::BALANCE_TOTAL => 'wallet_balance',
				
			];
			
			foreach ($request->connect ('v2/private/wallet/balance')['result'] as $data) {
				
				if (!$quote) {
					
					$balance = [];
					
					foreach ($data as $data)
						$balance[$data['asset']] = $data[$types[$type]];
					
					return $balance;
					
				} else return $data[$types[$type]];
				
			}
			
		}
		
		function getAnnouncements () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->signed = false;
			
			return $request->connect ('v2/public/announcement')['result'];
			
		}
		
		function setFuturesLeverage ($base, $quote, $leverage) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'buy_leverage' => $leverage,
				'sell_leverage' => $leverage,
				
			];
			
			$request->method = BybitRequest::POST;
			$request->market = BybitRequest::FUTURES;
			
			return $request->connect ('private/linear/position/set-leverage')['result'];
			
		}
		
		function setFuturesMarginType ($base, $quote, $type, $longLeverage = 10, $shortLeverage = 10) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'is_isolated' => ($type == self::ISOLATED),
				'buy_leverage' => $longLeverage,
				'sell_leverage' => $shortLeverage,
				
			];
			
			$request->method = BybitRequest::POST;
			$request->market = BybitRequest::FUTURES;
			
			return $request->connect ('private/linear/position/switch-isolated')['result'];
			
		}
		
		function getFuturesPositions ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BybitRequest::FUTURES;
			
			$data = [];
			
			foreach ($request->connect ('private/linear/position/list')['result'] as $pos)
				$data[$pos['side'] == 'Buy' ? self::LONG : self::SHORT] = $pos;
			
			return $data;
			
		}
		
		function createUserStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::POST;
			
			$data = $request->connect ('api/v3/userDataStream');
			$this->userKey = $data['listenKey'];
			
		}
		
		function updateUserStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'listenKey' => $this->userKey,
				
			];
			
			$request->method = BybitRequest::PUT;
			
			return $request->connect ('api/v3/userDataStream');
			
		}
		
		function userSocket ($callback) {
			
			$this->updateUserStreamKey ();
			
			$request = $this->getRequest (__FUNCTION__);
			$request->socket ($this->userKey, $callback);
			
		}
		
		function futuresSocket ($callback) {
			
			$this->updateFuturesStreamKey ();
			
			$request = $this->getRequest (__FUNCTION__);
			$request->socket ($this->futuresKey, $callback);
			
		}
		
		function createFuturesStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::POST;
			$request->market = BybitRequest::FUTURES;
			
			$data = $request->connect ('fapi/v1/listenKey');
			$this->futuresKey = $data['listenKey'];
			
		}
		
		function updateFuturesStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'listenKey' => $this->futuresKey,
				
			];
			
			$request->method = BybitRequest::PUT;
			
			return $request->connect ('v1/listenKey');
			
		}
		
		function getTrades ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BybitRequest::FUTURES;
			
			return $request->connect ('fapi/v1/userTrades');
			
		}
		
		function getSymbolsInfo () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->signed = false;
			
			return $request->connect ('v2/public/symbols')['result'];
			
		}
		
		protected function getSymbolsList ($list, $quote) {
			
			$symbols = [];
			
			foreach ($list as $symbol) {
				
				$pair = $this->pair ($symbol['base_currency'], $symbol['quote_currency']);
				
				if ((!$quote or $symbol['quote_currency'] == $quote) and $symbol['status'] == 'Trading')
					$symbols[$pair] = $this->prepSymbol ($symbol);
				
			}
			
			return $symbols;
			
		}
		
		protected function prepSymbol ($symbol) {
			
			$parts = explode ('.', $symbol['lot_size_filter']['min_trading_qty']);
			
			return [
				
				'base' => $symbol['base_currency'],
				'quote' => $symbol['quote_currency'],
				'leverage' => $symbol['leverage_filter']['max_leverage'],
				'price_precision' => $symbol['price_scale'],
				//'amount_precision' => (count ($parts) > 1 ? strlen ($parts[1]) : 0),
				'min_notional' => $symbol['lot_size_filter']['min_trading_qty'],
				'max_notional' => $symbol['lot_size_filter']['max_trading_qty'],
				
			];
			
		}
		
		function getSymbols ($quote = '') {
			return $this->getSymbolsList ($this->getSymbolsInfo (), $quote);
		}
		
		function getFuturesSymbols ($quote = '') {
			return $this->getSymbolsList ($this->getFuturesSymbolsInfo (), $quote);
		}
		
		function getFuturesOpenOrders ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				//'stop_order_status' => 'Active',
				
			];
			
			$request->market = BybitRequest::FUTURES;
			
			return $request->connect ('private/linear/stop-order/list')['result'];
			
		}
		
		function openFuturesMarketPosition ($base, $quote, $side, $data) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			return $this->createFuturesTypeOrder ([$data], 'Limit', 'Market', __FUNCTION__);
			
		}
		
		function createFuturesMarketTakeProfitOrder ($orders) {
			return $this->createFuturesTypeOrder ($orders, 'Limit', 'Market', __FUNCTION__);
		}
		
		function editFuturesOrder ($base, $quote, $id, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'order_id' => $id,
				
			];
			
			foreach ($data as $key => $value)
				$request->params[$key] = $value;
			
			return $request->connect ('private/linear/order/replace')['result'];
			
		}
		
		function editFuturesOrderName ($base, $quote, $name, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'order_link_id' => $name,
				
			];
			
			foreach ($data as $key => $value)
				$request->params[$key] = $value;
			
			return $request->connect ('private/linear/order/replace')['result'];
			
		}
		
		function editFuturesPosition ($base, $quote, $side, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'side' => ($this->isLong () ? 'Buy' : 'Sell'),
				
			];
			
			foreach ($data as $key => $value)
				if ($key != 'name')
				$request->params[$key] = $value;
			
			return $request->connect ('private/linear/position/trading-stop')['result'];
			
		}
		
		function createFuturesMarketStopOrder ($orders) {
			
			//foreach ($orders as $i => $order)
			//	$orders[$i]['close'] = true;
			
			return $this->createFuturesTypeOrder ($orders, 'Limit', 'Market', __FUNCTION__);
			
		}
		
		function createFuturesTypeOrder ($orders, $type1, $type2, $func) {
			
			$list = [];
			
			foreach ($orders as $order) {
				
				$data = [
					
					'symbol' => $this->pair ($order['base'], $order['quote']),
					'order_type' => (isset ($order['price']) ? $type1 : $type2),
					'side' => ($this->isLong () ? 'Buy' : 'Sell'),
					'qty' => $order['quantity'],
					'time_in_force' => 'GoodTillCancel',
					'reduce_only' => (isset ($order['close']) ? 'true' : 'false'),
					'close_on_trigger' => 'false',
					'trigger_by' => 'MarkPrice',
					'tp_trigger_by' => 'MarkPrice',
					'sl_trigger_by' => 'MarkPrice',
					
				];
				
				if (isset ($order['take_profit']))
					$data['take_profit'] = $this->price ($order['take_profit']);
				
				if (isset ($order['stop_loss']))
					$data['stop_loss'] = $this->price ($order['stop_loss']);
				
				if (isset ($order['price']))
					$data['price'] = $this->price ($order['price']);
				
				if (isset ($order['name']))
					$data['order_link_id'] = $order['name'];
				
				$list[] = $data;
				
			}
			
			return $this->createFuturesBatchOrder ($list, $func);
			
		}
		
		protected function createFuturesBatchOrder ($orders, $func) {
			
			$output = [];
			
			foreach ($orders as $order) {
				
				$request = $this->getRequest ($func);
				
				$request->params = $order;
				
				$request->market = BybitRequest::FUTURES;
				$request->method = BybitRequest::POST;
				
				$output[] = $request->connect ('private/linear/order/create')['result'];
				
			}
			
			return $output;
			
		}
		
		function cancelFuturesOpenOrders ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
			return $request->connect ('private/linear/order/cancel-all')['result'];
			
		}
		
		function cancelFuturesOrderName ($base, $quote, $name) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'order_link_id' => $name,
				
			];
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
			$request->connect ('private/linear/order/cancel')['result'];
			
		}
		
		function longShortRatio ($base, $quote, $period) {
			
			$summary = [];
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'period' => $period,
				
			];
			
			$request->market = BybitRequest::FUTURES;
			$request->signed = false;
			
			foreach ($request->connect ('futures/data/topLongShortAccountRatio') as $value) {
				
				$date = ($value['timestamp'] / 1000);
				
				$summary[$date] = [
					
					'ratio' => $value['longShortRatio'],
					'long' => $value['longAccount'],
					'short' => $value['shortAccount'],
					'date' => $date,
					
				];
				
			}
			
			return $summary;
			
		}
		
		function isOrderStopLoss ($order) {
			return ($order['stop_order_type'] == 'StopLoss');
		}
		
		function isOrderTakeProfit ($order) {
			return ($order['stop_order_type'] == 'TakeProfit');
		}
		
		function orderName ($order) {
			return $order['order_link_id'];
		}
		
		function cancelFuturesOrders ($base, $quote, array $ids) {
			
			$output = [];
			
			foreach ($ids as $id) {
				
				$request = $this->getRequest (__FUNCTION__);
				
				$request->params = [
					
					'symbol' => $this->pair ($base, $quote),
					'stop_order_id' => $id,
					
				];
				
				$request->market = BybitRequest::FUTURES;
				$request->method = BybitRequest::POST;
				
				$output[] = $request->connect ('private/linear/stop-order/cancel')['result'];
				
			}
			
			return $output;
			
		}
		
		function cancelFuturesOrdersNames ($base, $quote, $ids) {
			
			$output = [];
			
			foreach ($ids as $id) {
				
				$request = $this->getRequest (__FUNCTION__);
				
				$request->params = [
					
					'symbol' => $this->pair ($base, $quote),
					'order_link_id' => $id,
					
				];
				
				$request->market = BybitRequest::FUTURES;
				$request->method = BybitRequest::POST;
				
				$output[] = $request->connect ('private/linear/stop-order/cancel')['result'];
				
			}
			
			return $output;
			
		}
		
		protected function getRequest ($func, $order = []) {
			return new BybitRequest ($this, $func, $order);
		}
		
		function orderCreateDate ($order) {
			return strtotime ($order['created_time']);
		}
		
		function getAccountStatus () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			return $request->connect ('sapi/v1/account/status')['data'];
			
		}
		
		function ticker ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->signed = false;
			
			if ($pairs = $request->connect ('v2/public/tickers')) {
				
				$pairs = $pairs['result'];
				
				if (!$base and !$quote) {
					
					$output = [];
					
					foreach ($pairs as $pair)
						$output[$pair['symbol']] = $this->prepTicker ($pair);
					
					return $output;
					
				} else return $this->prepTicker ($pairs[0]);
				
			}
			
			return ['index_price' => 0];
			
		}
		
		protected function prepTicker ($item) {
			return ['mark_price' => $item['mark_price'], 'index_price' => $item['index_price'], 'prev' => $item['prev_price_24h'], 'change_percent' => $item['price_24h_pcnt'], 'close' => $item['last_price']];
		}
		
		function setPairsFuturesHedgeMode () {
			
		}
		
		function setFuturesHedgeMode (bool $hedge, $base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'mode' => $hedge ? 'BothSide' : 'MergedSingle',
				
			];
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
			return $request->connect ('private/linear/position/switch-mod')['result'];
			
		}
		
		function futuresTradingStatus ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->market = BybitRequest::FUTURES;
			$request->debug = 0;
			
			$data = $request->connect ('fapi/v1/apiTradingStatus');
			
			$output = [];
			
			if (!$base and !$quote) {
				
				$output = [];
				
				foreach ($data['indicators'] as $pair => $data)
					$output[$pair] = [$data['indicator'] => $this->prepTradingStatus ($data)];
				
			} else {
				
				$data = $data['indicators'][$this->pair ($base, $quote)];
				$output = [$data['indicator'] => $this->prepTradingStatus ($data)];
				
			}
			
			return $output;
			
		}
		
		protected function prepTradingStatus ($item) {
			return ['value' => $item['value'], 'trigger' => $item['triggerValue']];
		}
		
		function futuresOrderData (array $order) {
			return ['price' => $order['base_price']];
		}
		
		function getPositionInfo ($base, $quote) {
			return $this->position;
		}
		
		function getPositionData ($position) {
			return $position;
		}
		
	}
	
	class BybitRequest {
		
		public
			$apiUrl = 'https://api.bybit.com',
			$futuresUrl = 'https://api.bybit.com',
			$streamsUrl = 'tls://stream.binance.com',
			
			$testApiUrl = 'https://api-testnet.bybit.com',
			$testFuturesUrl = 'https://api-testnet.bybit.com',
			$testStreamsUrl = 'tls://testnet-dex.binance.org';
		
		public
			$params = [],
			$method = self::GET,
			$market,
			$signed = true,
			$debug = 0,
			$errorCodes = [404],
			$func,
			$order,
			$recvWindow = 60000; // 5 seconds
		
		const GET = 'GET', POST = 'POST', PUT = 'PUT', DELETE = 'DELETE';
		const FUTURES = 'FUTURES';
		
		protected $socket, $exchange;
		
		function __construct ($exchange, $func, $order = []) {
			
			$this->exchange = $exchange;
			$this->func = $func;
			$this->order = $order;
			
			//$this->socket = new \WebSocket (str_replace ('', rand (1, 17), $this->streamsUrl), 9443);
			
		}
		
		function connect ($path) {
			
			$ch = curl_init ();
			
			if ($this->exchange->debug and $this->debug) {
				
				if ($this->market == self::FUTURES)
					$url = $this->testFuturesUrl;
				else
					$url = $this->testApiUrl;
				
			} else {
				
				if ($this->market == self::FUTURES)
					$url = str_replace ('', rand (1, 3), $this->futuresUrl);
				else
					$url = str_replace ('', rand (1, 17), $this->apiUrl);
				
			}
			
			if ($this->signed) {
				
				$this->params['api_key'] = $this->exchange->cred['key'];
				$this->params['recv_window'] =	$this->recvWindow;
				$this->params['timestamp'] = $this->time ();
				$this->params['sign'] = $this->signature ();
				
			}
			
			if ($this->params and $this->method != self::POST)
				$path .= '?'.http_build_query ($this->params);
			
			$options = [
				
				CURLOPT_URL => $url.'/'.$path,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT => 'User-Agent: Mozilla/4.0 (compatible; PHP '.$this->exchange->getTitle ().' API)',
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				
			];
			
			//debug ($url.'/'.$path);
			
			if ($this->method == self::POST) {
				
				$options[CURLOPT_POST] = 1;
				$options[CURLOPT_POSTFIELDS] = http_build_query ($this->params);
				
			} elseif ($this->method == self::PUT)
				$options[CURLOPT_PUT] = true;
			elseif ($this->method != self::GET)
				$options[CURLOPT_CUSTOMREQUEST] = $this->method;
			
			$options[CURLOPT_HTTPHEADER] = ['Connection: keep-alive'];
			
			if ($this->exchange->proxies) {
				
				$proxy = trim ($this->exchange->proxies[mt_rand (0, count ($this->exchange->proxies) - 1)]);
				
				$parts = explode ('@', $proxy);
				
				if (count ($parts) > 1) {
					
					$proxy = $parts[1];
					$options[CURLOPT_PROXYUSERPWD] = $parts[0];
					
				}
				
				$options[CURLOPT_PROXY] = $proxy;
				$options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
				
			} else $proxy = '';
			
			curl_setopt_array ($ch, $options);
			
			$data = curl_exec ($ch);
			$info = curl_getinfo ($ch);
			
			$this->exchange->queryNum++;
			
			$options[CURLOPT_SSL_CIPHER_LIST] = 'TLSv1';
			
			if ($error = curl_error ($ch))
				throw new \ExchangeException ($error, curl_errno ($ch), $this->func, $proxy, $this->order);
			elseif (in_array ($info['http_code'], $this->errorCodes))
				throw new \ExchangeException ($options[CURLOPT_URL].' '.http_get_message ($info['http_code']), $info['http_code'], $this->func, $proxy, $this->order);
			
			$data = json_decode ($data, true);
			
			curl_close ($ch);
			
			if (isset ($data['ret_code']) and $data['ret_code'] != 0)
				throw new \ExchangeException ($data['ret_msg'], $data['ret_code'], $this->func, $proxy, $this->order); // Типа ошибка
			
			return $data;
			
		}
		
		protected function time () {
			
			$ts = $this->milliseconds () + $this->exchange->timeOffset;
			return number_format ($ts, 0, '.', '');
			
		}
		
		public function milliseconds () {
			
			if (PHP_INT_SIZE == 4)
				return $this->milliseconds32 ();
			else
				return $this->milliseconds64 ();
			
		}
		
		public function milliseconds32 () {
			
			list ($msec, $sec) = explode (' ', microtime ());
			
			// raspbian 32-bit integer workaround
			// https://github.com/ccxt/ccxt/issues/5978
			// return (int) ($sec.substr ($msec, 2, 3));
			
			return $sec.substr ($msec, 2, 3);
			
		}
		
		public function milliseconds64 () {
			
			list ($msec, $sec) = explode (' ', microtime ());
			// this method will not work on 32-bit raspbian
			return (int) ($sec . substr ($msec, 2, 3));
			
		}
		
		protected function signature () {
			
			ksort ($this->params);
			return hash_hmac ('sha256', http_build_query ($this->params), $this->exchange->cred['secret']);
			
		}
		
		function socket ($key, $callback) {
			
			$this->socket->path = 'ws/'.$key;
			
			$this->socket->open ();
			
			$this->socket->read ($callback);
			
		}
		
	}