<?php
	
	require 'ExchangeException.php';
	
	//require 'WebSocketException.php';
	//require 'WebSocket.php';
	
	abstract class Exchange {
		
		public
			$debug = 0,
			$amount = 3,
			$precision = 2,
			$date = 'd.m.y H:i',
			$entryPrice = 0,
			$markPrice = 0,
			$minQuantity = 0,
			$maxQuantity = 0;
		
		public
			$hedgeMode = true,
			$initialMarginRate = 0,
			$maintenanceMarginRate = 0;
		
		public
			$margin = 0,
			$leverage = 0,
			$liquid = 0,
			$notional = 0,
			$quantity = 0,
			$pnl = 0, $roe,
			$timeOffset,
			$queryNum = 0;
		
		public
			$cred = [],
			$fees = 0,
			$feesRate = [],
			$proxies = [],
			$position = [],
			$ticker = [],
			$positions = [],
			$orders = [];
		
		protected
			$lastDate = 0;
		
		public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
		
		public $flevel = 0, $rebate = 10, $ftype = self::FTYPE_USD;
		
		public $side = self::LONG, $marginType = self::CROSS;
		
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
		
		function setCredentials ($cred) {
			$this->cred = $cred;
		}
		
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
			return $margin - $this->getMaintenanceMargin ($price);
		}
		
		function getLiquidationPrice ($margin = 0) {
			
			$price = $this->entryPrice;
			
			if ($this->marginType == self::CROSS) {
				
				if ($this->isLong ())
					$price -= ($this->getSustainableLoss ($margin, $price) / $this->quantity);
				else
					$price += ($this->getSustainableLoss ($margin, $price) / $this->quantity);
				
			} else {
				
				$price *= $this->quantity;
				
				if ($this->isLong ())
					$price *= ((1 - $this->getInitialMargin () + ($this->maintenanceMarginRate / 100)) - $margin);
				else
					$price *= ((1 + $this->getInitialMargin () - ($this->maintenanceMarginRate / 100)) + $margin);
				
				$price /= $this->quantity;
				
			}
			
			return $this->price ($price);
			
		}
		
		function getMarginQuantity ($margin, $price) {
			return ($this->getSustainableLoss ($margin, $this->entryPrice) / $price);
		}
		
		function getInitialMargin () {
			return (1 / $this->leverage);
		}
		
		function getAdditionalMargin ($stopPrice) { // TODO
			
			if ($this->isLong ())
				return ($this->getLiquidationPrice () * $this->quantity) - ($stopPrice * $this->quantity);
			else
				return ($stopPrice * $this->quantity) - ($this->getLiquidationPrice () * $this->quantity);
			
		}
		
		abstract function getCharts ($base, $quote, array $data);
		abstract function getBalance ($type, $quote = '');
		
		function getFuturesCharts ($base, $quote, array $data) {
			return $this->getCharts ($base, $quote, $data);
		}
		
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
		
		function futuresInit ($base, $quote) {
			
			if ($this->markPrice == 0) // NULL
				$this->markPrice = $this->getMarkPrice ($base, $quote);
			
			if ($this->leverage == 0) // NULL
				$this->leverage = $this->getLeverage ();
			
			//$this->leverage = 10;
			
			$this->liquid = (100 / $this->leverage);
			
			$this->notional = ($this->margin * $this->leverage);
			
			if ($this->quantity == 0) // NULL
				$this->quantity = $this->getQuantity ($this->markPrice);
			
		}
		
		function futuresUpdate () {
			
			if ($this->entryPrice == 0)
				$this->entryPrice = $this->markPrice;
			
			$this->pnl = $this->getPNL ();
			
			$this->fees  = $this->getFuturesTakerFees ($this->entryPrice);
			$this->fees += $this->getFuturesTakerFees ($this->markPrice);
			
			$this->pnl -= $this->fees;
			
			$this->roe = $this->getROE ();
			
			$this->margin += $this->pnl;
			
		}
		
		function getMargin ($balance, $percent) {
			return (($balance * $percent) / 100);
		}
		
		function getFeeRate ($type) {
			
			$value  = $this->feesRate[$this->ftype][$this->flevel][($type == self::MAKER ? 0 : 1)];
			$value -= $this->getMargin ($value, $this->rebate);
			
			return $value;
			
		}
		
		function getFuturesFees ($price, $type) {
			return $this->getMargin (($price * $this->quantity), $this->getFeeRate ($type));
		}
		
		function getFuturesMakerFees ($price) {
			return $this->getFuturesFees ($price, self::MAKER);
		}
		
		function getFuturesTakerFees ($price) {
			return $this->getFuturesFees ($price, self::TAKER);
		}
		
		function getQuantity ($price) {
			return $this->amount ($this->notional / $price);
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
			
			$pnl = $this->pnl;
			
			$pnl *= 100;
			$pnl /= (($this->quantity * $this->entryPrice) / $this->leverage);
			
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
		
		function pair ($base, $quote) {
			return $base.$quote;
		}
		
		function getMarkPrice ($base, $quote) {
			
			if (!$this->ticker) $this->ticker = $this->ticker ();
			
			return $this->ticker[$this->pair ($base, $quote)]['mark_price'];
			
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
		
		function setFuturesLeverage ($base, $quote, $leverage) {}
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
		
		abstract function ticker ($base = '', $quote = '');
		
		function futuresTicker ($base = '', $quote = '') {
			return $this->ticker ($base, $quote);
		}
		
		function getVolatility ($base, $quote, $interval = '1h') {
			
			$date = new \Date ();
			
			$charts = $this->getCharts ($base, $quote, ['interval' => $interval, 'start_time' => $date->add (-\Date::DAY * 1)->getTime ()]);
			
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
		
		function price ($amount) {
			return round ($amount, $this->precision);
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
		
		function setPairsFuturesHedgeMode () {
			
			try {
				$this->setFuturesHedgeMode ($this->hedgeMode);
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
				
				$prices2 = $this->getCharts ($base, $quote, $data);
				
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
		
		function setFuturesMode ($base, $quote) {}
		function cancelFuturesOrderName ($base, $quote, $name) {}
		function closeFuturesMarketPosition ($base, $quote, $data) {}
		
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
			
			$this->ticker = [];
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