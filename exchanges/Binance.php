<?php
	
	namespace Exchange;
	
	class Binance extends \Exchange {
		
		public $feesRate = [
			
			self::FUTURES => [
				
				self::FTYPE_USD => [
					
					[0.0200, 0.0400],
					[0.0160, 0.0400],
					[0.0140, 0.0350],
					[0.0120, 0.0320],
					
				],
				
				self::FTYPE_COIN => [
					
					[0.0100, 0.0500], // 30d BTC Volume Maker / Taker %
					[0.0080, 0.0450],
					[0.0050, 0.0400],
					[0.0030, 0.0300],
					
				],
				
			],
			
		];
		
		public $curChanges = [
			
			'1000SHIB' => 'SHIB',
			'SHIB1000' => 'SHIB',
			
		];
		
		public $timeOffset;
		
		protected $userKey, $futuresKey;
		
		function getName () {
			return 'binance';
		}
		
		function getTitle () {
			return 'Binance';
		}
		
		function getVersion () {
			return '1.4';
		}
		
		function setCredentials ($cred) {
			
			parent::setCredentials ($cred);
			
			if ($this->timeOffset == null) {
				
				$request = $this->getRequest (__FUNCTION__);
				
				$request->signed = false;
				
				$data = $request->connect ('api/v3/time');
				
				if (isset ($data['serverTime']))
					$this->timeOffset = ($data['serverTime'] - (microtime (true) * 1000));
				
			}
			
		}
		
		protected function pricesRequest ($func, $base, $quote, array $data): BybitRequest {
			
			$request = $this->getRequest ($func);
			
			if (isset ($this->curChanges[$base]))
				$base = $this->curChanges[$base];
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'interval' => $data['interval'],
				
			];
			
			if (isset ($data['start_time']))
				$request->params['startTime'] = ($data['start_time'] * 1000);
			
			if (isset ($data['end_time']) and $data['end_time'])
				$request->params['endTime'] = ($data['end_time'] * 1000);
			
			if (isset ($data['limit']))
				$request->params['limit'] = $data['limit'];
			
			$request->market = BinanceRequest::FUTURES;
			
			$request->signed = false;
			$request->debug = 0;
			
			return $request;
			
		}
		
		function getPrices ($base, $quote, array $data): array {
			
			$summary = [];
			
			foreach ($this->pricesRequest (__FUNCTION__, $base, $quote, $data)->connect ('fapi/v1/klines') as $value)
				$summary[] = [
					
					'date' => ($value[0] / 1000),
					'date_text' => $this->date ($value[0] / 1000),
					'low' => $value[3],
					'high' => $value[2],
					'open' => $value[1], // Покупка
					'close' => $value[4], // Продажа
					'volume' => $value['volume'],
					
				];
			
			return $summary;
			
		}
		
		function getMarkPrices ($base, $quote, array $data): array {
			
			$summary = [];
			
			foreach ($this->pricesRequest (__FUNCTION__, $base, $quote, $data)->connect ('fapi/v1/markPriceKlines') as $value)
				$summary[] = [
					
					'date' => ($value[0] / 1000),
					'date_text' => $this->date ($value[0] / 1000),
					'low' => $value[3],
					'high' => $value[2],
					'open' => $value[1], // Покупка
					'close' => $value[4], // Продажа
					
				];
			
			return $summary;
			
		}
		
		/*function createOrder ($type, $base, $quote, $price) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'type' => 'LIMIT',
				'side' => $type,
				'quantity' => $this->quantity (),
				'price' => $price,
				'timeInForce' => 'GTC',
				
			];
			
			$request->method = BinanceRequest::POST;
			
			return $request->connect ('api/v3/order');
			
		}
		
		function createOrder ($type, $base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'type' => 'MARKET',
				'side' => $type,
				'quantity' => $this->quantity (),
				
			];
			
			$request->method = BinanceRequest::POST;
			
			return $request->connect ('api/v3/order');
			
		}*/
		
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
		
		protected function getBalances ($type, $quote = ''): array {
			
			$balances = [];
			
			$types = [
				
				self::BALANCE_AVAILABLE => 'free',
				self::BALANCE_TOTAL => 'total',
				
			];
			
			foreach ($this->request ('api/v3/account')['balances'] as $data)
				$balances[$data['asset']] = $data[$types[$type]];
			
			return $balances;
			
		}
		
		function getFuturesBalance ($type, $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->market = BinanceRequest::FUTURES;
			
			if ($quote) $request->params['cur'] = $quote;
			
			$types = [
				
				self::BALANCE_AVAILABLE => 'free',
				self::BALANCE_TOTAL => 'total',
				
			];
			
			foreach ($request->connect ('fapi/v2/balance') as $data) {
				
				if (!$quote) {
					
					$balance = [];
					
					foreach ($data as $data)
						$balance[$data['asset']] = $data[$types[$type]];
					
					return $balance;
					
				} else return $data[$types[$type]];
				
			}
			
		}
		
		function setFuturesLeverage ($base, $quote, $leverage) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'leverage' => $leverage,
				
			];
			
			$request->method = BinanceRequest::POST;
			$request->market = BinanceRequest::FUTURES;
			
			return $request->connect ('fapi/v1/leverage');
			
		}
		
		function setFuturesMarginType ($base, $quote, $longLeverage = 10, $shortLeverage = 10) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'marginType' => $this->marginType,
				
			];
			
			$request->method = BinanceRequest::POST;
			$request->market = BinanceRequest::FUTURES;
			
			return $request->connect ('fapi/v1/marginType');
			
		}
		
		function getFuturesPositions ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->market = BinanceRequest::FUTURES;
			
			$data = [];
			
			foreach ($request->connect ('fapi/v2/positionRisk') as $pos)
			if ($this->hedgeMode)
				$data[$pos['positionSide']] = $pos;
			else
				$data = $pos;
			
			return $data;
			
		}
		
		function getMarkPrice ($base, $quote) {
			return $this->position['markPrice'];
		}
		
		function getEntryPrice () {
			return $this->position['entryPrice'];
		}
		
		function createUserStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BinanceRequest::POST;
			
			$data = $request->connect ('api/v3/userDataStream');
			$this->userKey = $data['listenKey'];
			
		}
		
		function updateUserStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'listenKey' => $this->userKey,
				
			];
			
			$request->method = BinanceRequest::PUT;
			
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
			
			$request->method = BinanceRequest::POST;
			$request->market = BinanceRequest::FUTURES;
			
			$data = $request->connect ('fapi/v1/listenKey');
			$this->futuresKey = $data['listenKey'];
			
		}
		
		function updateFuturesStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'listenKey' => $this->futuresKey,
				
			];
			
			$request->method = BinanceRequest::PUT;
			
			return $request->connect ('v1/listenKey');
			
		}
		
		function getTrades ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			
			return $request->connect ('fapi/v1/userTrades');
			
		}
		
		protected function getSymbolsInfo () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->signed = false;
			
			return $request->connect ('api/v3/exchangeInfo');
			
		}
		
		protected function getFuturesSymbolsInfo () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->signed = false;
			
			$request->market = BinanceRequest::FUTURES;
			
			return $request->connect ('fapi/v1/exchangeInfo');
			
		}
		
		function getFuturesOpenOrders ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			
			return $request->connect ('fapi/v1/openOrders');
			
		}
		
		function openPosition ($base, $quote, $side, $order = []) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'side' => ($this->isLong () ? 'BUY' : 'SELL'),
				'type' => 'MARKET',
				'quantity' => $this->quantity,
				
			];
			
			if ($this->hedgeMode)
				$request->params['positionSide'] = $side;
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::POST;
			
			return $request->connect ('fapi/v1/order');
			
		}
		
		protected function createFuturesBatchOrder ($orders, $func) {
			
			//debug ($orders);
			
			$request = $this->getRequest ($func, $orders);
			
			$request->params = [
				
				'batchOrders' => json_encode ($orders)
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::POST;
			
			return $request->connect ('fapi/v1/batchOrders');
			
		}
		
		function createFuturesTakeProfitOrder ($orders) {
			
			foreach ($orders as $i => $order) {
				
				$orders[$i]['side'] = ($this->isLong () ? self::SELL : self::BUY);
				
			}
			
			return $this->createFuturesTypeOrder ($orders, 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET', __FUNCTION__);
			
		}
		
		function createFuturesStopOrder ($orders) {
			
			foreach ($orders as $i => $order) {
				
				$orders[$i]['price_type'] = 'MARK_PRICE';
				$orders[$i]['side'] = ($this->isLong () ? self::SELL : self::BUY);
				
			}
			
			return $this->createFuturesTypeOrder ($orders, 'STOP', 'STOP_MARKET', __FUNCTION__);
			
		}
		
		protected function createFuturesTypeOrder ($orders, $type1, $type2, $func) {
			
			$list = [];
			
			foreach ($orders as $order) {
				
				$data = [
					
					'symbol' => $this->pair ($order['base'], $order['quote']),
					'type' => (isset ($order['price']) ? $type1 : $type2),
					'side' => $order['side'],
					'stopPrice' => $this->price ($order['trigger_price']),
					'priceProtect' => 'TRUE',
					
				];
				
				if (isset ($order['quantity']))
					$data['quantity'] = $this->amount ($order['quantity']);
				else
					$data['closePosition'] = 'true';
				
				if (isset ($order['price']))
					$data['price'] = $this->price ($order['price']);
				
				if (isset ($order['name']))
					$data['newClientOrderId'] = $order['name'];
				
				if (isset ($order['price_type']))
					$data['workingType'] = $order['price_type'];
				
				if ($this->hedgeMode)
					$data['positionSide'] = $order['pside'];
				else
					$data['positionSide'] = self::$BOTH;
				
				$list[] = $data;
				
			}
			
			return $this->createFuturesBatchOrder ($list, $func);
			
		}
		
		function createFuturesTrailingStopOrder ($order) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($order['base'], $order['quote']),
				'type' => 'TRAILING_STOP_MARKET',
				'side' => $order['side'],
				'quantity' => $this->amount ($order['quantity']),
				'callbackRate' => $order['rate'],
				'priceProtect' => 'TRUE',
				'workingType' => 'MARK_PRICE',
				'side' => ($this->isLong () ? self::SELL : self::BUY),
				'reduceOnly' => 'true',
				
			];
			
			if (isset ($order['price']))
				$request->params['activationPrice'] = $this->price ($order['price']);
			
			if (isset ($order['name']))
				$request->params['newClientOrderId'] = $order['name'];
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::POST;
			
			return $request->connect ('fapi/v1/order');
			
		}
		
		function cancelOpenOrders ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::DELETE;
			
			return $request->connect ('fapi/v1/allOpenOrders');
			
		}
		
		function longShortRatio ($base, $quote, $period) {
			
			$summary = [];
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'period' => $period,
				
			];
			
			$request->market = BinanceRequest::FUTURES;
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
			return ($order['origType'] == 'STOP_MARKET');
		}
		
		function isOrderTakeProfit ($order) {
			return ($order['origType'] == 'TAKE_PROFIT_MARKET');
		}
		
		function orderId ($order) {
			return $order['orderId'];
		}
		
		function orderName ($order) {
			return $order['clientOrderId'];
		}
		
		function cancelFuturesOrders ($base, $quote, array $ids) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'orderIdList' => json_encode ($ids),
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::DELETE;
			
			return $request->connect ('fapi/v1/batchOrders');
			
		}
		
		function cancelFuturesOrdersNames ($base, $quote, array $ids) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'origClientOrderIdList' => json_encode ($ids),
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::DELETE;
			
			return $request->connect ('fapi/v1/batchOrders');
			
		}
		
		protected function getRequest ($func, $order = []): BinanceRequest {
			return new BinanceRequest ($this, $func, $order);
		}
		
		function orderCreateDate ($order) {
			return ($order['updateTime'] / 1000);
		}
		
		function getFuturesPrices ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			
			$data = $request->connect ('fapi/v1/premiumIndex');
			
			return ['mark_price' => $data['markPrice'], 'index_price' => $data['indexPrice']];
			
		}
		
		function getAccountStatus () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			return $request->connect ('sapi/v1/account/status')['data'];
			
		}
		
		protected function getSymbolsList ($func, $list, $quote) {
			
			$symbols = [];
			
			foreach ($list as $symbol) {
				
				if ((!$quote or $symbol['marginAsset'] == $quote) and $symbol['underlyingType'] == 'COIN') {
					
					$symbol['base'] = $symbol['baseAsset'];
					$symbol['quote'] = $symbol['marginAsset'];
					
					$symbols[$symbol['symbol']] = $symbol;
					
				}
				
			}
			
			$request = $this->getRequest ($func);
			
			$request->market = BinanceRequest::FUTURES;
			
			$data = $request->connect ('fapi/v1/leverageBracket');
			
			$output = [];
			
			foreach ($data as $pair)
				if (isset ($symbols[$pair['symbol']]))
				$output[$pair['symbol']] = $this->prepBracket ($pair['brackets'][0], $symbols[$pair['symbol']]);
			
			return $output;
			
		}
		
		function getSymbols ($quote = '') {
			return $this->getSymbolsList (__FUNCTION__, $this->getSymbolsInfo ()['symbols'], $quote);
		}
		
		function getFuturesSymbols ($quote = '') {
			return $this->getSymbolsList (__FUNCTION__, $this->getFuturesSymbolsInfo ()['symbols'], $quote);
		}
		
		protected function prepBracket ($bracket, $pair) {
			
			$data = [
				
				'leverage' => $bracket['initialLeverage'],
				'notional' => $bracket['notionalCap'],
				'price_precision' => $pair['pricePrecision'],
				'amount_precision' => $pair['quantityPrecision'],
				
			];
			
			foreach ($pair['filters'] as $filter) {
				
				if ($filter['filterType'] == 'LOT_SIZE') {
					
					$data['min_notional'] = $filter['minQty'];
					$data['max_notional'] = $filter['maxQty'];
					
				}
				
			}
			
			return $data;
			
		}
		
		function getPrice ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->market = BinanceRequest::FUTURES;
			$request->debug = 0;
			
			$data = $request->connect ('fapi/v1/ticker/24hr');
			
			if (!$base and !$quote) {
				
				$output = [];
				
				foreach ($data as $pair)
					$output[$pair['symbol']] = $this->prepTicker ($pair);
				
			} else $output = $this->prepTicker ($data);
			
			return $output;
			
		}
		
		protected function prepTicker ($item) {
			return ['change' => $item['priceChange'], 'change_percent' => $item['priceChangePercent'], 'close' => $item['lastPrice']];
		}
		
		function setFuturesHedgeMode (bool $hedge, $base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'dualSidePosition' => $hedge ? 'true' : 'false'
				
			];
			
			$request->market = BinanceRequest::FUTURES;
			$request->method = BinanceRequest::POST;
			
			return $request->connect ('fapi/v1/positionSide/dual');
			
		}
		
		function getFuturesHedgeMode () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->market = BinanceRequest::FUTURES;
			
			return $request->connect ('fapi/v1/positionSide/dual')['dualSidePosition'];
			
		}
		
		function futuresTradingStatus ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->market = BinanceRequest::FUTURES;
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
		
		function getPositionInfo ($base, $quote) {
			return $this->getFuturesOpenOrders ($base, $quote)[0];
		}
		
		function getPositionData ($position) {
			return ['stop_loss' => $position['stopPrice']];
		}
		
		function positionActive (): bool {
			return ($this->position['size'] > 0);
		}
		
		function minQuantity () {
			return ($this->markPrice * $this->minQuantity);
		}
		
		function maxQuantity () {
			return ($this->markPrice * $this->maxQuantity);
		}
		
	}
	
	class BinanceRequest {
		
		public
			$apiUrl = 'https://api.binance.com',
			$futuresUrl = 'https://fapi.binance.com',
			$streamsUrl = 'tls://stream.binance.com',
			
			$testApiUrl = 'https://testnet.binance.vision',
			$testFuturesUrl = 'https://testnet.binancefuture.com',
			$testStreamsUrl = 'tls://testnet-dex.binance.org';
		
		public
			$params = [],
			$method = self::GET,
			$market,
			$signed = true,
			$debug = 1,
			$errorCodes = [405],
			$func,
			$order;
		
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
				
				$this->params['recvWindow'] =	$this->exchange->recvWindow;
				$this->params['adjustForTimeDifference'] = true;
				$this->params['timestamp'] = $this->time ();
				$this->params['signature'] = $this->signature ();
				
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
			
			if ($this->signed)
				$options[CURLOPT_HTTPHEADER][] = 'X-MBX-APIKEY: '.$this->exchange->cred['key'];
			
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
			
			if (isset ($data[0]['msg']) or (isset ($data[0]['code']) and $data[0]['code'] == 400))
				throw new \ExchangeException ($data[0]['msg'], $data[0]['code'], $this->func, $proxy, $this->order); // Типа ошибка
			elseif (isset ($data['msg']) and !isset ($data['code']))
				throw new \ExchangeException ($data['msg'], 0, $this->func, $proxy, $this->order);
			elseif (isset ($data['msg']) and $data['code'] != 200)
				throw new \ExchangeException ($data['msg'], $data['code'], $this->func, $proxy, $this->order);
			
			return $data;
			
		}
		
		protected function time () {
			
			$ts = (microtime (true) * 1000) + $this->exchange->timeOffset;
			return number_format ($ts, 0, '.', '');
			
		}
		
		protected function signature () {
			return hash_hmac ('sha256', http_build_query ($this->params), $this->exchange->cred['secret']);
		}
		
		function socket ($key, $callback) {
			
			$this->socket->path = 'ws/'.$key;
			
			$this->socket->open ();
			
			$this->socket->read ($callback);
			
		}
		
	}