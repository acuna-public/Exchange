<?php
  
  require 'ExchangeException.php';
  require 'NotEnoughFundsException.php';
  
  require 'Calculator.php';
  
  abstract class Exchange {
    
    public
      $timeOffset = 0,
      $recvWindow = 60000, // 1 minute
      $dateFormat = 'd.m.y H:i';
    
    public
      $debug = 0,
      $amount = 0,
      $basePrecision = 2,
      $quotePrecision = 2,
      $leveragePrecision = 2;
    
    public
      $marginPercent = 100,
      $balancePercent = 100,
      $pruneValue = 0.1,
      $leverage = 0,
      $quantity = 0,
      $balance = 0,
      $totalPNL = 0,
      $markDate = 0,
      $upnl = 0,
      $fees = 0,
      $openBalance = 0,
      $walletBalance = 0,
      $fixedBalance = 0,
      $maxBalance = 0,
      $stopLoss = 0,
      $quantitys = [],
      $entryPrices = [],
      $minQuantity = 0,
      $maxQuantity = 0,
      $minValue = [self::SPOT => 0, self::FUTURES => 0],
      $balanceAvailable = 0,
      $initialMarginRate = 0,
      $maintenanceMarginRate = 0,
      $base = '', $quote = '';
    
    public
      $fixedSumm = 0;
    
    public
      $pnl = 0,
      $unreleasedPNL = 0,
      $margin = 0,
      $extraMargin = 0,
      $entryPrice = 0,
      $markPrice = 0,
      $openFee = 0,
      $closeFee = 0,
      $liquidPrice = 0,
      $liquidPercent = 0;
    
    public
      $cred = [],
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
    
    public
      $flevel = 0,
      $rebate = [self::SPOT => 0, self::FUTURES => 0],
      $openMarketType = self::TAKER,
      $closeMarketType = self::TAKER,
      $hedgeMode = false,
      $crossMargin = true;
    
    public
      \Exchange\Calculator $calculator;
    
    public $side = self::LONG, $market = self::SPOT;
    
    const SPOT = 'SPOT', FUTURES = 'FUTURES';
    const PRICES_INDEX = 1, PRICES_MARK = 2, PRICES_LAST = 3;
    
    const LONG = 'LONG', SHORT = 'SHORT', BOTH = 'BOTH', BUY = 'BUY', SELL = 'SELL', MAKER = 'MAKER', TAKER = 'TAKER', BALANCE_AVAILABLE = 'available', BALANCE_TOTAL = 'total', BALANCE_EQUITY = 'equity', BALANCE_UPNL = 'upnl';
    
    static $PERPETUAL = 'PERPETUAL', $LEVERAGED = 'LEVERAGED', $BOTH = 'BOTH';
    
    public $timeframes = [
      
      'm' => 'minutes',
      'h' => 'hours',
      'd' => 'days',
      'w' => 'weeks',
      'M' => 'months',
      'y' => 'years',
      
    ];
    
    function __construct () {
      $this->calculator = new \Exchange\Calculator ();
    }
    
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
    
    function getRiskLeverage ($maxLeverage = 100) {
      
      $leverage = 0;
      
      do {
        
        $leverage++;
        $value = ($this->margin * $leverage);
        
      } while ($leverage <= $maxLeverage);
      
      return $leverage;
      
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
    
    function getMaintenanceMargin () {
      return ($this->maintenanceMarginRate / 100);
    }
    
    function getLiquidationPrice ($quote) {
      
      if ($this->market != self::SPOT and $this->leverage > 1) {
        
        $entryPrice = $this->getEntryPrice ();
        
        if ($this->quantity == 0) throw new \E ();
        
        $value = ($this->quantity * $entryPrice);
        $initialMargin = ($value / $this->leverage);
        $maitenanceMargin = ($value * $this->getMaintenanceMargin ());
        
        if ($this->crossMargin or $quote == 'USDC') {
          
          $balance = $this->getAvailableBalance ();
          
          $loss = ($balance - $maitenanceMargin) / $this->quantity;
          
          if ($this->isLong ())
            $price = $entryPrice - $loss;
          else
            $price = $entryPrice + $loss;
          
        } else {
          
          $loss = ($initialMargin - $maitenanceMargin) / $this->quantity;
          
          if ($this->isLong ())
            $price = $entryPrice - ($loss - ($this->extraMargin / $this->quantity));
          else
            $price = $entryPrice + ($loss + ($this->extraMargin / $this->quantity));
          
        }
        
        return $this->price ($price);
        
      } else return 0;
      
    }
    
    function getMarginPercent ($stopLoss) {
      
      $entryPrice = $this->getEntryPrice ();
      
      if ($this->isLong ())
        $stopPrice = ($entryPrice - $stopLoss);
      else
        $stopPrice = ($entryPrice + $stopLoss);
      
      $diff = $this->getProfit ($entryPrice, $percent->valueOf ($stopLoss));
      
    }
    
    function initialMarginRate () {
      return (1 / $this->leverage);
    }
    
    function getStopLoss ($quote, $entryPrice) {
      
      if ($this->stopLoss > 0) {
        
        $percent = (($entryPrice * $this->stopLoss) / 100);
        
        if ($this->isLong ()) {
          
          $stopPrice = $this->price ($entryPrice - $percent);
          if ($stopPrice > $this->liquidPrice) return $stopPrice;
          
        } else {
          
          $stopPrice = $this->price ($entryPrice + $percent);
          if ($stopPrice < $this->liquidPrice) return $stopPrice;
          
        }
        
      }
      
      return 0;
      
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
    
    function liquidPricePercent () {
      
      $entryPrice = $this->getEntryPrice ();
      
      $price = ($this->liquidPrice - $entryPrice);
      
      if ($this->liquidPrice > 0)
        return (($price * 100) / $entryPrice);
      else
        return 0;
      
    }
    
    function getMarginQuantity ($price) {
      return ($this->getSustainableLoss () / $price);
    }
    
    abstract function getPrices (int $type, string $base, string $quote, array $data): array;
    
    protected abstract function getBalances ($quote = ''): array;
    
    final function getBalance ($type, $quote = '') {
      
      if (!$this->balances)
        $this->balances = $this->getBalances ($quote);
      
      return $this->balances[$quote][$type];
      
    }
    
    function getPositionData ($base, $quote) {
      
      if (isset ($this->positions[$this->pair ($base, $quote)][$this->getSide ()]))
        $this->position = $this->positions[$this->pair ($base, $quote)][$this->getSide ()];
      else
        $this->position = [];
      
    }
    
    function setPositionData ($base, $quote, $data) {
      $this->positions[$this->pair ($base, $quote)][$this->getSide ()] = $data;
    }
    
    abstract function positionActive (): bool;
    
    function leverageRound ($value) {
      return round ($value, $this->leveragePrecision);
    }
    
    function clean ($quote) {
      
      $this->balances = [];
      
      $this->orders = [];
      $this->positions = $this->getPositions ('', $quote);
      
    }
    
    final function getTicker ($base, $quote) {
      return $this->getTickers ($base, $quote)[$this->pair ($base, $quote)];
    }
    
    function getNotional () {
      return ($this->margin * $this->leverage);
    }
    
    function getQuantity () {
      return $this->amount ($this->getNotional () / $this->entryPrice);
    }
    
    function getROE ($margin) {
      return ($this->getROI ($margin) / $this->leverage);
    }
    
    function getROI ($margin) {
      
      $percent = new \Percent ($this->pnl);
      
      $percent->delim = $margin;
      
      return $percent->valueOf (100);
      
    }
    
    function getProfit ($entry, $exit) {
      
      if ($this->isLong ())
        return ($exit - $entry);
      else
        return ($entry - $exit);
      
    }
    
    function getEquity () {
      return ($this->getWalletBalance () + $this->upnl);
    }
    
    function getActiveBalance () {
      
      $balance = $this->getWalletBalance ();
      if ($this->upnl < 0) $balance += $this->upnl;
      
      return $balance;
      
    }
    
    function getWalletBalance () {
      
      $percent = new \Percent ($this->walletBalance);
      
      if ($this->balancePercent <= 0 or $this->balancePercent > 100)
        $this->balancePercent = 100;
      
      $balance = $percent->valueOf ($this->balancePercent);
      
      if ($this->fixedSumm > 0)
        $balance -= $this->fixedSumm;
      
      return $balance;
      
    }
    
    function getAvailableBalance () {
      
      $balance = $this->balanceAvailable;
      
      if ($this->upnl < 0) $balance += $this->upnl;
      
      if ($this->fixedSumm > 0)
        $balance -= $this->fixedSumm;
      
      return $balance;
      
    }
    
    function getAverageEntryPrice ($quote) {
      
      $value = 0;
      $quantity = 0;
      
      if ($quote == 'USDT') {
        
        for ($i = 0; $i < count ($this->entryPrices); $i++) {
          
          $value += ($this->quantitys[$i] * $this->entryPrices[$i]);
          $quantity += $this->quantitys[$i];
          
        }
        
        return $this->price ($value / $quantity);
        
      }
      
      return 0;
      
    }
    
    function getEntryPrice () {
      return end_value ($this->entryPrices);
    }
    
    final function checkMargin ($quote): bool {
      
      $balanceAvailable = $this->getAvailableBalance ();
      
      $this->entryPrice = $this->markPrice;
      $this->openFee = $this->closeFee = 0;
      
      if ($this->openBalance > 0 and $balanceAvailable > 0) {
        
        if ($this->margin == 0)
          $this->balance = $this->openBalance;
        else
          $this->balance = $this->margin;
        
        if ($this->margin == 0) {
          
          $balance = $this->balance;
          
          if ($this->pruneValue > 0 and ($balance + $this->pruneValue) > $balanceAvailable)
            $balance -= $this->pruneValue;
          
          $percent = new \Percent ($balance);
          
          if ($this->marginPercent <= 0 or $this->marginPercent > 100)
            $this->marginPercent = 100;
          
          $this->margin = $percent->valueOf ($this->marginPercent);
          
        }
        
        if ($this->margin > 0) {
          
          $this->quantity = $this->getQuantity ();
          
          $this->openFee = $this->getOpenFee ($quote);
          
          return true;
          
        }
        
      }
      
      return false;
      
    }
    
    public $fullBalance = 0;
    
    final function open ($quote): bool {
      
      if ($this->checkMargin ($quote)) {
        
        if ($this->margin > 0 and $this->openFee > 0)
          $this->margin -= $this->openFee;
        
        $this->fullBalance = $this->balance;
        
        if ($this->openFee > 0)
          $this->balance -= $this->openFee;
        
        //if (($this->margin + $this->openFee) > $this->minValue[$this->market]) {
          
          $this->quantity = $this->getQuantity (); // Final
          
          $this->openFee = $this->getOpenFee ($quote);
          
          if ($this->quantity >= $this->minQuantity) {
            
            $balanceAvailable = $this->getAvailableBalance ();
            
            //$this->debug ($this->fullBalance, $balanceAvailable);
            
            $balanceAvailable -= $this->fullBalance;
            
            if ($balanceAvailable >= 0) {
              
              $this->quantitys[] = $this->quantity;
              $this->entryPrices[] = $this->entryPrice;
              
              return true;
              
            }
            
          }// else $this->debug ($this->quantity, $this->minQuantity);
          
        //}// else throw new \NotEnoughFundsException ($this, 'Position value must be more than '.$this->minValue[$this->market].'. Current value: '.$this->margin);
        
      }
      
      return false;
      
    }
    
    final function open2 (): bool {
      return ($this->maxQuantity <= 0 or $this->quantity <= $this->maxQuantity);
    }
    
    function getTotalQuantity () {
      
      $value = 0;
      
      foreach ($this->quantitys as $quantity)
        $value += $quantity;
      
      return $this->amount ($value);
      
    }
    
    final function update ($quote) {
      
      if ($this->quantity == 0)
        $this->quantity = $this->getTotalQuantity ();
      
      if ($this->quantity > 0) {
        
        $this->entryPrice = $this->getAverageEntryPrice ($quote);
        
        $this->openFee = $this->getOpenFee ($quote);
        $this->closeFee = $this->getCloseFee ($quote);
        
        $this->unreleasedPNL = ($this->getProfit ($this->entryPrice, $this->markPrice) * $this->quantity);
        $this->pnl = ($this->unreleasedPNL - $this->closeFee);
        
        $this->extraMargin = $this->getExtraMargin ();
        $this->liquidPrice = $this->getLiquidationPrice ($quote);
        $this->liquidPercent = $this->liquidPricePercent ();
        
        //$this->debug ($this->balance, $this->margin, $this->extraMargin);
        
      }
      
    }
    
    final function close () {
      
      $this->margin += $this->pnl;
      $this->balance += $this->pnl;
      $this->totalPNL += $this->pnl;
      
      $this->walletBalance += $this->pnl;
      
      $this->fees += $this->openFee;
      $this->fees += $this->closeFee;
      
      //if ($this->walletBalance < 0)
      //  $this->walletBalance = 0;
      
      $this->balanceAvailable += $this->balance;
      
      //if ($this->balanceAvailable < 0)
      //  $this->balanceAvailable = 0;
      
      array_pop ($this->quantitys);
      array_pop ($this->entryPrices);
      
      if ($this->pnl > 0) {
        
        if ($this->maxBalance > 0) {
          
          if ($this->getActiveBalance () > $this->maxBalance)
            $this->fixedSumm += $this->pnl;
          
        } elseif ($this->fixedBalance > 0) {
          
          if ($this->getActiveBalance () > $this->fixedBalance)
            if ($this->fixedSumm < $this->fixedBalance)
              $this->fixedSumm += $this->pnl;
          
        }
        
      }
      
    }
    
    function getExtraMargin () {
      
      if (!$this->crossMargin)
        $extraMargin = ($this->balance - $this->margin);
      else
        $extraMargin = 0;
      //$this->debug ($this->balance, $this->margin, $extraMargin);
      if ($extraMargin < 0)
        $extraMargin = 0;
      
      return $extraMargin;
      
    }
    
    function getInitialMargin () {
      
      if ($this->entryPrice > 0)
        return ($this->quantity * $this->entryPrice * (1 / $this->leverage));
      else
        throw new \ExchangeException ($this, 'Price must be higher than 0');
      
    }
    
    function getLeverage () {
      
      if ($this->market == self::SPOT)
        return 1;
      elseif ($this->leverage <= 0)
        return $this->position['leverage'];
      else
        return $this->leverage;
      
    }
    
    function getFeeRate ($marketType) {
      
      $value = $this->feesRate[$this->market][$this->flevel][($marketType == self::MAKER ? 0 : 1)];
      
      $percent = new \Percent ($value);
      
      return $percent->minus ($this->rebate[$this->market]);
      
    }
    
    function getOpenFee ($quote) {
      return ($this->quantity * $this->entryPrice) * ($this->getFeeRate ($this->openMarketType) / 100);
    }
    
    function getCloseFee ($quote) {
      return ($this->quantity * $this->markPrice) * ($this->getFeeRate ($this->closeMarketType) / 100);
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
    
    abstract function getOrders ($base, $quote);
    abstract function getOrderInfo ($id);
    
    function editPosition ($base, $quote, $data) {}
    
    function setFuturesMarginType ($base, $quote, $longLeverage = 10, $shortLeverage = 10) {}
    
    function getPositions ($base = '', $quote = '') {}
    
    abstract function getTrades ($base, $quote);
    abstract function getSymbols ($quote = '');
    abstract function cancelOrders ($base = '', $quote = '', $filter = '');
    
    function getFuturesOpenOrders ($base, $quote) {}
    function getFuturesFilledOrders ($base, $quote) {}
    function createFuturesTakeProfitOrder ($orders) {}
    function createFuturesStopOrder ($orders) {} // TODO
    function createFuturesTrailingStopOrder ($order) {}
    
    function getMarginType ($base, $quote) {}
    
    function cancelFuturesOrders ($base, $quote, array $ids) {}
    
    function cancelFuturesOrdersNames ($base, $quote, array $ids) {
      return [];
    }
    
    function getAccountStatus () {
      return '';
    }
    
    abstract function getTickers ($base = '', $quote = '');
    
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
    
    function prepDate ($date) {
      return $date;
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
    
    function setPairFuturesHedgeMode ($base, $quote) {}
    
    protected function quantity ($quantity) {
      return $this->amount ($quantity);
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
    
    function setMode ($base, $quote) {}
    function cancelOrderName ($base, $quote, $name) {}
    
    function createOrder ($base, $quote, $data = []) {}
    function closeOrder ($base, $quote, $data = []) {}
    function decreaseOrder ($base, $quote, $data = []) {}
    function closeAllPositions ($data = []) {}
    
    protected function prepOrder ($data) {
      return $data;
    }
    
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
    
    function createSocket (): ?\Exchange\Socket {
      return null;
    }
    
    function debug (...$data) {
      return debug ($this->date ($this->markDate).': '.$this->pair ($this->base, $this->quote).$this->side.': '.array2json ($data));
    }
    
    public
      $params = [],
      $method = self::POST,
      $signed = true,
      //$debug = 0,
      $errorCodes = [404],
      $showUrl = false,
      $func,
      $order;
    
    const GET = 'GET', POST = 'POST', PUT = 'PUT', DELETE = 'DELETE';
    
    function time () {
      
      $ts = $this->milliseconds () + $this->timeOffset;
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
    
    function signature () {
      
      ksort ($this->params);
      return hash_hmac ('sha256', http_build_query ($this->params), $this->cred['secret']);
      
    }
    
  }