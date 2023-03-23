<?php
	
	namespace Exchange;
	
	class Bybit extends \Exchange {
		
		public $feesRate = [
			
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
				'interval' => $this->intervalChanges[$data['interval']],
				
			];
			
			if (!isset ($data['limit']))
				$data['limit'] = 200;
			
			$request->params['limit'] = $data['limit'];
			
			if (!isset ($data['end_time']))
				$data['end_time'] = time ();
			
			$request->params['to'] = $data['end_time'];
			
			if (!isset ($data['start_time']))
				$data['start_time'] = ($data['end_time'] - ($data['limit'] * $this->timeframe ($data['interval'])));
			
			$request->params['from'] = $data['start_time'];
			
			$request->signed = false;
			$request->debug = 0;
			
			$summary = [];
			
			if ($prices = $request->connect ('public/linear/kline')['result'])
			foreach ($prices as $value)
				$summary[] = [
					
					'date' => $value['start_at'],
					'date_text' => $this->date ($value['start_at']),
					'low' => $value['low'],
					'high' => $value['high'],
					'open' => $value['open'],
					'close' => $value['close'],
					'volume' => $value['volume'],
					
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
		
		function setFuturesMode ($base, $quote) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'mode' => ($this->hedgeMode ? 'BothSide' : 'MergedSingle'),
				
			];
			
			if ($base)
				$request->params['symbol'] = $this->pair ($base, $quote);
			else
				$request->params['coin'] = $quote;
			
			$request->method = BybitRequest::POST;
			$request->market = BybitRequest::FUTURES;
			
			return $request->connect ('private/linear/position/switch-mode')['result'];
			
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
		
		protected function prepPos ($data) {
			
			return [
				
				'take_profit' => $data['take_profit'],
				'stop_loss' => $data['stop_loss'],
				'trigger_price' => $data['bust_price'],
				
			];
			
		}
		
		function getFuturesPositions ($base = '', $quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
			if ($base and $quote)
				$request->params['symbol'] = $this->pair ($base, $quote);
			
			$request->market = BybitRequest::FUTURES;
			
			$data = [];
			
			foreach ($request->connect ('private/linear/position/list')['result'] as $pos) {
				
				if ($this->hedgeMode) {
					
					if ($base and $quote)
						$data[$this->pair ($base, $quote)][($pos['side'] == 'Buy' ? self::LONG : self::SHORT)] = $pos;
					else						 $data[$pos['data']['symbol']][($pos['data']['side'] == 'Buy' ? self::LONG : self::SHORT)] = $pos['data'];
					
				} else {
					
					if ($base and $quote)
						$data[$this->pair ($base, $quote)] = $pos;
					else						 $data[$pos['data']['symbol']] = $pos['data'];
					
				}
				
			}
			
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
		
		protected function prepSymbol ($symbol, $symbol2) {
			
			return [
				
				'base' => $symbol['base_currency'],
				'quote' => $symbol['quote_currency'],
				'leverage' => $symbol['leverage_filter']['max_leverage'],
				'price_precision' => $symbol2['priceFraction'],
				'amount_precision' => $symbol2['lotFraction'],
				'min_quantity' => $symbol2['minQty'],
				'max_quantity' => $symbol2['maxNewOrderQty'],
				'initial_margin_rate' => ($symbol2['baseInitialMarginRateE4'] / 100),
				'maintenance_margin_rate' => ($symbol2['baseMaintenanceMarginRateE4'] / 100),
				
			];
			
		}
		
		function getSymbols ($quote = '') {
			
			$request = $this->getRequest (__FUNCTION__);
			
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
				
				if ((!$quote or $symbol['quote_currency'] == $quote) and $symbol['status'] == 'Trading')
					$symbols[$pair] = $this->prepSymbol ($symbol, $symbols2[$pair]);
				
			}
			
			return $symbols;
			
		}
		
		function getFuturesFilledOrders ($base, $quote) {
			return $this->getFuturesOrders ($base, $quote, 'Filled');
		}
		
		protected function getFuturesOrders ($base, $quote, $status) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'stop_order_status' => $status,
				
			];
			
			$request->market = BybitRequest::FUTURES;
			
			$output = [];
			
			foreach ($request->connect ('private/linear/order/list')['result']['data'] as $order)
				$output[] = ['trigger_price' => $order['last_exec_price']];
			
			return $output;
			
		}
		
		function openFuturesMarketPosition ($base, $quote, $data) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			return $this->createFuturesTypeOrder ([$data], ($this->isLong () ? 'Buy' : 'Sell'), 'Limit', 'Market', __FUNCTION__);
			
		}
		
		function closeFuturesMarketPosition ($base, $quote, $data) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			$data['close'] = true;
			
			return $this->createFuturesTypeOrder ([$data], ($this->isLong () ? 'Sell' : 'Buy'), 'Limit', 'Market', __FUNCTION__);
			
		}
		
		function createFuturesMarketTakeProfitOrder ($orders) {
			return $this->createFuturesTypeOrder ($orders, ($this->isLong () ? 'Buy' : 'Sell'), 'Limit', 'Market', __FUNCTION__);
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
		
		function editFuturesPosition ($base, $quote, $data) {
			
			$request = $this->getRequest (__FUNCTION__);
			
			$request->market = BybitRequest::FUTURES;
			$request->method = BybitRequest::POST;
			
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
		
		function createFuturesMarketStopOrder ($orders) {
			
			//foreach ($orders as $i => $order)
			//	$orders[$i]['close'] = true;
			
			return $this->createFuturesTypeOrder ($orders, ($this->isLong () ? 'Buy' : 'Sell'), 'Limit', 'Market', __FUNCTION__);
			
		}
		
		function createFuturesTypeOrder ($orders, $side, $type1, $type2, $func) {
			
			$list = [];
			
			foreach ($orders as $order) {
				
				$data = [
					
					'symbol' => $this->pair ($order['base'], $order['quote']),
					'order_type' => (isset ($order['price']) ? $type1 : $type2),
					'side' => $side,
					'qty' => $order['quantity'],
					'time_in_force' => 'GoodTillCancel',
					'reduce_only' => (isset ($order['close']) ? 'true' : 'false'),
					'close_on_trigger' => 'false',
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
				
				if ($this->hedgeMode)
					$data['position_idx'] = ($this->isLong () ? 1 : 2);
				else
					$data['position_idx'] = 0;
				
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
				
				$output[] = $request->connect ('private/linear/order/create')['result']['order_id'];
				
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
			
			return ['index_price' => 0, 'mark_price' => 0];
			
		}
		
		protected function prepTicker ($item) {
			
			return [
				
				'mark_price' => $item['mark_price'],
				'index_price' => $item['index_price'],
				'last_price' => $item['last_price'],
				'prev' => $item['prev_price_24h'],
				'change_percent' => $item['price_24h_pcnt'],
				
			];
			
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
		
		function getAdditionalMargin ($stopPrice) {
			return round (parent::getAdditionalMargin ($stopPrice), 4);
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
			$method = self::GET,
			$market,
			$signed = true,
			$debug = 0,
			$errorCodes = [404],
			$func,
			$order,
			$recvWindow = 60000; // 1 second
		
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
				throw new \ExchangeException ($data['ret_msg'], $data['ret_code'], $this->func, $proxy, $this->order);
			
			return $data;
			
		}
		
		function connect2 ($url) {
			
			$ch = curl_init ();
			
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
			//curl_setopt ($ch, CURLOPT_USERAGENT, '');
			
			$data = curl_exec ($ch);
			
			if ($error = curl_error ($ch))
				throw new \ExchangeException ($error, curl_errno ($ch), $this->func, $proxy, $this->order);
			
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