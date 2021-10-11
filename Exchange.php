<?php
  
  require 'ExchangeException.php';
  
  require 'WebSocketException.php';
  require 'WebSocket.php';
  
  abstract class Exchange {
    
    public
      $qtyPercent = 100,
      $debug = 0,
      $entryPrice = 0, // Только для расчета PNL
      $market = true,
      $amount = 3,
      $precision = 2,
      $date = 'd.m.y H:i';
    
    public
      $futuresBalance = -1,
      $notional = 0,
      $quantity,
      $futuresFees,
      $pnl = 0, $roe,
      $change,
      $positions = [],
      $position = [],
      $markPrice = 0,
      $cred = [],
      $liquid = 0,
      $queryNum = 0,
      $testQuantity = 0,
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
    
    function getInverseLongPNL ($cur1, $cur2, $open, $close) {
      
      if (!$this->notional)
        $this->update ($cur1, $cur2);
      
      return (((($this->notional) * 1) / $open) - (($this->notional) * 1) / $close);
      
    }
    
    function getInverseShortPNL ($cur1, $cur2, $open, $close) {
      
      if (!$this->notional)
        $this->update ($cur1, $cur2);
      
      return (((($this->margin) * 1) / $open) - (($this->margin) * 1) / $close);
      
    }
    
    abstract function getCharts (array $data);
    abstract function getBalances ();
    
    function getFuturesBalances () {
      return [];
    }
    
    function getBalance ($cur) {
      return $this->getBalances ()[$cur];
    }
    
    function getFuturesBalance ($cur) {
      return $this->getFuturesBalances ()[$cur];
    }
    
    function futuresUpdate ($cur1, $cur2) {
      
      $this->position = $this->positions[0];
      
      $this->markPrice = $this->getMarkPrice ();
      
      if ($this->markPrice == 0)
        $this->markPrice = $this->getFuturesPrices ($cur1, $cur2)['index_price'];
      
      if ($this->qtyPercent <= 0)
        $this->qtyPercent = 100;
      
      $this->leverage = $this->getLeverage ();
      
      $this->margin = $this->getMargin ($this->futuresBalance);
      
      $this->liquid = (100 / $this->leverage);
      $this->notional = ($this->margin * $this->leverage);
      
      if ($this->entryPrice == 0)
        $this->entryPrice = $this->getEntryPrice ();
      
      if ($this->entryPrice == 0)
        $this->entryPrice = $this->markPrice;
      
      if ($this->quantity <= 0)
        $this->quantity = $this->getQuantity ($this->entryPrice);
      
      $this->pnl += $this->getPNL ($this->entryPrice, $this->markPrice, $this->quantity);
      //debug ([$this->getPNL ($this->entryPrice, $this->markPrice, $this->quantity), $this->pnl]);
      $this->roe = $this->getROE ($this->pnl);
      $this->change = $this->getLevel ($this->roe);
      
      $this->futuresFees = $this->getFuturesFee ();
      
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
      
      $fee  = $this->openFee ($this->entryPrice, $this->quantity, $this->market ? self::TAKER : self::MAKER);
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
      return ($this->margin != 0 ? (($pnl * 100) / $this->margin) : 0);
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
    
    function pair ($cur1, $cur2) {
      return $cur1.'-'.$cur2;
    }
    
    abstract function createOrder ($type, $cur1, $cur2, $amount, $price);
    
    function createMarketOrder ($type, $cur1, $cur2, $amount) {
      return $this->createOrder ($type, $cur1, $cur2, $amount, 0);
    }
    
    abstract function getOrders ($cur1, $cur2);
    abstract function getOrderInfo ($id);
    abstract function getHolds ($id);
    
    final function isOrderDone ($id) {
      
      $info = $this->getOrderInfo ($id);
      return ($info && $info['done']);
      
    }
    
    function longShortRatio ($cur1, $cur2, $period) {
      throw new \Exception ('Long/Short Ratio not implemented');
    }
    
    function setFuturesLeverage ($cur1, $cur2, $leverage) {}
    function setFuturesMarginType ($cur1, $cur2, $type) {}
    
    function getFuturesPositions ($cur1, $cur2) {}
    
    function isOrderStopLoss ($order) {
      return false;
    }
    
    abstract function orderId ($order);
    
    function orderName ($order) {
      return $this->orderId ($order);
    }
    
    function getTrades ($cur1, $cur2) {}
    function getExchangeInfo () {}
    function getFuturesExchangeInfo () {}
    function getFuturesOpenOrders ($cur1, $cur2) {}
    function createFuturesOrder ($orders) {}
    function openFuturesMarketPosition ($order) {}
    function createFuturesMarketTakeProfitOrder ($orders) {}
    function createFuturesMarketStopOrder ($orders) {}
    function createFuturesTrailingStopOrder ($order) {}
    function cancelFuturesOpenOrders ($cur1, $cur2) {}
    function isOrderTakeProfit ($order) {}
    function cancelFuturesOrders ($cur1, $cur2, $ids) {}
    function orderCreateDate ($order) {}
    
    function cancelFuturesOrdersNames ($cur1, $cur2, $ids) {
      return $this->cancelFuturesOrders ($cur1, $cur2, $ids);
    }
    
    function getFuturesPrices ($cur1, $cur2) {
      return [];
    }
    
    function getAccountStatus () {
      return '';
    }
    
    function getFuturesCurrencyPairs ($cur2 = '') {}
    function getBrackets ($cur1 = '', $cur2 = '') {}
    function ticker ($cur1 = '', $cur2 = '') {}
    function futuresTicker ($cur1 = '', $cur2 = '') {}
    
    function getVolatility ($cur1, $cur2, $interval = '1h') {
      
      $date = new \Date ();
      
      $charts = $this->getCharts (['cur1' => $cur1, 'cur2' => $cur2, 'interval' => $interval, 'start_time' => $date->add (-\Date::DAY * 1)->getTime ()]);
      
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
    
    function date ($date) {
      return date ($this->date, $date);
    }
    
    function getCurrencyPairs ($type, $cur2 = '') {}
    
  }