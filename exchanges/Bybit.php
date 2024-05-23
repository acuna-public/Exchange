<?php
	
	namespace Exchange;
	
	class Bybit extends \Exchange {
		
		public $feesRate = [
			
			self::SPOT => [
				
				[0.1, 0.1],
				[0.08, 0.0675],
				[0.0775, 0.065],
				[0.075, 0.0625],
				[0.06, 0.05],
				[0.05, 0.04],
				[0.045, 0.03],
				
			],
			
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
					
					[0.0100, 0.0500],
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
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$time = $this->connect ('v5/market/time')['result']['timeSecond'];
			
			$this->timeOffset = ((number_format ($time, 0, '.', '') * 1000) - $this->milliseconds ());
			
		}
		
		function getPrices (int $type, string $base, string $quote, array $data): array {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$this->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'interval' => $this->intervalChanges[$data['interval']],
				
			];
			
			if (!isset ($data['limit']) or $data['limit'] <= 0)
				$data['limit'] = 500;
			
			$this->params['limit'] = $data['limit'];
			
			if (isset ($data['start_time']) and $data['start_time']) {
				
				//$data['start_time'] -= $this->timeframe ($data['interval']);
				$this->params['start'] = ($data['start_time'] * 1000);
				//debug ([$this->date ($data['start_time'])]);
			}
			
			if (isset ($data['end_time']) and $data['end_time'])
				$this->params['end'] = ($data['end_time'] * 1000);
			
			$this->signed = false;
			$this->debug = 0;
			
			$output = [];
			
			if ($prices = $this->connect ('v5/market/'.($type == self::PRICES_MARK ? 'mark-price-kline' : 'kline'))['result']['list'])
			foreach ($prices as $value) {
				
				$output[] = [
					
					'low' => (float) $value[3],
					'high' => (float) $value[2],
					'open' => (float) $value[1],
					'close' => (float) $value[4],
					'volume' => (float) $value[5],
					'date' => ($value[0] / 1000),
					'date_text' => $this->date (($value[0] / 1000)),
					
				];
				
			}
			
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
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$this->params = [
				
				'orderId' => $id,
				
			];
			
			return $this->orderData ($this->connect ('v3/order'));
			
		}
		
		protected function getBalances ($quote = ''): array {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = true;
			
			$this->params = [
				
				'accountType' => ($this->market == self::SPOT ? 'SPOT' : 'CONTRACT'),
				
			];
			
			if ($quote) $this->params['coin'] = $quote;
			
			$types = [
				
				self::BALANCE_EQUITY => 'equity',
				self::BALANCE_TOTAL => 'walletBalance',
				self::BALANCE_AVAILABLE => 'availableToWithdraw',
				self::BALANCE_UPNL => 'unrealisedPnl',
				
			];
			
			$balance = [];
			
			foreach ($this->connect ('v5/account/wallet-balance')['result']['list'][0]['coin'] as $coin) {
				
				$balance[$coin['coin']] = [];
				
				foreach ($types as $name => $value)
					$balance[$coin['coin']][$name] = $coin[$value];
				
			}
			
			return $balance;
			
		}
		
		function getAnnouncements ($data = []) {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$this->params = [];
			
			foreach ($data as $key => $value)
				$this->params[$key] = $value;
			
			return $this->connect ('v5/announcements/index')['result']['list'];
			
		}
		
		protected function category ($quote) {
			
			if ($this->market == self::SPOT)
				return 'spot';
			elseif ($quote == 'USD')
				return 'inverse';
			else
				return 'linear';
			
		}
		
		function setMode ($base, $quote) {
			
			/*if ($this->market != self::SPOT) {
				
				$this->func = __FUNCTION__;
				
				$this->method = self::POST;
				$this->signed = false;
				
				$this->params = [
					
					'category' => $this->category ($quote),
					'mode' => ($this->isHedgeMode () ? 3 : 0),
					
				];
				
				if ($base)
					$this->params['symbol'] = $this->pair ($base, $quote);
				else
					$this->params['coin'] = $quote;
				
				return $this->connect ('v5/position/switch-mode')['result'];
				
			} else return [];*/
			
		}
		
		protected function prepPos ($data) {
			
			return [
				
				'take_profit' => $data['take_profit'],
				'stop_loss' => $data['stop_loss'],
				'trigger_price' => $data['bust_price'],
				
			];
			
		}
		
		function getPositions ($base = '', $quote = '') {
			return $this->_getPositions ($base, $quote, __FUNCTION__);
		}
		
		protected function _getPositions ($base, $quote, $func, $output = [], $cursor = ''): array {
			
			$this->func = $func;
			
			$this->method = self::GET;
			$this->signed = true;
			
			$this->params = [
				
				'category' => $this->category ($quote),
				'cursor' => $cursor,
				'limit' => 200,
				
			];
			
			if ($base and $quote)
				$this->params['symbol'] = $this->pair ($base, $quote);
			else
				$this->params['settleCoin'] = $quote;
			
			$this->showUrl = true;
			
			$data = $this->connect ('v5/position/list')['result'];
			
			foreach ($data['list'] as $pos) {
				
				if ($pos['positionIdx'] != 0) {
					
					if ($pos['positionIdx'] == 1)
						$side = self::LONG;
					else
						$side = self::SHORT;
					
				} else $side = self::BOTH;
				
				$output[$pos['symbol']][$side] = [
					
					'side' => ($pos['side'] == 'Buy' ? self::LONG : self::SHORT),
					'netPNL' => (float) $pos['curRealisedPnl'],
					'grossPNL' => (float) $pos['unrealisedPnl'],
					'quantity' => (float) $pos['size'],
					'value' => (float) $pos['positionValue'],
					'initialMargin' => (float) $pos['positionIM'],
					'maitenanceMargin' => (float) $pos['positionMM'],
					'balance' => (float) $pos['positionBalance'],
					'entryPrice' => (float) $this->price ($quote == 'USDC' ? $pos['sessionAvgPrice'] : $pos['avgPrice']),
					'markPrice' => (float) $this->price ($pos['markPrice']),
					'liquidPrice' => (float) $this->price ($pos['liqPrice']),
					'takeProfit' => (float) $pos['takeProfit'],
					'stopLoss' => (float) $pos['stopLoss'],
					'trailingStop' => (float) $pos['trailingStop'],
					'leverage' => (float) $pos['leverage'],
					'crossMargin' => ($pos['tradeMode'] == 0),
					'reduceOnly' => $pos['isReduceOnly'],
					'entryTime' => ($pos['createdTime'] / 1000),
					'updatedTime' => ($pos['updatedTime'] / 1000),
					//'test' => $pos,
					
				];
				
			}
			
			if ($data['nextPageCursor'])
				$output = $this->_getPositions ($base, $quote, $func, $output, $data['nextPageCursor']);
			
			return $output;
			
		}
		
		function getTrades ($base, $quote) {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = true;
			
			$this->params = [
				
				'symbol' => $this->pair ($base, $quote),
				
			];
			
			return $this->connect ('fapi/v1/userTrades');
			
		}
		
		protected function prepSymbol ($symbol, $symbol2) {
			
			$data = [
				
				'base' => $symbol['baseCoin'],
				'quote' => $symbol['quoteCoin'],
				'pricePrecision' => $symbol2['priceFraction'],
				'amountPrecision' => $symbol2['lotFraction'],
				'initialMarginRate' => ($symbol2['baseInitialMarginRateE4'] / 100),
				'maintenanceMarginRate' => ($symbol2['baseMaintenanceMarginRateE4'] / 100),
				'minQuantity' => $symbol['lotSizeFilter']['minOrderQty'],
				'maxQuantity' => $symbol['lotSizeFilter']['maxOrderQty'],
				
			];
			
			if ($this->market == self::SPOT) {
				
				$data['minValue'] = $symbol['lotSizeFilter']['minOrderAmt'];
				
			} else {
				
				$data['minValue'] = $symbol['lotSizeFilter']['minNotionalValue'];
				
				$data['minLeverage'] = $symbol['leverageFilter']['minLeverage'];
				$data['maxLeverage'] = $symbol['leverageFilter']['maxLeverage'];
				
			}
			
			return $data;
			
		}
		
		function getSymbols ($quote = '') {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$data = $this->connect2 ('https://api2.bybit.com/contract/v5/product/dynamic-symbol-list?filter=all');
			
			$symbols2 = [];
			
			foreach ($data['result'] as $type => $symbols) {
				
				if ($type == 'LinearPerpetual' or $type == 'UsdcPerpetual') {
					
					foreach ($symbols as $symbol) {
						
						$pair = $this->pair ($symbol['baseCurrency'], $symbol['coinName']);
						
						if (!$quote or $symbol['coinName'] == $quote)
							if ($symbol['contractStatus'] == 'Trading')
								$symbols2[$pair] = $symbol;
						
					}
					
				} elseif ($type == 'InversePerpetual') {
					
					foreach ($symbols as $symbol) {
						
						$pair = $this->pair ($symbol['baseCurrency'], $symbol['quoteCurrency']);
						
						if (!$quote or $symbol['quoteCurrency'] == $quote)
							if ($symbol['contractStatus'] == 'Trading')
								$symbols2[$pair] = $symbol;
						
					}
					
				}
				
			}
			
			$symbols = [];
			
			$this->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			$list = $this->connect ('v5/market/instruments-info')['result']['list'];
			
			foreach ($list as $symbol) {
				
				$pair = $this->pair ($symbol['baseCoin'], $symbol['quoteCoin']);
				
				//if ($symbol['status'] == 'Trading')
				if (!$quote or $symbol['quoteCoin'] == $quote)
					if (isset ($symbols2[$pair]))
						$symbols[$pair] = $this->prepSymbol ($symbol, $symbols2[$pair]);
					
			}
			
			return $symbols;
			
		}
		
		function getSymbols2 ($quote = '') {
			
			$this->func = __FUNCTION__;
			
			$this->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			$this->method = self::GET;
			$this->signed = false;
			
			$symbols = [];
			
			foreach ($this->connect ('v5/market/instruments-info')['result']['list'] as $symbol) {
				
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
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = true;
			
			$this->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$this->params['symbol'] = $this->pair ($base, $quote);
			else
				$this->params['settleCoin'] = $quote;
			
			return $this->connect ('v5/order/realtime')['result']['list'];
			
		}
		
		protected function createTypeOrder (string $symbol, array $order, string $side, string $func) {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::POST;
			$this->signed = true;
			
			$data = [
				
				'category' => $this->category ($order['quote']),
				'symbol' => $symbol,
				'orderType' => (isset ($order['price']) ? 'Limit' : 'Market'),
				'side' => $side,
				'timeInForce' => 'GTC',
				'reduceOnly' => ((isset ($order['close']) and $order['close']) ? 'true' : 'false'),
				//'closeOnTrigger' => ((isset ($order['close']) and $order['close']) ? 'true' : 'false'),
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
			
			if ($this->isHedgeMode ())
				$data['positionIdx'] = ($this->isLong () ? 1 : 2);
			else
				$data['positionIdx'] = 0;
			
			$this->params = $data;
			
			return $this->connect ('v5/order/create')['result'];
			
		}
		
		function createOrder ($base, $quote, $data = []) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			if ($this->openMarketType == self::MAKER)
				$data['price'] = (isset ($data['price']) ? $data['price'] : $this->entryPrice);
			
			if ($this->market == self::SPOT)
				$data['marketUnit'] = 'baseCoin';
			
			return $this->createTypeOrder ($this->pair ($data['base'], $data['quote']), $data, ($this->isLong () ? 'Buy' : 'Sell'), __FUNCTION__);
			
		}
		
		function closeOrder ($base, $quote, $data = []) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			$data['close'] = true;
			
			if ($this->market == self::SPOT)
				$data['marketUnit'] = 'quoteCoin';
			
			return $this->createTypeOrder ($this->pair ($data['base'], $data['quote']), $data, ($this->isLong () ? 'Sell' : 'Buy'), __FUNCTION__);
			
		}
		
		function decreaseOrder ($base, $quote, $data = []) {
			
			$data['base'] = $base;
			$data['quote'] = $quote;
			
			if ($this->closeMarketType == self::MAKER)
				$data['price'] = (isset ($data['price']) ? $data['price'] : $this->markPrice);
			
			return $this->createTypeOrder ($this->pair ($data['base'], $data['quote']), $data, ($this->isLong () ? 'Sell' : 'Buy'), __FUNCTION__);
			
		}
		
		function closeAllPositions ($quote = '') {
			
			foreach ($this->positions as $symbol => $side)
			foreach ($side as $data) {
				
				$data['close'] = true;
				$data['quote'] = $quote;
				
				$this->createTypeOrder ($symbol, $data, ($data['side'] == self::LONG ? 'Sell' : 'Buy'), __FUNCTION__);
				
			}
			
		}
		
		function editOrder ($base, $quote, $orders) {
			
			$this->func = __FUNCTION__;
			
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
			
			$this->params = [
				
				'category' => $this->category ($quote),
				'request' => $list,
				
			];
			
			$this->method = self::POST;
			$this->signed = true;
			
			return $this->connect ('v5/order/amend-batch')['result']['list'];
			
		}
		
		function editPosition ($base, $quote, $data): array {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::POST;
			$this->signed = true;
			
			$output = [];
			
			if (isset ($data['takeProfit']) or isset ($data['stopLoss']))
			if ($data['takeProfit'] > 0 or $data['stopLoss'] > 0) {
				
				$this->params = [
					
					'category' => $this->category ($quote),
					'symbol' => $this->pair ($base, $quote),
					'tpTriggerBy' => 'MarkPrice',
					'slTriggerBy' => 'MarkPrice',
					
				];
				
				if ($data['takeProfit'])
					$this->params['takeProfit'] = $data['takeProfit'];
				
				if ($data['stopLoss'])
					$this->params['stopLoss'] = $data['stopLoss'];
				
				if ($this->isHedgeMode ())
					$this->params['positionIdx'] = ($this->isLong () ? 1 : 2);
				else
					$this->params['positionIdx'] = 0;
				
				foreach ($this->connect ('v5/position/trading-stop')['result'] as $key => $value)
					$output[$key] = $value;
				
			}
			
			if ($this->market != self::SPOT) {
				
				if (
					(isset ($data['longLeverage']) and $data['longLeverage'] != 0) or
					(isset ($data['shortLeverage']) and $data['shortLeverage'] != 0) or
					(isset ($data['leverage']) and $data['leverage'] != 0)
				) {
					
					if (isset ($data['leverage']))
						$data['longLeverage'] =
						$data['shortLeverage'] =
						$data['leverage'];
					
					$this->params = [
						
						'symbol' => $this->pair ($base, $quote),
						'category' => $this->category ($quote),
						'buyLeverage' => $this->leverageRound ($data['longLeverage']),
						'sellLeverage' => $this->leverageRound ($data['shortLeverage']),
						
					];
					
					foreach ($this->connect ('v5/position/set-leverage')['result'] as $key => $value)
						$output[$key] = $value;
					
				}
				
				if (isset ($data['crossMargin'])) {
					
					if (!isset ($data['longLeverage'])) $data['longLeverage'] = $this->leverage;
					if (!isset ($data['shortLeverage'])) $data['shortLeverage'] = $this->leverage;
					
					if (isset ($data['leverage']))
						$data['longLeverage'] =
						$data['shortLeverage'] =
						$data['leverage'];
					
					$this->params = [
						
						'symbol' => $this->pair ($base, $quote),
						'category' => $this->category ($quote),
						'tradeMode' => ($data['crossMargin'] ? 0 : 1),
						'buyLeverage' => $this->leverageRound ($data['longLeverage']),
						'sellLeverage' => $this->leverageRound ($data['shortLeverage']),
						
					];
					
					foreach ($this->connect ('v5/position/switch-isolated')['result'] as $key => $value)
						$output[$key] = $value;
					
				}
				
				if (!isset ($data['margin']) and $this->extraMargin != 0)
					$data['margin'] = $this->extraMargin;
				
				if (isset ($data['margin']) and $data['margin'] != 0) {
					
					$this->params = [
						
						'symbol' => $this->pair ($base, $quote),
						'category' => $this->category ($quote),
						'margin' => round ($data['margin'], 4),
						
					];
					
					if ($this->isHedgeMode ())
						$this->params['positionIdx'] = ($this->isLong () ? 1 : 2);
					else
						$this->params['positionIdx'] = 0;
					
					foreach ($this->connect ('v5/position/add-margin')['result'] as $key => $value)
						$output[$key] = $value;
					
				}
				
			}
			
			return $output;
			
		}
		
		function cancelOrders ($base = '', $quote = '', $filter = '') {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::POST;
			$this->signed = true;
			
			$this->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$this->params['symbol'] = $this->pair ($base, $quote);
			elseif ($base)
				$this->params['baseCoin'] = $base;
			else
				$this->params['settleCoin'] = $quote;
			
			if ($filter == 'stop')
				$this->params['orderFilter'] = 'StopOrder';
			elseif ($filter == 'tpsl')
				$this->params['orderFilter'] = 'tpslOrder';
			
			return $this->connect ('v5/order/cancel-all')['result']['list'];
			
		}
		
		function longShortRatio ($base, $quote, $period) {
			
			$summary = [];
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$this->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'period' => $period,
				
			];
			
			$this->signed = false;
			
			foreach ($this->connect ('futures/data/topLongShortAccountRatio') as $value) {
				
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
		
		function getAccountStatus () {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = true;
			
			return $this->connect ('sapi/v1/account/status')['data'];
			
		}
		
		function getTickerPrice ($base, $quote) {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$this->params = [
				
				'category' => $this->category ($quote),
				
			];
			
			if ($base and $quote)
				$this->params['symbol'] = $this->pair ($base, $quote);
			
			if ($pairs = $this->connect ('v5/market/tickers')['result']['list']) {
				
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
			
			/*$this->func = __FUNCTION__;
			
			$this->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'mode' => $hedge ? 'BothSide' : 'MergedSingle',
				
			];
			
			return $this->connect ('private/linear/position/switch-mod')['result'];*/
			
			return '';
			
		}
		
		function futuresTradingStatus ($base = '', $quote = '') {
			
			$this->func = __FUNCTION__;
			
			$this->params = [];
			
			if ($base and $quote)
				$this->params['symbol'] = $this->pair ($base, $quote);
			
			$this->method = self::GET;
			
			$this->debug = 0;
			
			$data = $this->connect ('fapi/v1/apiTradingStatus');
			
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
		
		function positionActive (): bool {
			return ($this->position and $this->position['quantity'] > 0);
		}
		
		function isCrossMargin (): bool {
			return ($this->crossMargin and $this->position and $this->position['crossMargin']);
		}
		
		function withdraw ($coin, $address, $chain, $amount, $data = []): array {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::POST;
			$this->signed = true;
			
			$this->params = [
				
				'coin' => $coin,
				'chain' => $chain,
				'forceChain' => 1,
				'address' => $address,
				'amount' => $this->quantity ($amount),
				'accountType' => ($this->market == self::SPOT ? 'SPOT' : 'FUND'),
				
			];
			
			foreach ($data as $key => $value)
				$this->params[$key] = $value;
			
			return $this->connect ('v5/asset/withdraw/create')['result'];
			
		}
		
		function longShortGlobalAccountsRatio ($base, $quote, $data) {
			
			$this->func = __FUNCTION__;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$this->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'period' => $data['interval'],
				
			];
			
			if (!isset ($data['limit']) or $data['limit'] <= 0)
				$data['limit'] = 500;
			
			$this->params['limit'] = $data['limit'];
			
			$this->market = BinanceRequest::FUTURES;
			$this->signed = false;
			
			$summary = [];
			
			foreach ($this->connect ('v5/market/account-ratio')['result']['list'] as $value) {
				
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
			
			$this->func = __FUNCTION__;
			
			$this->params = [
				
				'symbol' => $this->pair ($base, $quote),
				'category' => $this->category ($quote),
				'intervalTime' => $this->times[$data['interval']],
				
			];
			
			if (!isset ($data['limit']) or $data['limit'] <= 0)
				$data['limit'] = 500;
			
			$this->params['limit'] = $data['limit'];
			
			if (isset ($data['start_time']))
				$this->params['startTime'] = ($data['start_time'] * 1000);
			
			if (isset ($data['end_time']))
				$this->params['endTime'] = ($data['end_time'] * 1000);
			
			$this->market = BinanceRequest::FUTURES;
			
			$this->method = self::GET;
			$this->signed = false;
			
			$summary = [];
			
			foreach ($this->connect ('v5/market/open-interest')['result']['list'] as $value) {
				
				$summary[] = [
					
					'value' => $value['openInterest'],
					'date' => ($value['timestamp'] / 1000),
					'date_text' => $this->date ($value['timestamp'] / 1000),
					
				];
				
			}
			
			return $summary;
			
		}
		
		function createSocket (): ?Socket {
			return new Socket\BybitSocket ($this);
		}
		
		public
			$apiUrl = 'https://api.bybit.com',
			$futuresUrl = 'https://api.bybit.com',
			
			$testApiUrl = 'https://api-testnet.bybit.com',
			$testFuturesUrl = 'https://api-testnet.bybit.com';
		
		function connect ($path) {
			
			$ch = curl_init ();
			
			if ($this->debug == 1 and $this->debug == 1) {
				
				if ($this->market == \Exchange::FUTURES)
					$url = $this->testFuturesUrl;
				else
					$url = $this->testApiUrl;
				
			} else {
				
				if ($this->market == \Exchange::FUTURES)
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
				//CURLOPT_SSL_VERIFYPEER => false,
				//CURLOPT_SSL_VERIFYHOST => false,
				//CURLOPT_HEADER => 1,
				
			];
			
			if ($this->debug == 2)
				debug ($options[CURLOPT_URL]);
			
			$this->time = $this->time ();
			
			if ($this->method == self::POST) {
				
				$options[CURLOPT_CUSTOMREQUEST] = $this->method;
				$options[CURLOPT_POSTFIELDS] = http_build_query ($this->params);
				
				if ($this->signed) {
					
					$options[CURLOPT_HTTPHEADER][] = 'X-BAPI-SIGN: '.$this->signature ();
					$options[CURLOPT_HTTPHEADER][] = 'X-BAPI-API-KEY: '.$this->cred['key'];
					$options[CURLOPT_HTTPHEADER][] = 'X-BAPI-TIMESTAMP: '.$this->time ();
					$options[CURLOPT_HTTPHEADER][] = 'X-BAPI-RECV-WINDOW: '.$this->recvWindow;
					
				}
				
			} elseif ($this->method == self::PUT)
				$options[CURLOPT_PUT] = true;
			elseif ($this->method != self::GET)
				$options[CURLOPT_CUSTOMREQUEST] = $this->method;
			
			$options[CURLOPT_HTTPHEADER][] = 'Connection: keep-alive';
			
			if ($this->proxies) {
				
				$proxy = trim ($this->proxies[mt_rand (0, count ($this->proxies) - 1)]);
				
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
			
			$this->queryNum++;
			
			$options[CURLOPT_SSL_CIPHER_LIST] = 'TLSv1';
			
			if ($error = curl_error ($ch))
				throw new \ExchangeException ($this, $error, curl_errno ($ch), $options, $this->func);
			elseif (in_array ($info['http_code'], $this->errorCodes))
				throw new \ExchangeException ($this, http_get_message ($info['http_code']).' ('.$options[CURLOPT_URL].')', $info['http_code'], $options, $this->func);
			//debug ($data);
			$data = json2array ($data);
			
			curl_close ($ch);
			
			if (isset ($data['ret_code']) and $data['ret_code'] != 0)
				throw new \ExchangeException ($this, $data['ret_msg'], $data['ret_code'], $options, $this->func);
			elseif (isset ($data['retCode']) and $data['retCode'] != 0) // v5
				throw new \ExchangeException ($this, $data['retMsg'], $data['retCode'], $options, $this->func);
			
			return $data;
			
		}
		
		protected function signature () {
			return hash_hmac ('sha256', $this->time.$this->cred['key'].$this->recvWindow.http_build_query ($this->params), $this->cred['secret']);
		}
		
		function connect2 ($url) {
			
			$ch = curl_init ();
			
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt ($ch, CURLOPT_USERAGENT, $this->userAgent ? $this->userAgent : get_useragent ());
			curl_setopt ($ch, CURLOPT_COOKIE, $this->cookies);
			
			$data = curl_exec ($ch);
			$info = curl_getinfo ($ch);
			
			if ($error = curl_error ($ch))
				throw new \ExchangeException ($this, $error, curl_errno ($ch), $info, $this->func);
			elseif ($info['http_code'] != 200)
				throw new \ExchangeException ($this, 'Access denied', $info['http_code'], $info, $this->func);
			
			curl_close ($ch);
			
			return json_decode ($data, true);
			
		}
		
	}