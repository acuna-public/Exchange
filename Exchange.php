<?php
	
	require 'ExchangeException.php';
	
	//require 'WebSocketException.php';
	require 'WebSocket.php';
	
	abstract class Exchange {
		
		public
			$debug = 0,
			$timeOffset = 0,
			$recvWindow = 60000, // 1 minute
			$dateFormat = 'd.m.y H:i',
			$sleep = 0;
		
		public
			$amount = 3,
			$precision = 2,
			$quotePrecision = 2;
		
		public
			$marginPercent = 100,
			$balancePercent = 100,
			$prunedPercent = 99,
			$leverage = 0,
			$quantity = 0,
			$balance = 0,
			$openBalance = 0,
			$walletBalance = 0,
			$stopLoss = 0,
			$entryPrice = 0,
			$markPrice = 0,
			$minQuantity = 0,
			$maxQuantity = 0,
			$balanceAvailable = 0,
			$initialMarginRate = 0,
			$maintenanceMarginRate = 0;
		
		public
			$margin = 0,
			$extraMargin = 0,
			$liquidPrice = 0,
			$base = '', $quote = '';
		
		public
			$cred = [],
			$openFee = 0,
			$closeFee = 0,
			$fees = 0,
			$cookies = '',
			$userAgent = '',
			$queryNum = 0,
			$feesRate = [],
			$proxies = [],
			$position = [],
			$positions = [],
			$orders = [],
			$curChanges = [];
		
		protected
			$grossPNL = 0, $netPNL = 0,
			$lastDate = 0,
			$balances = [];
		
		public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
		
		public
			$flevel = 0,
			$rebate = 10,
			$ftype = self::FTYPE_USD,
			$openMarketType = self::TAKER,
			$closeMarketType = self::TAKER,
			$hedgeMode = false,
			$crossMargin = true;
		
		public $side = self::LONG, $market = self::SPOT;
		
		const SPOT = 'SPOT', FUTURES = 'FUTURES';
		const PRICES_INDEX = 1, PRICES_MARK = 2, PRICES_LAST = 3;
		
		const LONG = 'LONG', SHORT = 'SHORT', BOTH = 'BOTH', BUY = 'BUY', SELL = 'SELL', MAKER = 'MAKER', TAKER = 'TAKER', BALANCE_AVAILABLE = 'available', BALANCE_TOTAL = 'total', FTYPE_USD = 'USD', FTYPE_COIN = 'COIN';
		
		static $PERPETUAL = 'PERPETUAL', $LEVERAGED = 'LEVERAGED', $BOTH = 'BOTH';
		
		public $timeframes = [
			
			'm' => 'minutes',
			'h' => 'hours',
			'd' => 'days',
			'w' => 'weeks',
			'M' => 'months',
			'y' => 'years',
			
		];
		
		abstract function getName ();
		abstract function getTitle ();
		abstract function getVersion ();
		
		function setCredentials ($cred) {
			$this->cred = $cred;
		}
		
		function timeOffset () {}
		
		function changeSide () {
			
			if ($this->isLong ())
				$this->side = self::SHORT;
			else
				$this->side = self::LONG;
			
		}
		
		function getSide () {
			return ($this->isHedgeMode () ? $this->side : self::BOTH);
		}
		
		function setSide ($side) {
			
			if ($side == self::BOTH)
				throw new \ExchangeException ($this, 'Side can be only LONG of SHORT');
			
			$this->side = $side;
			
		}
		
		function getSides () {
			return ($this->isHedgeMode () ? [self::LONG, self::SHORT] : [self::BOTH]);
		}
		
		function isLong () {
			return ($this->side == self::LONG);
		}
		
		function isShort () {
			return ($this->side == self::SHORT);
		}
		
		function isHedgeMode () {
			return ($this->hedgeMode and $this->market != self::SPOT);
		}
		
		function getSustainableLoss () {
			return ($this->balanceAvailable - $this->getMaintenanceMargin ());
		}
		
		function getLiquidationPrice2 ($quote) {
			
			if ($this->quantity > 0) {
				
				$price = $this->walletBalance - 0 + 0 + ((($this->maintenanceMarginRate / 100) * $this->entryPrice) / 100) + 0 + 0;
				$price -= ($this->isLong () ? 1 : -1) * $this->quantity * $this->entryPrice - 0 * 0 + 0 * 0;
				$price /= $this->quantity * ($this->maintenanceMarginRate / 100) + 0 * 0 + 0 * 0;
				
				$price = $this->price ($price);
				
				return ($price > 0 ? $price : 0);
				
			} else return 0;
			
		}
		
		protected function getExtraMargin () {
			
			if (!$this->crossMargin and $this->marginPercent < 100)
				$extraMargin = ($this->balance - $this->margin);
			else
				$extraMargin = 0;
			
			return $extraMargin;
			
		}
		
		function getMaintenanceMargin () {
			return ($this->maintenanceMarginRate / 100);
		}
		
		protected function getLiquidationPrice ($quote) {
			
			if ($this->crossMargin or $quote == 'USDC') {
				
				if ($this->isLong ())
					$price = $this->entryPrice - ((($this->margin + $this->extraMargin + $this->getInitialMargin ()) - $this->getMaintenanceMargin ()) / $this->quantity);
				else
					$price = $this->entryPrice + ((($this->margin + $this->extraMargin + $this->getInitialMargin ()) - $this->getMaintenanceMargin ()) / $this->quantity);
				
			} else {
				
				if ($this->isLong ())
					$price = $this->entryPrice * (1 - $this->initialMarginRate () + $this->getMaintenanceMargin ()) - ($this->extraMargin / $this->quantity);
				else
					$price = $this->entryPrice * (1 + $this->initialMarginRate () - $this->getMaintenanceMargin ()) + ($this->extraMargin / $this->quantity);
				
			}
			
			return $this->price ($price);
			
		}
		
		function getMarginPercent ($stopLoss) {
			
			if ($this->isLong ())
				$stopPrice = ($this->entryPrice - $stopLoss);
			else
				$stopPrice = ($this->entryPrice + $stopLoss);
			
			$diff = $this->getProfit ($this->entryPrice, $percent->valueOf ($stopLoss));
			
		}
		
		protected function getInitialMargin () {
			
			//if ($this->crossMargin)
				return ($this->entryPrice * ($this->quantity / $this->leverage));
			//else
			//	return ($this->initialMarginRate / 100);
			
		}
		
		function initialMarginRate () {
			return (1 / $this->leverage);
		}
		
		function getStopLoss ($quote, $entryPrice) {
			
			$price = $this->getLiquidationPrice ($entryPrice);
			
			if ($this->stopLoss <= 0) $this->stopLoss = $this->liquid;
			
			$percent = (($entryPrice * $this->stopLoss) / 100);
			
			if ($this->isLong ()) {
				
				$stopPrice = $this->price ($entryPrice - $percent);
				if ($stopPrice > $price) $price = $stopPrice;
				
			} else {
				
				$stopPrice = $this->price ($entryPrice + $percent);
				if ($stopPrice < $price) $price = $stopPrice;
				
			}
			
			return $price;
			
		}
		
		function getTakeProfit ($entryPrice) {
			
			if ($this->takeProfit < 0) $this->takeProfit = $this->liquid;
			
			if ($this->takeProfit > 0) {
				
				$percent = (($entryPrice * $this->takeProfit) / 100);
				
				if ($this->isLong ())
					return $this->price ($entryPrice + $percent);
				else
					return $this->price ($entryPrice - $percent);
				
			} else return 0;
			
		}
		
		function setMarginType ($base, $quote, $longLeverage = 0, $shortLeverage = 0) {}
		
		function liquidPricePercent () {
			
			$price = ($this->liquidPrice - $this->entryPrice);
			
			if ($this->liquidPrice > 0)
				return (($price * 100) / $this->entryPrice);
			else
				return 0;
			
		}
		
		function getMarginQuantity ($price) {
			return ($this->getSustainableLoss () / $price);
		}
		
		function getAdditionalMargin ($stopPrice) { // TODO
			
			if ($this->isLong ())
				return ($this->getLiquidationPrice () * $this->quantity) - ($stopPrice * $this->quantity);
			else
				return ($stopPrice * $this->quantity) - ($this->getLiquidationPrice () * $this->quantity);
			
		}
		
		abstract function getPrices (int $type, string $base, string $quote, array $data): array;
		
		protected abstract function getBalances ($type, $quote = ''): array;
		
		function getFuturesBalance ($type, $quote = '') {
			return $this->getBalance ($type, $quote);
		}
		
		final function getBalance ($type, $quote = '') {
			
			if (!$this->balances)
				$this->balances = $this->getBalances ($type, $quote);
			
			return $this->balances[$quote][$type];
			
		}
		
		function getPositionData ($base, $quote) {
			
			if (isset ($this->positions[$base.$quote]))
				$this->position = $this->positions[$base.$quote][$this->getSide ()];
			else
				$this->position = [];
			
		}
		
		function setPositionData ($base, $quote, $data) {
			$this->positions[$base.$quote][$this->getSide ()] = $data;
		}
		
		function clean ($quote) {
			
			$this->balances = [];
			
			$this->orders = [];
			$this->positions = $this->getPositions ('', $quote);
			
		}
		
		function getTicker ($base, $quote) {
			return $this->getTickerPrice ($base, $quote)[$this->pair ($base, $quote)];
		}
		
		abstract function positionActive (): bool;
		
		function getPNL () {
			return $this->netPNL;
		}
		
		function getGrossPNL () {
			return $this->grossPNL;
		}
		
		function getROE ($margin) {
			return ($this->netPNL * 100) / (($this->quantity / $this->leverage) * $this->entryPrice);
		}
		
		function getROI ($margin) {
			return ($this->getROE ($margin) * $this->leverage);
		}
		
		function getProfit ($entry, $exit) {
			
			if ($this->isLong ())
				return ($exit - $entry);
			else
				return ($entry - $exit);
			
		}
		
		function getQuantity () {
			
			if ($this->entryPrice > 0)
				return $this->amount ($this->getNotional () / $this->entryPrice);
			else
				throw new \ExchangeException ($this, 'Price must be higher than 0');
			
		}
		
		function getNotional () {
			return ($this->margin * $this->leverage);
		}
		
		/*function setLeverage ($leverage) {
			
			$this->leverage = $leverage;
			
			if ($this->leverage <= 1)
				throw new \ExchangeException ($this, 'Leverage must be higher than 0');
			
		}*/
		
		function start ($base, $quote) {
			
			//if ($this->leverage == 0)
			//	$this->leverage = $this->getLeverage ($base, $quote);
			
			$this->openFee = $this->getOpenFee ();
			$this->closeFee = $this->getCloseFee ();
			
		}
		
		function update ($base, $quote) {
			
			$this->grossPNL = ($this->getProfit ($this->entryPrice, $this->markPrice) * $this->quantity);
			
			$this->fees = ($this->openFee + $this->closeFee);
			
			$this->netPNL = ($this->grossPNL - $this->fees);
			
			$this->margin = $this->getInitialMargin ();
			$this->extraMargin = $this->getExtraMargin ();
			$this->liquidPrice = $this->getLiquidationPrice ($quote);
			
		}
		
		final function open () {
			
			$this->netPNL = 0;
			
			$this->debug ($this->openBalance);
			
			if ($this->openBalance > 0 and $this->balanceAvailable > 0) {
				
				if ($this->marginPercent <= 0 or $this->marginPercent > 100)
					$this->marginPercent = 100;
				
				if ($this->balancePercent <= 0 or $this->balancePercent > 100)
					$this->balancePercent = 100;
				
				if ($this->prunedPercent <= 0 or $this->prunedPercent > 100)
					$this->prunedPercent = 100;
				
				if ($this->balancePercent == 100)
					$this->balancePercent = $this->prunedPercent;
				
				if ($this->margin <= 0) {
					
					$percent = new \Percent ($this->openBalance);
					$this->balance = $percent->valueOf ($this->balancePercent);
					
					$percent = new \Percent ($this->balance);
					$this->margin = $percent->valueOf ($this->marginPercent);
					
				}
				
				$this->quantity = $this->getQuantity ();
				
				$this->debug (222, $this->quantity);
				
				$min = $this->minQuantity ();
				$max = $this->maxQuantity ();
				
				if ($this->margin > 0 and $this->quantity >= $min) {
					
					$quantity = $this->quantity;
					
					if ($max > 0 and $this->quantity > $max)
						$this->quantity = $max;
					
					$margin = $this->margin;
					
					if ($this->quantity != $quantity) {
						
						$percent = new \Percent ($this->quantity);
						
						$percent->delim = $quantity;
						
						$this->margin = $percent->valueOf ($this->margin);
						
						$margin -= $this->margin;
						
					}
					
					//$this->debug ($this->balanceAvailable, $this->openBalance);
					
					if ($margin >= 0 and $this->balanceAvailable > 0 and $this->balanceAvailable >= $this->openBalance) {
						
						$this->balanceAvailable -= $this->openBalance;
						
						return true;
						
					}
					
				}// else $this->debug ($this->quantity, $min);
				
			}
			
			return false;
			
		}
		
		final function close () {
			
			$this->margin += $this->netPNL;
			$this->balance += $this->netPNL;
			$this->walletBalance += $this->netPNL;
			
			if ($this->balance < 0)
				$this->balance = 0;
			
			$this->balanceAvailable += $this->balance;
			
		}
		
		function getFeeRate ($marketType) {
			
			$value = $this->feesRate[$this->market][$this->ftype][$this->flevel][($marketType == self::MAKER ? 0 : 1)];
			
			$percent = new \Percent ($value);
			$value -= $percent->valueOf ($this->rebate);
			
			return ($value / 100);
			
		}
		
		function getOpenFee () {
			
			if ($this->entryPrice > 0 and $this->quantity > 0)
				return $this->entryPrice * $this->quantity * $this->getFeeRate ($this->openMarketType);
			else
				return 0;
			
		}
		
		function getCloseFee () {
			
			if ($this->entryPrice > 0 and $this->quantity > 0)
				return $this->markPrice * $this->quantity * $this->getFeeRate ($this->closeMarketType);
			else
				return 0;
			
		}
		
		function getBankruptcyPrice () {
			
			if ($this->isLong ())
				return $this->entryPrice * ($this->leverage - 1) / $this->leverage;
			else
				return $this->entryPrice * ($this->leverage + 1) / $this->leverage;
			
		}
		
		function getRPRatio ($entryPrice, $takeProfit, $stopLoss) {
			
			$output  = $this->getProfit ($entryPrice, $stopLoss);
			$output /= $this->getProfit ($takeProfit, $entryPrice);
			
			return $output;
			
		}
		
		function toPoint () {
			return (1 / pow (10, $this->basePrecision));
		}
		
		function pair ($base, $quote) {
			
			if (isset ($this->curChanges[$base]))
				$base = $this->curChanges[$base];
			
			return $base.$quote;
			
		}
		
		function createOrder ($type, $base, $quote, $price) {} // TODO
		
		abstract function getOrders ($base, $quote);
		abstract function getOrderInfo ($id);
		
		function editPosition ($base, $quote, $data) {}
		
		function setLeverage ($base, $quote, $leverage) {}
		function setFuturesMarginType ($base, $quote, $longLeverage = 10, $shortLeverage = 10) {}
		
		function getPositions ($base = '', $quote = '') {}
		
		function isOrderStopLoss ($order) {
			return false;
		}
		
		function orderId ($order) {
		}
		
		function orderName ($order) {
			return $this->orderId ($order);
		}
		
		abstract function getTrades ($base, $quote);
		abstract function getSymbols ($quote = '');
		abstract function isOrderTakeProfit ($order);
		abstract function orderCreateDate ($order);
		abstract function cancelOrders ($base = '', $quote = '', $filter = '');
		
		function getFuturesOpenOrders ($base, $quote) {}
		function getFuturesFilledOrders ($base, $quote) {}
		function createFuturesTakeProfitOrder ($orders) {}
		function createFuturesStopOrder ($orders) {} // TODO
		function createFuturesTrailingStopOrder ($order) {}
		
		function getMarginType ($base, $quote) {}
		
		function cancelFuturesOrders ($base, $quote, array $ids) {}
		
		function futuresOrderCreateDate ($order) {
			return $this->orderCreateDate ($order);
		}
		
		function cancelFuturesOrdersNames ($base, $quote, array $ids) {
			return [];
		}
		
		function getAccountStatus () {
			return '';
		}
		
		abstract function getTickerPrice ($base, $quote);
		
		function getVolatility ($base, $quote, $interval = '1h') {
			
			$date = new \Date ();
			
			$charts = $this->getMarkPrices ($base, $quote, ['interval' => $interval, 'start_time' => $date->add (-\Date::DAY * 1)->getTime ()]);
			
			$min = $charts[0]['close'];
			$max = 0;
			
			foreach ($charts as $price) {
				
				if ($price['close'] > $max)
					$max = $price['close'];
				elseif ($price['close'] < $min)
					$min = $price['close'];
				
			}
			
			return ((($max - $min) * 100) / $max);
			
		}
		
		function amount ($amount) {
			return round ($amount, $this->amount);
		}
		
		function price ($price) {
			return round ($price, $this->basePrecision);
		}
		
		function quoteRound ($price) {
			return round ($price, $this->quotePrecision);
		}
		
		function date ($date) {
			return date ($this->dateFormat, $date);
		}
		
		abstract function setFuturesHedgeMode (bool $hedge, $base = '', $quote = '');
		
		function getFuturesHedgeMode () {
			return false;
		}
		
		function getUFR ($execOrders, $totalOrders) {
			return (1 - ($execOrders / $totalOrders));
		}
		
		abstract function orderData (array $order);
		
		function getAnnouncements ($data = []) {
			return [];
		}
		
		function setPairsFuturesHedgeMode (bool $hedge) {
			
			try {
				$this->setFuturesHedgeMode ($hedge);
			} catch (\ExchangeException $e) {
				// ignore
			}
			
		}
		
		function setPairFuturesHedgeMod ($base, $quote) {
			
		}
		
		protected function quantity ($quantity) {
			return $quantity;
		}
		
		function getAllPrices (int $type, string $base, string $quote, array $data, $callback) {
			
			$end = $data['end_time'];
			$data['end_time'] = 0;
			
			$date = new \DateTime ();
			
			$date->setTimestamp ($data['start_time']);
			
			do {
				
				$prices = $this->getPrices ($type, $base, $quote, $data);
				
				$data3 = [];
				
				$i = end_key ($prices);
				
				$end2 = $prices[0]['date'];
				
				//debug ([1, $this->date ($data['start_time']), $this->date ($end2)]);
				
				do {
					
					$price = $prices[$i];
					
					if ($price['date'] <= $end) {
						
						$time = $date->getTimestamp ();
						
						if ($time == $price['date']) {
							
							$data3[] = $price;
							
						} else/* {
							
							$data3[] = [
								
								'low' => 0,
								'high' => 0,
								'open' => 0,
								'close' => 0,
								'volume' => 0,
								'date' => $time,
								'date_text' => $this->date ($time),
								
							];
							
						}*/
						
						throw new \ExchangeException ($this, 'Wrong date: '.$price['date_text'].'. Expected: '.$date->format ($this->dateFormat));
						
						//debug ($price['date_text']);
						
					}
					
					$date->modify ('+'.$this->timeframe2 ($data['interval']));
					
					$i--;
					
				} while ($i >= 0 and $price['date'] <= $end2);
				
				//debug (111);
				
				$callback ($data, $data3);
				
				$data['start_time'] = ($end2 + ($this->timeframe ($data['interval'])));
				
			} while ($prices and $data['start_time'] <= $end);
			
		}
		
		function minQuantity () {
			return $this->minQuantity;
		}
		
		function maxQuantity () {
			return $this->maxQuantity;
		}
		
		function setMode ($base, $quote) {}
		function cancelOrderName ($base, $quote, $name) {}
		
		function openPosition ($base, $quote, $data = []) {}
		function closePosition ($base, $quote, $data = []) {}
		function decreasePosition ($base, $quote, $data = []) {}
		
		function timeframe ($timeframe) { // From cctx
			
			$scales = [];
			
			$scales['s'] = 1;
			$scales['m'] = $scales['s'] *  60;
			$scales['h'] = $scales['m'] *  60;
			$scales['d'] = $scales['h'] *  24;
			$scales['w'] = $scales['d'] *   7;
			$scales['M'] = $scales['w'] *  30;
			$scales['y'] = $scales['M'] * 365;
			
			$amount = substr ($timeframe, 0, -1);
			$unit = substr ($timeframe, -1);
			
			return ($amount * $scales[$unit]);
			
		}
		
		function timeframe2 ($timeframe) {
			
			$amount = substr ($timeframe, 0, -1);
			$unit = substr ($timeframe, -1);
			
			return ($amount.' '.$this->timeframes[$unit]);
			
		}
		
		function getMinMargin () {
			return ($this->entryPrice * ($this->minQuantity / $this->leverage));
		}
		
		function getCompoundIncome ($value, $rate) {
			
			$value += ($value * ($rate / 100));
			
			return $value;
			
		}
		
		function pricesSocketTopic (int $type, string $base, string $quote, array $data) {
			return '';
		}
		
		function createSocket (): ?\Socket {
			return null;
		}
		
		function debug (...$data) {
			return debug ($this->date ($this->markDate).': '.$this->pair ($this->base, $this->quote).$this->side.': '.array2json ($data));
		}
		
	}
	
	abstract class Request {
		
		public
			$params = [],
			$method = self::POST,
			$signed = true,
			$debug = 0,
			$errorCodes = [404],
			$showUrl = false,
			$func,
			$order;
		
		const GET = 'GET', POST = 'POST', PUT = 'PUT', DELETE = 'DELETE';
		
		function __construct ($exchange, $func, $order = []) {
			
			$this->exchange = $exchange;
			$this->func = $func;
			$this->order = $order;
			
		}
		
	}
	
	abstract class Socket extends \WebSocket {
		
		public $func;
		public \Exchange $exchange;
		
		public $data = [], $topics = [];
		
		function __construct (\Exchange $exchange) {
			$this->exchange = $exchange;
		}
		
		abstract function ping ();
		abstract function getPrice ($start): array;
		abstract function getPricesTopic (int $type, string $base, string $quote, array $data): string;
		abstract function publicConnect (): ?\Socket;
		abstract function privateConnect (): ?\Socket;
		
		function connect ($path): \Socket {
			
			$this->debug = ($this->exchange->debug == 2 ? 1 : 0);
			
			return parent::connect ($path);
			
		}
		
	}