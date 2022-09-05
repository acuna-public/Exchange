<?php
	
	require 'ExchangeException.php';
	
	require 'WebSocketException.php';
	require 'WebSocket.php';
	
	abstract class Exchange {
		
		public
			$cred = [];
		
		public
			$debug = 0,
			$amount = 3,
			$precision = 2,
			$hedgeMode = true,
			$notional = 0,
			$quantity = 0,
			$pnl = 0, $roe,
			$timeOffset,
			$positions = [],
			$position = [],
			$entryPrice = 0, // Только для расчета PNL
			$markPrice = 0,
			$liquid = 0,
			$date = 'd.m.y H:i',
			$queryNum = 0,
			$fees = [],
			$testQuantity = 0,
			$multiplierUp = 0,
			$multiplierDown = 0,
			$proxies = [],
			$minQuantity = 0,
			$maxQuantity = 0;
		
		protected
			$lastDate = 0;
		
		public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
		
		public $flevel = 0, $rebate = 10, $ftype = self::FTYPE_USD;
		
		public $side = self::LONG, $marginType = self::ISOLATED, $leverage = 0, $margin = 0;
		
		const LONG = 'LONG', SHORT = 'SHORT', ISOLATED = 'ISOLATED', CROSS = 'CROSSED', BUY = 'BUY', SELL = 'SELL', MAKER = 'MAKER', TAKER = 'TAKER', BALANCE_AVAILABLE = 'available', BALANCE_TOTAL = 'total', FTYPE_USD = 'USD', FTYPE_COIN = 'COIN';
		
		static $PERPETUAL = 'PERPETUAL', $LEVERAGED = 'LEVERAGED';
		
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
		
		function getInverseLongPNL ($base, $quote, $open, $close) {
			
			if (!$this->notional)
				$this->update ($base, $quote);
			
			return (((($this->notional) * 1) / $open) - (($this->notional) * 1) / $close);
			
		}
		
		function getInverseShortPNL ($base, $quote, $open, $close) {
			
			if (!$this->notional)
				$this->update ($base, $quote);
			
			return (((($this->margin) * 1) / $open) - (($this->margin) * 1) / $close);
			
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
			
			$this->positions = $this->getFuturesPositions ($base, $quote);
			
			if (isset ($this->positions[$this->side]))
				$this->position = $this->positions[$this->side];
			else
				$this->position = $this->positions['BOTH'];
			
		}
		
		function futuresInit ($base, $quote) {
			
			if ($this->markPrice == 0) // NULLED
				$this->markPrice = $this->getMarkPrice ();
			
			if ($this->markPrice == 0)
				$this->markPrice = $this->futuresTicker ($base, $quote)['mark_price'];
			
			if ($this->leverage == 0) // NULLED
				$this->leverage = $this->getLeverage ();
			
			//$this->leverage = 10;
			
			$this->liquid = (100 / $this->leverage);
			
			$this->notional = ($this->margin * $this->leverage);
			
			if ($this->quantity == 0) // NULLED
				$this->quantity = $this->getQuantity ($this->markPrice);
			
		}
		
		function futuresUpdate () {
			
			if ($this->entryPrice == 0) // NULLED
				$this->entryPrice = $this->getEntryPrice ();
			
			if ($this->entryPrice == 0)
				$this->entryPrice = $this->markPrice;
			
			$this->pnl = $this->getPNL ($this->entryPrice, $this->markPrice, $this->quantity);
			
			$this->roe = $this->getROE ($this->pnl);
			
		}
		
		function getMargin ($balance, $percent) {
			return (($balance * $percent) / 100);
		}
		
		function getFeeRate ($type) {
			
			$fee  = $this->fees[$this->ftype][$this->flevel][($type == self::MAKER ? 0 : 1)];
			$fee -= $this->getMargin ($fee, $this->rebate);
			
			return $fee;
			
		}
		
		function getFuturesFees ($price, $type) {
			return $this->getMargin (($price * $this->quantity), $this->getFeeRate ($type));
		}
		
		function getQuantity ($price) {
			return ($this->notional / $price);
		}
		
		function getLeverage () {
			return $this->position['leverage'];
		}
		
		function getFuturesPositionAmount () {
			return $this->position['positionAmt'];
		}
		
		function getPNL ($entry, $exit, $quantity) {
			return ($entry > 0 ? ($this->getProfit ($entry, $exit) * $quantity) : 0);
		}
		
		function getROE ($pnl) {
			return ($this->margin > 0 ? (($pnl * 100) / $this->margin) : 0);
		}
		
		function getProfit ($entry, $exit) {
			
			if ($this->isLong ())
				return ($exit - $entry);
			else
				return ($entry - $exit);
			
		}
		
		function getRPRatio ($entryPrice, $stopLoss, $takeProfit) {
			
			$output = $this->getProfit ($entryPrice, $stopLoss);
			$output /= $this->getProfit ($takeProfit, $entryPrice);
			
			return $output;
			
		}
		
		function getMarkPrice () {
			return 0;
		}
		
		function getEntryPrice () {
			return $this->position['entry_price'];
		}
		
		function pair ($base, $quote) {
			return $base.$quote;
		}
		
		abstract function createOrder ($type, $base, $quote, $amount, $price);
		abstract function getOrders ($base, $quote);
		abstract function getOrderInfo ($id);
		
		function createMarketOrder ($type, $base, $quote, $amount) {
			return $this->createOrder ($type, $base, $quote, $amount, 0);
		}
		
		function longShortRatio ($base, $quote, $period) {
			throw new \Exception ('Long/Short Ratio not implemented');
		}
		
		function setFuturesLeverage ($base, $quote, $leverage) {}
		function setFuturesMarginType ($base, $quote, $type, $longLeverage = 10, $shortLeverage = 10) {}
		
		function getFuturesPositions ($base, $quote) {}
		
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
		abstract function getSymbolsInfo ();
		abstract function isOrderTakeProfit ($order);
		abstract function orderCreateDate ($order);
		
		function getFuturesSymbolsInfo () {
			return $this->getSymbolsInfo ();
		}
		
		function getFuturesSymbols ($quote = '') {
			return $this->getSymbols ($quote);
		}
		
		function getFuturesOpenOrders ($base, $quote) {}
		function openFuturesMarketPosition ($base, $quote, $side, $order) {}
		function createFuturesMarketTakeProfitOrder ($orders) {}
		function createFuturesMarketStopOrder ($orders) {}
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
				
				if ($price['close'] > $max) {
					$max = $price['close'];
					
					//debug ([1, date ('d.m H:i:s', $price['date']), $max]);
					
				} elseif ($price['close'] < $min) {
					$min = $price['close'];
					
					//debug ([date ('d.m H:i:s', $price['date']), $min]);
					
				}
				
			}
			
			//return ($max - $min);
			return ((($max - $min) * 100) / $max);
			
		}
		
		function amount ($amount) {
			
			if ($this->amount > 0)
				$amount = mash_number_format ($amount, $this->amount, '.', '');
			else
				$amount = round ($amount);
			
			return $amount;
			
		}
		
		function price ($amount) {
			return mash_number_format ($amount, $this->precision, '.', '');
		}
		
		protected function date ($date) {
			return date ($this->date, $date);
		}
		
		function getPrice ($price) {
			
			if ($this->isLong ())
				return $this->markPrice * $this->multiplierUp;
			else
				return $this->markPrice * $this->multiplierDown;
			
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
		
		function quantity () {
			return $this->amount ($this->quantity);
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
				
				if ($callback) $callback ($prices3);
				
			} while ($prices2 and $data['start_time'] < $data['end_time']);
			
			return $prices;
			
		}
		
		function minQuantity () {
			return $this->minQuantity;
		}
		
		function maxQuantity () {
			return $this->maxQuantity;
		}
		
		function editFuturesOrder ($base, $quote, $id, $data) {}
		function editFuturesOrderName ($base, $quote, $name, $data) {}
		function cancelFuturesOrderName ($base, $quote, $name) {}
		
		protected function timeframe ($timeframe) {
			
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
		
	}