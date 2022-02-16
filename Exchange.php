<?php
	
	require 'ExchangeException.php';
	
	require 'WebSocketException.php';
	require 'WebSocket.php';
	
	abstract class Exchange {
		
		public
			$qtyPercent = 99,
			$debug = 0,
			$entryPrice = 0, // Только для расчета PNL
			$market = true,
			$amount = 3,
			$precision = 2,
			$hedgeMode = true;
		
		public static $date = 'd.m.y H:i';
		
		public
			$futuresBalance = -1,
			$notional = 0,
			$quantity,
			$pnl = 0, $roe, $pnl2 = 0,
			$change,
			$positions = [],
			$position = [],
			$markPrice = 0,
			$cred = [],
			$liquid = 0,
			$queryNum = 0,
			$testQuantity = 0,
			$multiplierUp = 0,
			$multiplierDown = 0,
			$maxNotional = 0,
			$proxies = [];
		
		public $flevel = 0, $rebate = 10, $ftype = 'USDT';
		
		public $side = self::LONG, $marginType = self::ISOLATED, $leverage = 0, $margin = 0;
		
		const LONG = 'LONG', SHORT = 'SHORT', ISOLATED = 'ISOLATED', CROSS = 'CROSS', BUY = 'BUY', SELL = 'SELL', MAKER = 'MAKER', TAKER = 'TAKER';
		
		static $PERPETUAL = 'PERPETUAL', $LEVERAGED = 'LEVERAGED';
		
		protected $balances = [], $futuresBalances = [];
		
		function __construct ($cred = []) {
			$this->setCredentials ($cred);
		}
		
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
		
		abstract function getCharts (array $data);
		abstract function getBalances ();
		
		function getFuturesBalances () {
			return $this->getBalances ();
		}
		
		function getBalance ($cur) {
			return $this->getBalances ()[$cur];
		}
		
		function getFuturesBalance ($cur) {
			return $this->getFuturesBalances ()[$cur];
		}
		
		function getPosition ($base, $quote) {
			
			$this->positions = $this->getFuturesPositions ($base, $quote);
			
			if (isset ($this->positions[$this->side]))
				$this->position = $this->positions[$this->side];
			else
				$this->position = $this->positions['BOTH'];
			
		}
		
		function futuresInit ($base, $quote) {
			
			if ($this->futuresBalance == 0) // NULLED
				$this->futuresBalance = $this->getFuturesBalance ($quote);
			
			if ($this->markPrice == 0) // NULLED
				$this->markPrice = $this->getMarkPrice ();
			
			if ($this->markPrice == 0)
				$this->markPrice = $this->getFuturesPrices ($base, $quote)['index_price'];
			
			if ($this->qtyPercent <= 0) $this->qtyPercent = 100;
			
			if ($this->leverage == 0) // NULLED
				$this->leverage = $this->getLeverage ();
			
			$this->liquid = (100 / $this->leverage);
			
			$this->margin = $this->getMargin ($this->futuresBalance);
			
			if ($this->maxNotional > 0 and $this->margin > $this->maxNotional)
				$this->margin = $this->maxNotional;
			
			$this->notional = ($this->margin * $this->leverage);
			
			if ($this->quantity == 0) // NULLED
				$this->quantity = $this->getQuantity ($this->markPrice);
			
		}
		
		function futuresUpdate () {
			
			if ($this->entryPrice == 0) // NULLED
				$this->entryPrice = $this->getEntryPrice ();
			
			$this->pnl = $this->getPNL ($this->entryPrice, $this->markPrice, $this->quantity);
			$this->roe = $this->getROE ($this->pnl);
			
			$this->change = $this->getLevel ($this->roe);
			
		}
		
		function getLevel ($roe) {
			return ($roe / 100);
		}
		
		function getMargin ($balance) {
			return (($balance * $this->qtyPercent) / 100);
		}
		
		function getFee ($type) {
			
			$fee = $this->fees[$this->ftype][$this->flevel][($type == self::MAKER ? 0 : 1)];
			$fee -= (($fee * $this->rebate) / 100);
			
			return $fee;
			
		}
		
		function openFee ($entryPrice, $quantity, $type) {
			
			$fee = ($entryPrice * $quantity);
			return (($fee * $this->getFee ($type)) / 100);
			
		}
		
		function closeFee ($exitPrice, $quantity, $type) {
			
			$fee = ($exitPrice * $quantity);
			return (($fee * $this->getFee ($type)) / 100);
			
		}
		
		function getFuturesFee () {
			
			$fee	= $this->openFee ($this->entryPrice, $this->quantity, $this->market ? self::TAKER : self::MAKER);
			$fee += $this->closeFee ($this->markPrice, $this->quantity, $this->market ? self::TAKER : self::MAKER);
			
			return $fee;
			
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
			return $this->margin != 0 ? (($pnl * 100) / $this->margin) : 0;
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
			return $this->position['markPrice'];
		}
		
		function getEntryPrice () {
			return $this->position['entryPrice'];
		}
		
		function pair ($base, $quote) {
			return $base.$quote;
		}
		
		abstract function createOrder ($type, $base, $quote, $amount, $price);
		abstract function getOrders ($base, $quote);
		abstract function getOrderInfo ($id);
		abstract function getHolds ($id);
		
		function createMarketOrder ($type, $base, $quote, $amount) {
			return $this->createOrder ($type, $base, $quote, $amount, 0);
		}
		
		function longShortRatio ($base, $quote, $period) {
			throw new \Exception ('Long/Short Ratio not implemented');
		}
		
		function setFuturesLeverage ($base, $quote, $leverage) {}
		function setFuturesMarginType ($base, $quote, $type) {}
		
		function getFuturesPositions ($base, $quote) {}
		
		function isOrderStopLoss ($order) {
			return false;
		}
		
		abstract function orderId ($order);
		
		function orderName ($order) {
			return $this->orderId ($order);
		}
		
		abstract function ticker ($base = '', $quote = '');
		abstract function getTrades ($base, $quote);
		abstract function getExchangeInfo ();
		abstract function isOrderTakeProfit ($order);
		abstract function orderCreateDate ($order);
		
		function getFuturesExchangeInfo () {}
		function getFuturesOpenOrders ($base, $quote) {}
		function createFuturesOrder ($orders) {}
		function openFuturesMarketPosition ($order) {}
		function createFuturesMarketTakeProfitOrder ($orders) {}
		function createFuturesMarketStopOrder ($orders) {}
		function createFuturesTrailingStopOrder ($order) {}
		function cancelFuturesOpenOrders ($base, $quote) {}
		
		function cancelFuturesOrders ($base, $quote, $ids) {}
		
		function futuresOrderCreateDate ($order) {
			return $this->orderCreateDate ($order);
		}
		
		function cancelFuturesOrdersNames ($base, $quote, $ids) {
			return $this->cancelFuturesOrders ($base, $quote, $ids);
		}
		
		function getFuturesPrices ($base, $quote) {
			return [];
		}
		
		function getAccountStatus () {
			return '';
		}
		
		function getFuturesCurrencyPairs ($quote = '') {}
		function getFuturesBrackets ($base = '', $quote = '') {}
		function futuresTicker ($base = '', $quote = '') {}
		
		function getVolatility ($base, $quote, $interval = '1h') {
			
			$date = new \Date ();
			
			$charts = $this->getCharts (['base' => $base, 'quote' => $quote, 'interval' => $interval, 'start_time' => $date->add (-\Date::DAY * 1)->getTime ()]);
			
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
			return mash_number_format ($amount, $this->amount, '.', '');
		}
		
		function price ($amount) {
			return mash_number_format ($amount, $this->precision, '.', '');
		}
		
		static function date ($date) {
			return date (self::$date, $date);
		}
		
		abstract function getCurrencyPairs ($type, $quote = '');
		
		function getPrice ($price) {
			
			if ($this->isLong ())
				return $this->markPrice * $this->multiplierUp;
			else
				return $this->markPrice * $this->multiplierDown;
			
		}
		
		abstract function setFuturesHedgeMode (bool $hedge);
		abstract function getFuturesHedgeMode ();
		
	}