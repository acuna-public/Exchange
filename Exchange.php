<?php
	
	require 'ExchangeException.php';
	
	//require 'WebSocketException.php';
	//require 'WebSocket.php';
	
	abstract class Exchange {
		
		public
			$debug = 0,
			$hedgeMode = false,
			$timeOffset = 0,
			$recvWindow = 60000, // 1 minute
			$dateFormat = 'd.m.y H:i',
			$sleep = 0;
		
		public
			$amount = 3,
			$precision = 2,
			$marginPercent = 100,
			$balancePercent = 100,
			$pnl = 0,
			$roe = 0, $roi = 0,
			$quantity = 0,
			$balance = 0,
			$leverage = 0,
			$openBalance = 0,
			$walletBalance = 0,
			$stopLoss = 0,
			$entryPrice = 0,
			$markPrice = 0,
			$minQuantity = 0,
			$maxQuantity = 0,
			$extraMargin = 0,
			$balanceAvailable = 0,
			$initialMarginRate = 0,
			$maintenanceMarginRate = 0;
		
		public
			$liquid = 0,
			$margin = 0;
		
		public
			$cred = [],
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
			$lastDate = 0,
			$balances = [];
		
		public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
		
		public $flevel = 0, $rebate = 10, $ftype = self::FTYPE_USD, $feeModel = self::TAKER;
		
		public $side = self::LONG, $marginType = self::ISOLATED, $market = self::SPOT;
		
		const SPOT = 'SPOT', FUTURES = 'FUTURES';
		
		const LONG = 'LONG', SHORT = 'SHORT', ISOLATED = 'ISOLATED', CROSS = 'CROSS', BUY = 'BUY', SELL = 'SELL', MAKER = 'MAKER', TAKER = 'TAKER', BALANCE_AVAILABLE = 'available', BALANCE_TOTAL = 'total', FTYPE_USD = 'USD', FTYPE_COIN = 'COIN';
		
		static $PERPETUAL = 'PERPETUAL', $LEVERAGED = 'LEVERAGED', $BOTH = 'BOTH';
		
		public $timeframes = [
			
			'm' => 'minute',
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
		
		function isLong () {
			return ($this->side == self::LONG);
		}
		
		function isShort () {
			return ($this->side == self::SHORT);
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
		
		function getLiquidationPrice ($quote) {
			
			if ($this->quantity > 0) {
				
				$price = $this->entryPrice;
				
				if ($this->marginType == self::CROSS) {
					
					if ($this->isLong ())
						$price -= ($this->getSustainableLoss () / $this->quantity);
					else
						$price += ($this->getSustainableLoss () / $this->quantity);
					
				} elseif ($this->extraMargin >= 0) {
					
					if ($quote == 'USDT') {
						
						if ($this->isLong ())
							$price *= (1 - $this->getInitialMargin () + $this->getMaintenanceMargin ()) - ($this->extraMargin / $this->quantity);
						else
							$price *= (1 + $this->getInitialMargin () - $this->getMaintenanceMargin ()) + ($this->extraMargin / $this->quantity);
						
					} elseif ($quote == 'USDC') {
						
						if ($this->isLong ())
							$price += (($this->getInitialMargin () + $this->extraMargin - $this->getMaintenanceMargin ()) / $this->quantity);
						else
							$price -= (($this->getInitialMargin () + $this->extraMargin - $this->getMaintenanceMargin ()) / $this->quantity);
						
					}
					
				}
				
				$price = $this->price ($price);
				
				return ($price > 0 ? $price : 0);
				
			} else return 0;
			
		}
		
		function getMarginPercent ($stopLoss) {
			
			if ($this->isLong ())
				$stopPrice = ($this->entryPrice - $stopLoss);
			else
				$stopPrice = ($this->entryPrice + $stopLoss);
			
			$diff = $this->getProfit ($this->entryPrice, $percent->valueOf ($stopLoss));
			
		}
		
		function getInitialMargin () {
			
			if ($this->marginType == self::CROSS)
				return (($this->quantity * $this->entryPrice) / $this->leverage);
			else
				return ($this->initialMarginRate / 100);
			
		}
		
		function getMaintenanceMargin2 () {
			return $this->entryPrice * $this->quantity * ($this->maintenanceMarginRate / 100) - (($this->initialMarginRate * ($this->entryPrice * $this->quantity)) / 100);
		}
		
		function getMaintenanceMargin () {
			return ($this->maintenanceMarginRate / 100);
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
		
		function liquidPricePercent ($liquidPrice) {
			
			$price = ($this->entryPrice - $liquidPrice);
			return (($price * 100) / $this->entryPrice);
			
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
		
		abstract function getPrices ($base, $quote, array $data): array;
		
		function getMarkPrices ($base, $quote, array $data): array {
			return $this->getPrices ($base, $quote, $data);
		}
		
		protected abstract function getBalances ($type, $quote = ''): array;
		
		function getFuturesBalance ($type, $quote = '') {
			return $this->getBalance ($type, $quote);
		}
		
		final function getBalance ($type, $quote = '') {
			
			if (!$this->balances)
				$this->balances = $this->getBalances ($type, $quote);
			
			return $this->balances[$quote][$type];
			
		}
		
		function getPosition ($base, $quote) {
			
			if (!$this->positions) $this->positions = $this->getPositions ('', $quote);
			
			if (isset ($this->positions[$this->pair ($base, $quote)])) {
				
				if ($this->hedgeMode) {
					
					if (isset ($this->positions[$this->pair ($base, $quote)][$this->side]))
						$this->position = $this->positions[$this->pair ($base, $quote)][$this->side];
					else
						$this->position = [];
					
				} else $this->position = $this->positions[$this->pair ($base, $quote)];
				
			}
			
		}
		
		abstract function positionActive (): bool;
		
		function getPNL () {
			return ($this->getProfit ($this->entryPrice, $this->markPrice) * $this->quantity);
		}
		
		function getROE () {
			
			$pnl = ($this->pnl * 100);
			
			$quantity = ($this->quantity * $this->entryPrice);
			
			if ($quantity > 0)
				$pnl /= $quantity;
			else
				//throw new \ExchangeException ('Quantity must be higher than 0');
				$pnl = 0;
			
			return $pnl;
			
		}
		
		function getROI () {
			return ($this->roe * $this->leverage);
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
				throw new \ExchangeException ('Price must be higher than 0');
			
		}
		
		function getNotional () {
			return ($this->margin * $this->leverage);
		}
		
		function start () {
			
			if ($this->leverage == 0) // NULL
				$this->leverage = $this->getLeverage ();
			
			$this->liquid = (100 / $this->leverage);
			
		}
		
		function update () {
			
			$this->pnl = $this->getPNL ();
			$this->roe = $this->getROE ();
			$this->roi = $this->getROI ();
			
		}
		
		final function open () {
			
			$this->pnl = $this->roe = $this->roi = $this->margin = $this->fees = $this->extraMargin = 0;
			
			if ($this->openBalance > 0 and $this->balanceAvailable > 0) {
				
				$this->entryPrice = $this->markPrice;
				
				if ($this->balancePercent <= 0 or $this->balancePercent > 100)
					$this->balancePercent = 100;
				
				$percent = new \Percent ($this->openBalance);
				$this->balance = $percent->valueOf ($this->balancePercent);
				
				if ($this->marginPercent <= 0 or $this->marginPercent > 100)
					$this->marginPercent = 100;
				
				$percent = new \Percent ($this->balance);
				$this->margin = $percent->valueOf ($this->marginPercent);
				
				$this->quantity = $quantity = $this->getQuantity ();
				
				if ($this->quantity > 0 and $this->margin > 0) {
					
					$min = $this->minQuantity ();
					$max = $this->maxQuantity ();
					
					if ($min > 0 and $this->quantity < $min)
						$this->quantity = $min;
					elseif ($max > 0 and $this->quantity > $max)
						$this->quantity = $max;
					
					$this->fees = $this->getOpenFee ();
					
					$margin = $this->margin;
					
					if ($this->quantity != $quantity) {
						
						$percent = new \Percent ($this->quantity);
						
						$percent->delim = $quantity;
						
						$this->margin = $percent->valueOf ($this->margin);
						
						$margin -= $this->margin;
						
					}
					
					if ($margin >= 0) {
						
						$balanceAvailable = ($this->balanceAvailable - $this->openBalance);
						
						if ($balanceAvailable >= 0) {
							
							$this->balanceAvailable = $balanceAvailable;
							
							if ($this->marginType == self::ISOLATED)
								$this->extraMargin = ($this->balance - $this->margin);
							
							return true;
							
						}
						
					}
					
				}
				
			}
			
			return false;
			
		}
		
		final function close () {
			
			$this->fees = $this->getCloseFee ();
			
			$fees = ($this->getOpenFee () + $this->fees);
			$fees = 0;
			if ($this->pnl > 0 and $this->pnl <= $fees)
				$this->pnl = $this->roe = $this->roi = 0;
			else
				$this->pnl -= $fees;
			
			if ($this->roi < -100) {
				
				if ($this->marginType == self::ISOLATED) {
					
					if ($this->pnl < -$this->balance) {
						
						$this->pnl = -$this->balance;
						$this->roi = -100;
						
					}
					
				} else {
					
					$percent = new \Percent ($this->roe);
					
					$percent->delim = $this->pnl;
					
					$diff = ($this->balanceAvailable - $this->pnl);
					
					if ($diff < 0) $this->pnl -= $diff;
					
					$this->roe = $percent->valueOf ($this->pnl);
					$this->roi = $this->getROI ();
					
				}
				
			}
			
			$this->balance += $this->pnl;
			$this->walletBalance += $this->pnl;
			
			$this->balanceAvailable += $this->balance;
			
		}
		
		function getMargin () {
			return (($balance * $percent) / 100);
		}
		
		function getFeeRate () {
			
			$value  = $this->feesRate[$this->market][$this->ftype][$this->flevel][($this->feeModel == self::MAKER ? 0 : 1)];
			$value -= (($value * $this->rebate) / 100);
			
			return $value;
			
		}
		
		function getOpenFee () {
			return ($this->getNotional () * $this->getFeeRate ()) / 100;
		}
		
		function getCloseFee () {
			return ($this->getNotional () * $this->getFeeRate ()) / 100;
		}
		
		function getBankruptcyPrice () {
			
			if ($this->isLong ())
				return $this->entryPrice * ($this->leverage - 1) / $this->leverage;
			else
				return $this->entryPrice * ($this->leverage + 1) / $this->leverage;
			
		}
		
		function getLeverage () {
			return $this->position['leverage'];
		}
		
		function getRPRatio ($entryPrice, $takeProfit, $stopLoss) {
			
			$output  = $this->getProfit ($entryPrice, $stopLoss);
			$output /= $this->getProfit ($takeProfit, $entryPrice);
			
			return $output;
			
		}
		
		function getEntryPrice () {
			return $this->position['entry_price'];
		}
		
		function toPoint () {
			return (1 / pow (10, $this->precision));
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
		function changePositionMargin ($base, $quote, $value) {}
		
		function getMarginType () {}
		
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
		
		abstract function getPrice ($base = '', $quote = '');
		
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
			return round ($price, $this->precision);
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
		
		function getAllPrices ($base, $quote, $data, $callback = null) {
			
			$prices = [];
			
			do {
				
				$prices2 = $this->getMarkPrices ($base, $quote, $data);
				
				$prices3 = [];
				
				for ($i = 0; $i < count ($prices2); $i++) {
					
					$price = $prices2[$i];
					
					if (!$prices or $i > 0) {
						
						$prices[] = $price;
						$prices3[] = $price;
						
					}
					
					$data['start_time'] = $price['date'];
					
				}
				
				if ($callback and $prices3) $callback ($prices3);
				
			} while ($prices2 and $prices3 and $data['start_time'] < $data['end_time']);
			
			return $prices;
			
		}
		
		function minQuantity () {
			return $this->minQuantity;
		}
		
		function maxQuantity () {
			return $this->maxQuantity;
		}
		
		function setMode ($base, $quote) {}
		function cancelOrderName ($base, $quote, $name) {}
		
		function openPosition ($base, $quote, $quantity, $data = []) {}
		function closePosition ($base, $quote, $quantity, $data = []) {}
		function decreasePosition ($base, $quote, $quantity, $data = []) {}
		
		function timeframe ($timeframe) { // From cctx
			
			$scales = [];
			
			$scales['s'] = 1;
			$scales['m'] = $scales['s'] * 60;
			$scales['h'] = $scales['m'] * 60;
			$scales['d'] = $scales['h'] * 24;
			$scales['w'] = $scales['d'] * 7;
			$scales['M'] = $scales['w'] * 30;
			$scales['y'] = $scales['M'] * 365;
			
			$amount = substr ($timeframe, 0, -1);
			$unit = substr ($timeframe, -1);
			
			return ($amount * $scales[$unit]);
			
		}
		
		function clean () {
			
			$this->positions = [];
			$this->orders = [];
			$this->balances = [];
			
		}
		
		function getMinMargin () {
			return ($this->markPrice * ($this->minQuantity / $this->leverage));
		}
		
		function getCompoundIncome ($value, $rate) {
			
			$value += ($value * ($rate / 100));
			
			return $value;
			
		}
		
	}