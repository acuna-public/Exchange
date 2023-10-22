<?php
	
	require 'ExchangeException.php';
	
	//require 'WebSocketException.php';
	//require 'WebSocket.php';
	
	abstract class Exchange {
		
		public
			$hedgeMode = false,
			$debug = 0;
		
		public
			$amount = 3,
			$basePrecision = 2,
			$quotePrecision = 2,
			$date = 'd.m.y H:i';
		
		public
			$margin = 0,
			$openBalance = 0,
			$closeBalance = 0,
			$leverage = 0,
			$takeProfit = 0,
			$stopLoss = 0,
			$entryPrice = 0,
			$markPrice = 0,
			$timeOffset = 0,
			$minQuantity = 0,
			$maxQuantity = 0,
			$marginPercent = 100,
			$balancePercent = 100,
			$balanceAvailable = 0,
			$liquid = 0,
			$quantity = 0,
			$initialMarginRate = 0,
			$maintenanceMarginRate = 0;
		
		public
			$cred = [],
			$openFee = 0,
			$closeFee = 0,
			$pnl = 0, $roe = 0,
			$fees = 0,
			$queryNum = 0,
			$feesRate = [],
			$proxies = [],
			$position = [],
			$positions = [],
			$orders = [];
		
		public
			$recvWindow = 60000; // 1 minute
		
		protected
			$lastDate = 0;
		
		public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
		
		public $flevel = 0, $rebate = 10, $ftype = self::FTYPE_USD, $feeModel = self::MAKER;
		
		public $side = self::LONG, $marginType = self::CROSS, $market = self::FUTURES;
		
		const FUTURES = 'FUTURES';
		
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
		
		function getMaintenanceMargin ($price) {
			return ($this->quantity * $price * ($this->maintenanceMarginRate / 100));
		}
		
		function getSustainableLoss ($margin, $price) {
			return ($margin - $this->getMaintenanceMargin ($price));
		}
		
		function getLiquidationPrice ($quote, $openBalance, $extraMargin = 0) {
			
			$price = $this->entryPrice;
			
			if ($this->marginType == self::CROSS) {
				
				if ($this->isLong ())
					$price -= ($this->getSustainableLoss ($openBalance, $price) / $this->quantity);
				else
					$price += ($this->getSustainableLoss ($openBalance, $price) / $this->quantity);
				
			} else {
				
				if ($quote == 'USDT') {
					
					if ($this->isLong ())
						$price *= (1 - $this->getInitialMargin () + ($this->maintenanceMarginRate / 100)) - ($extraMargin / $this->quantity);
					else
						$price *= (1 + $this->getInitialMargin () - ($this->maintenanceMarginRate / 100)) + ($extraMargin / $this->quantity);
					
				} elseif ($quote == 'USDC') {
					
					if ($this->isLong ())
						$price += (($this->getInitialMargin () + $extraMargin - ($this->maintenanceMarginRate / 100)) / $this->quantity);
					else
						$price -= (($this->getInitialMargin () + $extraMargin - ($this->maintenanceMarginRate / 100)) / $this->quantity);
					
				}
				
			}
			
			$price = $this->basePrice ($price);
			
			return ($price > 0 ? $price : 0);
			
		}
		
		function getInitialMargin () {
			return (1 / $this->leverage);
		}
		
		function getStopLoss ($quote, $entryPrice) {
			
			$price = $this->getLiquidationPrice ($entryPrice);
			
			if ($this->stopLoss <= 0) $this->stopLoss = $this->liquid;
			
			$percent = (($entryPrice * $this->stopLoss) / 100);
			
			if ($this->isLong ()) {
				
				$stopPrice = $this->basePrice ($entryPrice - $percent);
				if ($stopPrice > $price) $price = $stopPrice;
				
			} else {
				
				$stopPrice = $this->basePrice ($entryPrice + $percent);
				if ($stopPrice < $price) $price = $stopPrice;
				
			}
			
			return $price;
			
		}
		
		function getTakeProfit ($entryPrice) {
			
			if ($this->takeProfit < 0) $this->takeProfit = $this->liquid;
			
			if ($this->takeProfit > 0) {
				
				$percent = (($entryPrice * $this->takeProfit) / 100);
				
				if ($this->isLong ())
					return $this->basePrice ($entryPrice + $percent);
				else
					return $this->basePrice ($entryPrice - $percent);
				
			} else return 0;
			
		}
		
		function liquidPricePercent ($entryPrice, $liquidPrice) {
			
			$price = ($entryPrice - $liquidPrice);
			return (($price * 100) / $entryPrice);
			
		}
		
		function getMarginQuantity ($margin, $price) {
			return ($this->getSustainableLoss ($margin, $this->entryPrice) / $price);
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
		
		abstract function getBalance ($type, $quote = '');
		
		function getFuturesBalance ($type, $quote = '') {
			return $this->getBalance ($type, $quote);
		}
		
		function getPosition ($base, $quote) {
			
			if (!$this->positions) $this->positions = $this->getFuturesPositions ();
			
			if (isset ($this->positions[$this->pair ($base, $quote)])) {
				
				if ($this->hedgeMode)
					$this->position = $this->positions[$this->pair ($base, $quote)][$this->side];
				else
					$this->position = $this->positions[$this->pair ($base, $quote)];
				
			}
			
		}
		
		function optPosition ($base, $quote) {
			
			$this->getPosition ($base, $quote);
			
			return isset ($this->positions[$this->pair ($base, $quote)]);
			
		}
		
		function start () {
			
			if ($this->leverage == 0) // NULL
				$this->leverage = $this->getLeverage ();
			
			$this->liquid = (100 / $this->leverage);
			
		}
		
		function update () {
			
			$this->pnl = $this->getPNL ();
			$this->roe = $this->getROE ();
			
		}
		
		function open () {
			
			$this->fees = 0;
			
			if ($this->openBalance > 0) {
				
				if ($this->balancePercent <= 0 or $this->balancePercent > 100)
					$this->balancePercent = 100;
				
				$percent = new \Percent ($this->openBalance);
				$this->openBalance = $percent->valueOf ($this->balancePercent);
				
				if ($this->marginPercent <= 0 or $this->marginPercent > 100)
					$this->marginPercent = 100;
				
				$percent = new \Percent ($this->openBalance);
				$this->margin = $percent->valueOf ($this->marginPercent);
				
				$balanceAvailable = $this->balanceAvailable;
				$this->balanceAvailable -= $this->openBalance;
				
				$this->entryPrice = $this->markPrice;
				$this->quantity = $quantity = $this->getQuantity ();
				
				if ($this->quantity > 0) {
					
					$min = $this->minQuantity ();
					$max = $this->maxQuantity ();
					
					if ($min > 0 and $this->quantity < $min)
						$this->quantity = $min;
					elseif ($max > 0 and $this->quantity > $max)
						$this->quantity = $max;
					
				}
				
				if ($quantity > 0 and $this->margin > 0) {
					
					$margin = $this->margin;
					
					if ($this->quantity != $quantity) {
						
						$percent = new \Percent ($this->quantity);
						
						$percent->delim = $quantity;
						
						$this->margin = $percent->valueOf ($this->margin);
						
						$margin -= $this->margin;
						
					}
					
					return ($margin > 0 and $margin < $balanceAvailable);
					
				}
				
			}
			
			return false;
			
		}
		
		function close () {
			
			$this->fees = ($this->getFees ($this->entryPrice) + $this->getFees ($this->markPrice));
			
			if ($this->pnl > $this->fees)
				$this->pnl -= $this->fees;
			
			$this->margin += $this->pnl;
			$this->closeBalance += $this->pnl;
			
			$this->balanceAvailable += $this->closeBalance;
			
		}
		
		function getMargin ($balance, $percent) {
			return (($balance * $percent) / 100);
		}
		
		function getFeeRate () {
			
			$value  = $this->feesRate[$this->market][$this->ftype][$this->flevel][($this->feeModel == self::MAKER ? 0 : 1)];
			$value -= $this->getMargin ($value, $this->rebate);
			
			return $value;
			
		}
		
		function getFees ($price) {
			return $this->getMargin (($price * $this->quantity), $this->getFeeRate ());
		}
		
		function getQuantity () {
			
			if ($this->entryPrice > 0) {
				
				$notional = ($this->margin * $this->leverage);
				
				return $this->amount ($notional / $this->entryPrice);
				
			} else throw new \ExchangeException ('Price must be higher than 0');
			
		}
		
		function getLeverage () {
			return $this->position['leverage'];
		}
		
		function getFuturesPositionAmount () {
			return $this->position['positionAmt'];
		}
		
		function getPNL () {
			return ($this->getProfit ($this->entryPrice, $this->markPrice) * $this->quantity);
		}
		
		function getROE () {
			
			$pnl = ($this->pnl * 100);
			
			$quantity = (($this->quantity * $this->entryPrice) / $this->leverage);
			
			if ($quantity > 0)
				$pnl /= $quantity;
			else
				throw new \ExchangeException ('Position quantity must be greater than zero');
			
			return $pnl;
			
		}
		
		function getProfit ($entry, $exit) {
			
			if ($this->isLong ())
				return ($exit - $entry);
			else
				return ($entry - $exit);
			
		}
		
		function getRPRatio ($entryPrice, $stopLoss, $takeProfit) {
			
			$output  = $this->getProfit ($entryPrice, $stopLoss);
			$output /= $this->getProfit ($takeProfit, $entryPrice);
			
			return $output;
			
		}
		
		function getEntryPrice () {
			return $this->position['entry_price'];
		}
		
		function toPoint () {
			return (1 / pow (10, $this->basePrecision));
		}
		
		function pair ($base, $quote) {
			return $base.$quote;
		}
		
		function createOrder ($type, $base, $quote, $price) {} // TODO
		
		abstract function getOrders ($base, $quote);
		abstract function getOrderInfo ($id);
		
		function editFuturesPosition ($base, $quote, $data) {}
		
		function createMarketOrder ($type, $base, $quote) {
			return $this->createOrder ($type, $base, $quote, 0);
		}
		
		function longShortRatio ($base, $quote, $period) {
			throw new \ExchangeException ('Long/Short Ratio not implemented');
		}
		
		function setLeverage ($base, $quote, $leverage) {}
		function setFuturesMarginType ($base, $quote, $longLeverage = 10, $shortLeverage = 10) {}
		
		function getFuturesPositions ($base = '', $quote = '') {}
		
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
		
		function getFuturesSymbols ($quote = '') {
			return $this->getSymbols ($quote);
		}
		
		function getFuturesOpenOrders ($base, $quote) {}
		function getFuturesFilledOrders ($base, $quote) {}
		function openFuturesMarketPosition ($base, $quote, $order) {}
		function createFuturesMarketTakeProfitOrder ($orders) {}
		function createFuturesMarketStopOrder ($orders) {} // TODO
		function createFuturesTrailingStopOrder ($order) {}
		function cancelFuturesOpenOrders ($base, $quote) {}
		
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
		
		function basePrice ($amount) {
			return round ($amount, $this->basePrecision);
		}
		
		function quotePrice ($amount) {
			return round ($amount, $this->quotePrecision);
		}
		
		function date ($date) {
			return date ($this->date, $date);
		}
		
		abstract function setFuturesHedgeMode (bool $hedge, $base = '', $quote = '');
		
		function getFuturesHedgeMode () {
			return false;
		}
		
		function getUFR ($execOrders, $totalOrders) {
			return (1 - ($execOrders / $totalOrders));
		}
		
		abstract function orderData (array $order);
		
		function getAnnouncements () {
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
		
		protected function quantity () {
			return $this->quantity;
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
		function closeMarketPosition ($side, $base, $quote, $data) {}
		
		protected function timeframe ($timeframe) { // From cctx
			
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
			
		}
		
		function getMinMargin () {
			return ($this->markPrice * ($this->minQuantity / $this->leverage));
		}
		
		function getCompoundIncome ($value, $rate) {
			
			$value += ($value * ($rate / 100));
			
			return $value;
			
		}
		
	}