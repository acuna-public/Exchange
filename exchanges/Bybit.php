<?php
	
	namespace Exchange;
	
	class Bybit extends \Exchange {
		
		public $feesRate = [
			
			self::FUTURES => [
				
				self::FTYPE_USD => [
					
					[0.010, 0.06],
					[0.006, 0.05],
					[0.004, 0.045],
					[0.002, 0.0425],
					[0, 0.04],
					[0, 0.035],
					[0, 0.03],
					
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
			
		];
		
		public $quoteChanges = [
			
			'USDT' => 'USD',
			
		];
		
		public $intervalChanges = [
			
			'1m' => '1',
			'3m' => '3',
			'5m' => '5',
			'15m' => '15',
			'30m' => '30',
			'1h' => '60',
			'2h' => '120',
			'4h' => '240',
			'6h' => '360',
			'12h' => '720',
			'1d' => 'D',
			'1w' => 'W',
			'1M' => 'M',
			'1y' => 'Y',
			
		];
		
		protected $userKey, $futuresKey;
		
		function getName () {
			return 'bybit';
		}
		
		function getTitle () {
			return 'Bybit';
		}
		
		function getVersion () {
			return '1.4';
		}
		
		function timeOffset () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			$request->signed = false;
			
			$time = $request->connect ('v5/market/time')['result']['timeSecond'];
			
			$this->timeOffset = ((number_format ($time, 0, '.', '') * 1000) - $request->milliseconds ());
			
		}
		
		protected function pricesRequest ($func, $base, $quote, array $data): BybitRequest {
			
			$request = $this->getRequest ($func);
			
			$request->method = BybitRequest::GET;
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'interval' => $this->intervalChanges[$data['interval']],
				
			];
			
			if (!isset ($data['limit']) or $data['limit'] <= 0)
				$data['limit'] = 200;
			
			$request->params['limit'] = $data['limit'];
			
			if (isset ($data['start_time']))
				$request->params['start'] = $data['start_time'];
			
			if (isset ($data['end_time']))
				$request->params['end'] = $data['end_time'];
			
			$request->signed = false;
			$request->debug = 0;
			
			return $request;
			
		}
		
		function getPrices ($base, $quote, array $data): array {
			
			$summary = [];
			
			if ($prices = $this->pricesRequest (__FUNCTION__, $base, $quote, $data)->connect ('v5/market/kline')['result']['list'])
			foreach ($prices as $value)
				$summary[] = [
					
					'low' => $value[3],
					'high' => $value[2],
					'open' => $value[1],
					'close' => $value[4],
					'volume' => $value[5],
					'date' => ($value[0] / 1000),
					'date_text' => $this->date (($value[0] / 1000)),
					
				];
			
			return $summary;
			
		}
		
		function getMarkPrices ($base, $quote, array $data): array {
			
			$summary = [];
			
			if ($prices = $this->pricesRequest (__FUNCTION__, $base, $quote, $data)->connect ('v5/market/mark-price-kline')['result']['list'])
			foreach ($prices as $value)
				$summary[] = [
					
					'low' => $value[3],
					'high' => $value[2],
					'open' => $value[1],
					'close' => $value[4],
					'date' => ($value[0] / 1000),
					'date_text' => $this->date (($value[0] / 1000)),
					
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
			
			return $request->connect ('api/v3/order');
			
		}*/
		
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
			
			$request->method = BybitRequest::GET;
			
			$request->params = [
				
				'orderId' => $id,
				
			];
			
			return $this->orderData ($request->connect ('v3/order'));
			
		}
		
		protected function getBalances ($type, $quote = ''): array {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			
			$request->params = [
				
				'accountType' => ($this->market == self::SPOT ? 'SPOT' : 'CONTRACT'),
				
			];
			
			if ($quote) $request->params['coin'] = $quote;
			
			$types = [
				
				self::BALANCE_TOTAL => 'walletBalance',
				self::BALANCE_AVAILABLE => 'availableToWithdraw',
				
			];
			
			$balance = [];
			
			foreach ($request->connect ('v5/account/wallet-balance')['result']['list'][0]['coin'] as $coin) {
				
				$balance[$coin['coin']] = [];
				
				foreach ($types as $name => $value)
					$balance[$coin['coin']][$name] = $coin[$value];
				
			}
			
			return $balance;
			
		}
		
		function getAnnouncements ($data = []) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			$request->signed = false;
			
			foreach ($data as $key => $value)
				$request->params[$key] = $value;
			
			return $request->connect ('v5/announcements/index')['result']['list'];
			
		}
		
		function setLeverage ($base, $quote, $leverage) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'buyLeverage' => $leverage,
				'sellLeverage' => $leverage,
				
			];
			
			return $request->connect ('v5/position/set-leverage')['result'];
			
		}
		
		protected function category ($quote) {
			
			if ($this->market == self::SPOT)
				return 'spot';
			elseif ($quote == 'USD')
				return 'inverse';
			else
				return 'linear';
			
		}
		
		function changePositionMargin ($base, $quote, $value) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'margin' => round ($value, 4),
				
			];
			
			if ($this->hedgeMode)
				$request->params['positionIdx'] = ($this->isLong () ? 1 : 2);
			else
				$request->params['positionIdx'] = 0;
			
			return $request->connect ('v5/position/add-margin');
			
		}
		
		function setMode ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'category' => $this->category ($quote),
				'mode' => ($this->hedgeMode ? 3 : 0),
				
			];
			
			if ($base)
				$request->params['symbol'] = $this->pair ($base, $quote);
			else
				$request->params['coin'] = $quote;
			
			return $request->connect ('v5/position/switch-mode')['result'];
			
		}
		
		function setMarginType ($base, $quote, $longLeverage = 0, $shortLeverage = 0) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if (!$longLeverage) $longLeverage = 10;
			if (!$shortLeverage) $shortLeverage = 10;
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'tradeMode' => (($this->marginType == self::ISOLATED) ? 1 : 0),
				'buyLeverage' => $longLeverage,
				'sellLeverage' => $shortLeverage,
				
			];
			
			return $request->connect ('v5/position/switch-isolated')['result'];
			
		}
		
		protected function prepPos ($data) {
			
			return [
				
				'take_profit' => $data['take_profit'],
				'stop_loss' => $data['stop_loss'],
				'trigger_price' => $data['bust_price'],
				
			];
			
		}
		
		function getPositions ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			
			$request->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			else
				$request->params['settleCoin'] = $quote;
			
			$data = [];
			
			foreach ($request->connect ('v5/position/list')['result']['list'] as $pos) {
				
				if ($this->hedgeMode) {
					
					if ($base and $quote)
						$data[$this->pair ($base, $quote)][($pos['side'] == 'Buy' ? self::LONG : self::SHORT)] = $pos;
					else
						$data[$pos['symbol']][($pos['side'] == 'Buy' ? self::LONG : self::SHORT)] = $pos;
					
				} else {
					
					if ($base and $quote)
						$data[$this->pair ($base, $quote)] = $pos;
					else
						$data[$pos['symbol']] = $pos;
					
				}
				
			}
			
			return $data;
			
		}
		
		function createUserStreamKey () {
			
			$request = $this->getRequest (__FUNCTION__);
			
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
			
			$request->method = BybitRequest::GET;
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			return $request->connect ('fapi/v1/userTrades');
			
		}
		
		protected function prepSymbol ($symbol, $symbol2) {
			
			return [
				
				'base' => $symbol['base_currency'],
				'quote' => $symbol['quote_currency'],
				'leverage' => $symbol['leverage_filter']['max_leverage'],
				'price_precision' => $symbol2['priceFraction'],
				'amount_precision' => $symbol2['lotFraction'],
				'min_quantity' => $symbol['lot_size_filter']['min_trading_qty'],
				'max_quantity' => $symbol['lot_size_filter']['max_trading_qty'],
				'initial_margin_rate' => ($symbol2['baseInitialMarginRateE4'] / 100),
				'maintenance_margin_rate' => ($symbol2['baseMaintenanceMarginRateE4'] / 100),
				
			];
			
		}
		
		function getSymbols ($quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			$request->signed = false;
			
			$data = $request->connect2 ('https://api2.bybit.com/contract/v5/product/dynamic-symbol-list?filter=all');
			
			$symbols2 = [];
			
			foreach ($data['result'] as $type => $symbols) {
				
				if ($type == 'LinearPerpetual' or $type == 'UsdcPerpetual') {
					
					foreach ($symbols as $symbol) {
						
						$pair = $this->pair ($symbol['baseCurrency'], $symbol['coinName']);
						
						if ((!$quote or $symbol['coinName'] == $quote) and $symbol['contractStatus'] == 'Trading')
							$symbols2[$pair] = $symbol;
						
					}
					
				} elseif ($type == 'InversePerpetual') {
					
					foreach ($symbols as $symbol) {
						
						$pair = $this->pair ($symbol['baseCurrency'], $symbol['quoteCurrency']);
						
						if ((!$quote or $symbol['quoteCurrency'] == $quote) and $symbol['contractStatus'] == 'Trading')
							$symbols2[$pair] = $symbol;
						
					}
					
				}
				
			}
			
			$symbols = [];
			
			foreach ($request->connect ('v2/public/symbols')['result'] as $symbol) {
				
				$pair = $this->pair ($symbol['base_currency'], $symbol['quote_currency']);
				
				if ($symbol['status'] == 'Trading')
				if (!$quote or $symbol['quote_currency'] == $quote)
					$symbols[$pair] = $this->prepSymbol ($symbol, $symbols2[$pair]);
				
			}
			
			return $symbols;
			
		}
		
		function getSymbols2 ($quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			$request->method = BybitRequest::GET;
			$request->signed = false;
			
			$symbols = [];
			
			foreach ($request->connect ('v5/market/instruments-info')['result']['list'] as $symbol) {
				
				$pair = $this->pair ($symbol['baseCoin'], $symbol['quoteCoin']);
				
				if ($symbol['status'] == 'Trading')
				if (!$quote or $symbol['quoteCoin'] == $quote)
					$symbols[$pair] = [
						
						'base' => $symbol['baseCoin'],
						'quote' => $symbol['quoteCoin'],
						'launched' => $symbol['launchTime'],
						'minLeverage' => $symbol['leverageFilter']['minLeverage'],
						'maxLeverage' => $symbol['leverageFilter']['maxLeverage'],
						'minQuantity' => $symbol['lotSizeFilter']['minOrderQty'],
						'maxQuantity' => $symbol['lotSizeFilter']['maxOrderQty'],
						
					];
				
			}
			
			return $symbols;
			
		}
		
		function getOrders ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			
			$request->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			else
				$request->params['settleCoin'] = $quote;
			
			return $request->connect ('v5/order/realtime')['result']['list'];
			
		}
		
		protected function createBatchOrder ($orders, $func) {
			
			$output = [];
			
			foreach ($orders as $order) {
				
				$request = $this->getRequest ($func);
				
				$request->params = $order;
				
				$output[] = $request->connect ('v5/order/create')['result']['orderId'];
				
				sleep ($this->sleep);
				
			}
			
			return $output;
			
		}
		
		protected function createTypeOrder (array $orders, string $side, string $func) {
			
			$list = [];
			
			foreach ($orders as $order) {
				
				$data = [
					
					'symbol' => $this->pair ($order['base'], $order['quote']),
					'category' => $this->category ($order['quote']),
					'orderType' => (isset ($order['price']) ? 'Limit' : 'Market'),
					'side' => $side,
					'timeInForce' => 'GTC',
					'reduceOnly' => ((isset ($order['close']) and $order['close']) ? 'true' : 'false'),
					'closeOnTrigger' => 'false',
					'triggerBy' => 'MarkPrice',
					'tpTriggerBy' => 'MarkPrice',
					'slTriggerBy' => 'MarkPrice',
					
				];
				
				if (!isset ($order['quantity']))
					$order['quantity'] = $this->quantity;
				
				$data['qty'] = $this->quantity ($order['quantity']);
				
				if (isset ($order['take_profit']))
					$data['takeProfit'] = $this->price ($order['take_profit']);
				
				if (isset ($order['stop_loss']))
					$data['stopLoss'] = $this->price ($order['stop_loss']);
				
				if (isset ($order['price']))
					$data['price'] = $this->price ($order['price']);
				
				if (isset ($order['name']))
					$data['orderLinkId'] = $order['name'];
				
				if ($this->hedgeMode)
					$data['positionIdx'] = ($this->isLong () ? 1 : 2);
				else
					$data['positionIdx'] = 0;
				
				$list[] = $data;
				
			}
			
			return $this->createBatchOrder ($list, $func);
			
		}
		
		function openPosition ($base, $quote, $data = []) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			return $this->createTypeOrder ([$data], ($this->isLong () ? 'Buy' : 'Sell'), __FUNCTION__);
			
		}
		
		function closePosition ($base, $quote, $data = []) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			$data['close'] = true;
			
			return $this->createTypeOrder ([$data], ($this->isLong () ? 'Sell' : 'Buy'), __FUNCTION__);
			
		}
		
		function decreasePosition ($base, $quote, $data = []) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			return $this->createTypeOrder ([$data], ($this->isLong () ? 'Sell' : 'Buy'), __FUNCTION__);
			
		}
		
		function editOrder ($base, $quote, $orders) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$list = [];
			
			foreach ($orders as $order) {
				
				$order['symbol'] = $this->pair ($base, $quote);
				
				if (isset ($order['id']))
					$order['orderId'] = $order['id'];
				elseif (isset ($order['name']))
					$order['orderLinkId'] = $order['name'];
				
				if (isset ($order['quantity']))
					$order['qty'] = $order['quantity'];
				
				$list[] = $order;
				
			}
			
			$request->params = [
				
				'category' => $this->category ($quote),
				'request' => $list,
				
			];
			
			return $request->connect ('v5/order/amend-batch')['result']['list'];
			
		}
		
		function editPosition ($base, $quote, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'side' => ($this->isLong () ? 'Buy' : 'Sell'),
				'tp_trigger_by' => 'MarkPrice',
				'sl_trigger_by' => 'MarkPrice',
				
			];
			
			foreach ($data as $key => $value)
				if ($key != 'name')
				$request->params[$key] = $value;
			
			if ($this->hedgeMode)
				$request->params['position_idx'] = ($this->isLong () ? 1 : 2);
			else
				$request->params['position_idx'] = 0;
			
			return $request->connect ('private/linear/position/trading-stop')['result'];
			
		}
		
		function cancelOrders ($base = '', $quote = '', $filter = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			elseif ($base)
				$request->params['baseCoin'] = $base;
			else
				$request->params['settleCoin'] = $quote;
			
			if ($filter == 'stop')
				$request->params['orderFilter'] = 'StopOrder';
			elseif ($filter == 'tpsl')
				$request->params['orderFilter'] = 'tpslOrder';
			
			return $request->connect ('v5/order/cancel-all')['result']['list'];
			
		}
		
		function longShortRatio ($base, $quote, $period) {
			
			$summary = [];
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'period' => $period,
				
			];
			
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
				
				$output[] = $request->connect ('private/linear/stop-order/cancel')['result'];
				
			}
			
			return $output;
			
		}
		
		protected function getRequest ($func, $order = []): BybitRequest {
			return new BybitRequest ($this, $func, $order);
		}
		
		function orderCreateDate ($order) {
			return strtotime ($order['created_time']);
		}
		
		function getAccountStatus () {
			
			$request = $this->getRequest (__FUNCTION__);
			
			return $request->connect ('sapi/v1/account/status')['data'];
			
		}
		
		function getTickerPrice ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->method = BybitRequest::GET;
			$request->signed = false;
			
			$request->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			if ($pairs = $request->connect ('v5/market/tickers')['result']['list']) {
				
				$output = [];
				
				foreach ($pairs as $pair)
					$output[$pair['symbol']] = $this->prepTicker ($pair);
				
				return $output;
				
			}
			
			return ['index_price' => 0, 'mark_price' => 0];
			
		}
		
		protected function prepTicker ($item) {
			
			return [
				
				'mark_price' => $item['markPrice'],
				'index_price' => $item['indexPrice'],
				'last_price' => $item['lastPrice'],
				'prev' => $item['prevPrice24h'],
				'change_percent' => $item['price24hPcnt'],
				
			];
			
		}
		
		function setPairsFuturesHedgeMode (bool $hedge) {
			
		}
		
		function setFuturesHedgeMode (bool $hedge, $base = '', $quote = '') {
			
			/*$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'mode' => $hedge ? 'BothSide' : 'MergedSingle',
				
			];
			
			return $request->connect ('private/linear/position/switch-mod')['result'];*/
			
			return '';
			
		}
		
		function futuresTradingStatus ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->method = BybitRequest::GET;
			
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
		
		function getPositionData ($position) {
			return $position;
		}
		
		function positionActive ($base, $quote): bool {
			
			$this->getPosition ($base, $quote);
			return (isset ($this->position['size']) and $this->position['size'] > 0);
			
		}
		
		function getMarginType ($base, $quote) {
			
			$this->getPosition ($base, $quote);
			return (!isset ($this->position['tradeMode']) or $this->position['tradeMode'] == 1 ? self::ISOLATED : self::CROSS);
			
		}
		
		function getAdditionalMargin ($stopPrice) {
			return round (parent::getAdditionalMargin ($stopPrice), 4);
		}
		
		function withdraw ($coin, $address, $chain, $amount, $data = []): array {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'coin' => $coin,
				'chain' => $chain,
				'forceChain' => 1,
				'address' => $address,
				'amount' => $this->quantity ($amount),
				'accountType' => ($this->market == self::SPOT ? 'SPOT' : 'FUND'),
				
			];
			
			foreach ($data as $key => $value)
				$request->params[$key] = $value;
			
			return $request->connect ('v5/asset/withdraw/create')['result'];
			
		}
		
		function longShortGlobalAccountsRatio ($base, $quote, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'period' => $data['interval'],
				
			];
			
			if (!isset ($data['limit']) or $data['limit'] <= 0)
				$data['limit'] = 500;
			
			$request->params['limit'] = $data['limit'];
			
			$request->market = BinanceRequest::FUTURES;
			$request->signed = false;
			
			$summary = [];
			
			foreach ($request->connect ('v5/market/account-ratio')['result']['list'] as $value) {
				
				$summary[] = [
					
					'long' => $value['buyRatio'],
					'short' => $value['sellRatio'],
					'ratio' => ($value['buyRatio'] / $value['sellRatio']),
					'date' => ($value['timestamp'] / 1000),
					'date_text' => $this->date ($value['timestamp'] / 1000),
					
				];
				
			}
			
			return $summary;
			
		}
		
		public $times = ['5m' => '5min', '15m' => '15min', '30m' => '30min', '1h' => '1h', '4h' => '4h', '1d' => '1d'];
		
		function getOpenInterest ($base, $quote, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'intervalTime' => $this->times[$data['interval']],
				
			];
			
			if (!isset ($data['limit']) or $data['limit'] <= 0)
				$data['limit'] = 500;
			
			$request->params['limit'] = $data['limit'];
			
			if (isset ($data['start_time']))
				$request->params['startTime'] = ($data['start_time'] * 1000);
			
			if (isset ($data['end_time']))
				$request->params['endTime'] = ($data['end_time'] * 1000);
			
			$request->market = BinanceRequest::FUTURES;
			$request->signed = false;
			
			$summary = [];
			
			foreach ($request->connect ('v5/market/open-interest')['result']['list'] as $value) {
				
				$summary[] = [
					
					'value' => $value['openInterest'],
					'date' => ($value['timestamp'] / 1000),
					'date_text' => $this->date ($value['timestamp'] / 1000),
					
				];
				
			}
			
			return $summary;
			
		}
		
	}
	
	class BybitRequest {
		
		public
			$apiUrl = 'https://api.bybit.com',
			$futuresUrl = 'https://api.bybit.com',
			$streamsUrl = 'tls://stream.bybit.com',
			
			$testApiUrl = 'https://api-testnet.bybit.com',
			$testFuturesUrl = 'https://api-testnet.bybit.com',
			$testStreamsUrl = 'tls://testnet-dex.bybit.org';
		
		public
			$params = [],
			$method = self::POST,
			$signed = true,
			$debug = 0,
			$errorCodes = [404],
			$func,
			$order;
		
		const GET = 'GET', POST = 'POST', PUT = 'PUT', DELETE = 'DELETE';
		
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
				
				if ($this->exchange->market == \Exchange::FUTURES)
					$url = $this->testFuturesUrl;
				else
					$url = $this->testApiUrl;
				
			} else {
				
				if ($this->exchange->market == \Exchange::FUTURES)
					$url = $this->futuresUrl;
				else
					$url = $this->apiUrl;
				
			}
			
			if ($this->signed) {
				
				$this->params['api_key'] = $this->exchange->cred['key'];
				$this->params['recv_window'] =	$this->exchange->recvWindow;
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
				//CURLOPT_HEADER => 1,
				
			];
			
			//debug ($options[CURLOPT_URL]);
			
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
				throw new \ExchangeException ($error, curl_errno ($ch), $options, $this->func);
			elseif (in_array ($info['http_code'], $this->errorCodes))
				throw new \ExchangeException (http_get_message ($info['http_code']).' ('.$options[CURLOPT_URL].')', $info['http_code'], $options, $this->func);
			//debug ($data);
			$data = json2array ($data);
			
			curl_close ($ch);
			
			if (isset ($data['ret_code']) and $data['ret_code'] != 0)
				throw new \ExchangeException ($data['ret_msg'], $data['ret_code'], $options, $this->func);
			elseif (isset ($data['retCode']) and $data['retCode'] != 0) // v5
				throw new \ExchangeException ($data['retMsg'], $data['retCode'], $options, $this->func);
			
			return $data;
			
		}
		
		function connect5 ($path) {
			
			$ch = curl_init ();
			
			if ($this->exchange->debug and $this->debug) {
				
				if ($this->exchange->market == self::FUTURES)
					$url = $this->testFuturesUrl;
				else
					$url = $this->testApiUrl;
				
			} else {
				
				if ($this->exchange->market == self::FUTURES)
					$url = $this->futuresUrl;
				else
					$url = $this->apiUrl;
				
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
			
			//debug ($options[CURLOPT_URL]);
			
			if ($this->method == self::POST) {
				
				$options[CURLOPT_POST] = 1;
				
				if ($this->signed) {
					
					$options[CURLOPT_POSTFIELDS] = http_build_query ([
						
						'X-BAPI-API-KEY' => $this->exchange->cred['key'],
						'X-BAPI-RECV-WINDOW' =>	$this->exchange->recvWindow,
						'X-BAPI-TIMESTAMP' => $this->time (),
						'X-BAPI-SIGN' => $this->signature (),
						
					]);
					
				}
				
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
				throw new \ExchangeException ($error, curl_errno ($ch), $options, $this->func);
			elseif (in_array ($info['http_code'], $this->errorCodes))
				throw new \ExchangeException (http_get_message ($info['http_code']).' ('.$options[CURLOPT_URL].')', $info['http_code'], $options, $this->func);
			
			$data = json2array ($data);
			
			curl_close ($ch);
			
			if (isset ($data['ret_code']) and $data['ret_code'] != 0)
				throw new \ExchangeException ($data['ret_msg'], $data['ret_code'], $options, $this->func);
			elseif (isset ($data['retCode']) and $data['retCode'] != 0) // v5
				throw new \ExchangeException ($data['retMsg'], $data['retCode'], $options, $this->func);
			
			return $data;
			
		}
		
		function connect2 ($url) {
			
			$ch = curl_init ();
			
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt ($ch, CURLOPT_USERAGENT, $this->exchange->userAgent ? $this->exchange->userAgent : get_useragent ());
			curl_setopt ($ch, CURLOPT_COOKIE, $this->exchange->cookies);
			
			$data = curl_exec ($ch);
			$info = curl_getinfo ($ch);
			
			if ($error = curl_error ($ch))
				throw new \ExchangeException ($error, curl_errno ($ch), $info, $this->func);
			elseif ($info['http_code'] != 200)
				throw new \ExchangeException ('Access denied', $info['http_code'], $info, $this->func);
			
			curl_close ($ch);
			
			return json_decode ($data, true);
			
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
			
			return $sec.substr ($msec, 2, 3);
			
		}
		
		public function milliseconds64 () {
			
			list ($msec, $sec) = explode (' ', microtime ());
			
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