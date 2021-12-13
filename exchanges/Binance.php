<?php
  
  namespace Exchange;
  
  class Binance extends \Exchange {
    
    public $days = ['1m' => 1, '5m' => 2, '30m' => 10, '1h' => 20, '2h' => 499, '4h' => 120, '1d' => 1], $ratios = ['2h' => [1.2, 1.8]];
    public $limit = 120;
    
    public $fees = [
      
      'USDT' => [
        
        [0.0200, 0.0400],
        [0.0160, 0.0400],
        [0.0140, 0.0350],
        [0.0120, 0.0320],
        
      ],
      
      'COIN' => [
        
        [0.0100, 0.0500], // 30d BTC Volume Maker / Taker %
        [0.0080, 0.0450],
        [0.0050, 0.0400],
        [0.0030, 0.0300],
        
      ],
      
    ];
    
    public $curChanges = [
      
      '1000SHIB' => 'SHIB',
      
    ];
    
    public $interval = '1m', $timeOffset;
    
    protected $userKey, $futuresKey;
    
    function getName () {
      return 'binance';
    }
    
    function getTitle () {
      return 'Binance';
    }
    
    function setCredentials ($cred) {
      
      parent::setCredentials ($cred);
      
      if ($this->timeOffset == null) {
        
        $request = $this->getRequest (__FUNCTION__);
        
        $request->signed = false;
        
        $data = $request->connect ('api/v3/time');
        
        if (isset ($data['serverTime']))
          $this->timeOffset = ($data['serverTime'] - (microtime (true) * 1000));
        
      }
      
    }
    
    function pair ($cur1, $cur2) {
      return $cur1.$cur2;
    }
    
    function getCharts (array $data) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      if (isset ($this->curChanges[$data['cur1']]))
        $data['cur1'] = $this->curChanges[$data['cur1']];
      
      $request->params = [
        
        'symbol' => $this->pair ($data['cur1'], $data['cur2']),
        'interval' => (isset ($data['interval']) ? $data['interval'] : $this->interval),
        
      ];
      
      if (isset ($data['start_time']))
        $request->params['startTime'] = ($data['start_time'] * 1000);
      
      if (isset ($data['end_time']))
        $request->params['endTime'] = ($data['end_time'] * 1000);
      
      if (isset ($data['limit']))
        $request->params['limit'] = $data['limit'];
      
      $request->signed = false;
      $request->debug = 0;
      
      $summary = [];
      
      foreach ($request->connect ('api/v3/klines') as $value)
        $summary[] = [
          
          'date' => ($value[0] / 1000),
          'date_text' => self::date ($value[0] / 1000),
          'low' => $value[3],
          'high' => $value[2],
          'open' => $value[1], // Покупка
          'close' => $value[4], // Продажа
          
        ];
      
      return $summary;
      
    }
    
    function getBalances () {
      
      if (!$this->balances)
        foreach ($this->request ('api/v3/account')['balances'] as $data)
          $this->balances[$data['asset']] = $data['free'];
      
      return $this->balances;
      
    }
    
    function createOrder ($type, $cur1, $cur2, $amount, $price) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'type' => 'LIMIT',
        'side' => $type,
        'quantity' => $this->amount ($amount),
        'price' => $price,
        'timeInForce' => 'GTC',
        
      ];
      
      $request->method = Request::POST;
      
      return $request->connect ('api/v3/order');
      
    }
    
    function createMarketOrder ($type, $cur1, $cur2, $amount) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'type' => 'MARKET',
        'side' => $type,
        'quantity' => $this->amount ($amount),
        
      ];
      
      $request->method = Request::POST;
      
      return $request->connect ('api/v3/order');
      
    }
    
    function getOrders ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $data = $request->connect ('api/v3/allOrders');
      
      $output = [];
      
      foreach ($data as $order)
        $output[strtolower ($order['side'])][$order['orderId']] = $this->orderData ($order);
      
      return $output;
      
    }
    
    protected function orderData ($order) {
      
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
    
    function getHolds ($id) {
      return $this->request ('api/v3/balances/'.$id.'/holds');
    }
    
    function getFuturesBalances () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->market = Request::FUTURES;
      
      foreach ($request->connect ('fapi/v2/balance') as $data)
        $this->futuresBalances[$data['asset']] = $data['availableBalance'];
      
      return $this->futuresBalances;
      
    }
    
    function setFuturesLeverage ($cur1, $cur2, $leverage) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'leverage' => $leverage,
        
      ];
      
      $request->method = Request::POST;
      $request->market = Request::FUTURES;
      
      return $request->connect ('fapi/v1/leverage');
      
    }
    
    function setFuturesMarginType ($cur1, $cur2, $type) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'marginType' => $type,
        
      ];
      
      $request->method = Request::POST;
      $request->market = Request::FUTURES;
      
      return $request->connect ('fapi/v1/marginType');
      
    }
    
    function getFuturesPositions ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      
      return $request->connect ('fapi/v2/positionRisk');
      
    }
    
    function createUserStreamKey () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->method = Request::POST;
      
      $data = $request->connect ('api/v3/userDataStream');
      $this->userKey = $data['listenKey'];
      
    }
    
    function updateUserStreamKey () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'listenKey' => $this->userKey,
        
      ];
      
      $request->method = Request::PUT;
      
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
      
      $request->method = Request::POST;
      $request->market = Request::FUTURES;
      
      $data = $request->connect ('fapi/v1/listenKey');
      $this->futuresKey = $data['listenKey'];
      
    }
    
    function updateFuturesStreamKey () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'listenKey' => $this->futuresKey,
        
      ];
      
      $request->method = Request::PUT;
      
      return $request->connect ('v1/listenKey');
      
    }
    
    function getTrades ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      
      return $request->connect ('fapi/v1/userTrades');
      
    }
    
    function getExchangeInfo () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->signed = false;
      
      return $request->connect ('api/v3/exchangeInfo');
      
    }
    
    function getFuturesExchangeInfo () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->signed = false;
      
      $request->market = Request::FUTURES;
      
      return $request->connect ('fapi/v1/exchangeInfo');
      
    }
    
    function getCurrencyPairs ($type, $cur2 = '') {
      
      $symbols = [];
      
      foreach ($this->getExchangeInfo ()['symbols'] as $symbol) {
        
        if ((!$cur2 or $symbol['quoteAsset'] == $cur2) and $symbol['status'] == 'TRADING' and in_array ($type, $symbol['permissions'])) {
          
          $symbol['cur1'] = $symbol['baseAsset'];
          $symbol['cur2'] = $symbol['quoteAsset'];
          
          $symbols[$symbol['symbol']] = $symbol;
          
        }
        
      }
      
      return $symbols;
      
    }
    
    function getFuturesCurrencyPairs ($cur2 = '') {
      
      $symbols = [];
      
      foreach ($this->getFuturesExchangeInfo ()['symbols'] as $symbol) {
        
        if ((!$cur2 or $symbol['marginAsset'] == $cur2) and $symbol['underlyingType'] == 'COIN') {
          
          $symbol['cur1'] = $symbol['baseAsset'];
          $symbol['cur2'] = $symbol['marginAsset'];
          
          $symbols[$symbol['symbol']] = $symbol;
          
        }
        
      }
      
      return $symbols;
      
    }
    
    function getFuturesOpenOrders ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      
      return $request->connect ('fapi/v1/openOrders');
      
    }
    
    function createFuturesOrder ($orders) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'batchOrders' => json_encode ($orders)
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('fapi/v1/batchOrders');
      
    }
    
    function openFuturesMarketPosition ($order) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($order['cur1'], $order['cur2']),
        'side' => ($order['side'] == self::LONG ? 'BUY' : 'SELL'),
        'type' => 'MARKET',
        'quantity' => $this->amount ($order['quantity']),
        
      ];
      //debug ([$this->futuresBalance, $this->margin, $order['quantity'], ($this->futuresBalance), $request->params]);
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('fapi/v1/order');
    
    }
    
    protected function createFuturesBatchOrder ($orders, $func) {
      
      //debug ($orders);
      
      $request = $this->getRequest ($func, $orders);
      
      $request->params = [
        
        'batchOrders' => json_encode ($orders)
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('fapi/v1/batchOrders');
      
    }
    
    function createFuturesMarketTakeProfitOrder ($orders) {
      
      foreach ($orders as $i => $order) {
        
        $orders[$i]['side'] = ($this->isLong () ? self::SELL : self::BUY);
        
      }
      
      return $this->createFuturesTypeOrder ($orders, 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET', __FUNCTION__);
      
    }
    
    function createFuturesMarketStopOrder ($orders) {
      
      foreach ($orders as $i => $order) {
        
        $orders[$i]['price_type'] = 'MARK_PRICE';
        $orders[$i]['side'] = ($this->isLong () ? self::SELL : self::BUY);
        
      }
      
      return $this->createFuturesTypeOrder ($orders, 'STOP', 'STOP_MARKET', __FUNCTION__);
      
    }
    
    protected function createFuturesTypeOrder ($orders, $type1, $type2, $func) {
      
      $list = [];
      
      foreach ($orders as $order) {
        
        $data = [
          
          'symbol' => $this->pair ($order['cur1'], $order['cur2']),
          'type' => (isset ($order['price']) ? $type1 : $type2),
          'side' => $order['side'],
          'stopPrice' => $this->price ($order['trigger_price']),
          'priceProtect' => 'TRUE',
          
        ];
        
        if (isset ($order['quantity']))
          $data['quantity'] = $this->amount ($order['quantity']);
        else
          $data['closePosition'] = 'true';
        
        if (isset ($order['price']))
          $data['price'] = $this->price ($order['price']);
        
        if (isset ($order['name']))
          $data['newClientOrderId'] = $order['name'];
        
        if (isset ($order['price_type']))
          $data['workingType'] = $order['price_type'];
        
        $list[] = $data;
        
      }
      
      return $this->createFuturesBatchOrder ($list, $func);
      
    }
    
    function createFuturesTrailingStopOrder ($order) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($order['cur1'], $order['cur2']),
        'type' => 'TRAILING_STOP_MARKET',
        'side' => $order['side'],
        'quantity' => $this->amount ($order['quantity']),
        'callbackRate' => $order['rate'],
        'priceProtect' => 'TRUE',
        'workingType' => 'MARK_PRICE',
        'side' => ($this->isLong () ? self::SELL : self::BUY),
        'reduceOnly' => 'true',
        
      ];
      
      if (isset ($order['price']))
        $request->params['activationPrice'] = $this->price ($order['price']);
      
      if (isset ($order['name']))
        $request->params['newClientOrderId'] = $order['name'];
      
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('fapi/v1/order');
      
    }
    
    function cancelFuturesOpenOrders ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::DELETE;
      
      return $request->connect ('fapi/v1/allOpenOrders');
      
    }
    
    function longShortRatio ($cur1, $cur2, $period) {
      
      $summary = [];
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'period' => $period,
        
      ];
      
      $request->market = Request::FUTURES;
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
      return ($order['origType'] == 'STOP_MARKET');
    }
    
    function isOrderTakeProfit ($order) {
      return ($order['origType'] == 'TAKE_PROFIT_MARKET');
    }
    
    function orderId ($order) {
      return $order['orderId'];
    }
    
    function orderName ($order) {
      return $order['clientOrderId'];
    }
    
    function cancelFuturesOrders ($cur1, $cur2, $ids) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'orderIdList' => json_encode ($ids),
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::DELETE;
      
      return $request->connect ('fapi/v1/batchOrders');
      
    }
    
    function cancelFuturesOrdersNames ($cur1, $cur2, $ids) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'origClientOrderIdList' => json_encode ($ids),
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::DELETE;
      
      return $request->connect ('fapi/v1/batchOrders');
      
    }
    
    protected function getRequest ($func, $order = []) {
      return new Request ($this, $func, $order);
    }
    
    function orderCreateDate ($order) {
      return ($order['updateTime'] / 1000);
    }
    
    function getFuturesPrices ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      
      $data = $request->connect ('fapi/v1/premiumIndex');
      
      return ['mark_price' => $data['markPrice'], 'index_price' => $data['indexPrice']];
      
    }
    
    function getAccountStatus () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      return $request->connect ('sapi/v1/account/status')['data'];
      
    }
    
    function getFuturesBrackets ($cur1 = '', $cur2 = '') {
      
      $request = $this->getRequest (__FUNCTION__);
      
      if ($cur1 and $cur2)
        $request->params['symbol'] = $this->pair ($cur1, $cur2);
      
      $request->market = Request::FUTURES;
      
      $data = $request->connect ('fapi/v1/leverageBracket');
      
      if (!$cur1 and !$cur2) {
        
        $output = [];
        
        foreach ($data as $pair)
          $output[$pair['symbol']] = $this->prepBracket ($pair['brackets']);
        
      } else $output = $this->prepBracket ($data[0]['brackets']);
      
      return $output;
      
    }
    
    protected function prepBracket ($brackets) {
      
      $output = [];
      $count = count ($brackets) - 1;
      
      $i2 = 0;
      
      for ($i = $count; $i >= 0; $i--) {
        
        $output[$i2] = ['leverage' => $brackets[$i]['initialLeverage'], 'notional' => $brackets[$i]['notionalCap']];
        $i2++;
        
      }
      
      return $output;
      
    }
    
    function ticker ($cur1 = '', $cur2 = '') {
      
      $request = $this->getRequest (__FUNCTION__);
      
      if ($cur1 and $cur2)
        $request->params['symbol'] = $this->pair ($cur1, $cur2);
      
      $request->signed = false;
      
      $data = $request->connect ('api/v3/ticker/24hr');
      
      if (!$cur1 and !$cur2) {
        
        $output = [];
        
        foreach ($data as $pair)
          $output[$pair['symbol']] = $this->prepTicker ($pair);
        
      } else $output = $this->prepTicker ($data);
      
      return $output;
      
    }
    
    function futuresTicker ($cur1 = '', $cur2 = '') {
      
      $request = $this->getRequest (__FUNCTION__);
      
      if ($cur1 and $cur2)
        $request->params['symbol'] = $this->pair ($cur1, $cur2);
      
      $request->market = Request::FUTURES;
      $request->debug = 0;
      
      $data = $request->connect ('fapi/v1/ticker/24hr');
      
      if (!$cur1 and !$cur2) {
        
        $output = [];
        
        foreach ($data as $pair)
          $output[$pair['symbol']] = $this->prepTicker ($pair);
        
      } else $output = $this->prepTicker ($data);
      
      return $output;
      
    }
    
    protected function prepTicker ($item) {
      return ['change' => $item['priceChange'], 'change_percent' => $item['priceChangePercent'], 'close' => $item['lastPrice']];
    }
    
  }
  
  class Request {
    
    public
      $apiUrl = 'https://api.binance.com',
      $futuresUrl = 'https://fapi.binance.com',
      $streamsUrl = 'tls://stream.binance.com',
      
      $testApiUrl = 'https://testnet.binance.vision',
      $testFuturesUrl = 'https://testnet.binancefuture.com',
      $testStreamsUrl = 'tls://testnet-dex.binance.org';
    
    public
      $params = [],
      $method = self::GET,
      $market,
      $signed = true,
      $debug = 1,
      $errorCodes = [405],
      $func,
      $order,
      $recvWindow = 60000; // 1 minute
    
    const GET = 'GET', POST = 'POST', PUT = 'PUT', DELETE = 'DELETE';
    const FUTURES = 'FUTURES';
    
    protected $socket, $binance;
    
    function __construct ($binance, $func, $order = []) {
      
      $this->binance = $binance;
      $this->func = $func;
      $this->order = $order;
      
      //$this->socket = new \WebSocket ($this->streamsUrl, 9443);
      
    }
    
    function connect ($path) {
      
      $ch = curl_init ();
      
      if ($this->binance->debug and $this->debug) {
        
        if ($this->market == self::FUTURES)
          $url = $this->testFuturesUrl;
        else
          $url = $this->testApiUrl;
        
      } else {
        
        if ($this->market == self::FUTURES)
          $url = $this->futuresUrl;
        else
          $url = $this->apiUrl;
        
      }
      
      if ($this->signed) {
        
        $this->params['recvWindow'] =	$this->recvWindow;
        $this->params['timestamp'] = $this->time ();
        $this->params['signature'] = $this->signature ();
        
      }
      
      if ($this->params and $this->method != self::POST)
        $path .= '?'.http_build_query ($this->params);
      
      $options = [
        
        CURLOPT_URL => $url.'/'.$path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'User-Agent: Mozilla/4.0 (compatible; PHP Binance API)',
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
      
      if ($this->signed)
        $options[CURLOPT_HTTPHEADER][] = 'X-MBX-APIKEY: '.$this->binance->cred['key'];
      
      if ($this->binance->proxies) {
        
        $proxy = trim ($this->binance->proxies[mt_rand (0, count ($this->binance->proxies) - 1)]);
        
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
      
      $this->binance->queryNum++;
      
      $options[CURLOPT_SSL_CIPHER_LIST] = 'TLSv1';
      
      if ($error = curl_error ($ch))
        throw new \ExchangeException ($error, curl_errno ($ch), $this->func, $proxy, $this->order);
      elseif (in_array ($info['http_code'], $this->errorCodes))
        throw new \ExchangeException ($options[CURLOPT_URL].' '.http_get_message ($info['http_code']), $info['http_code'], $this->func, $proxy, $this->order);
      
      $data = json_decode ($data, true);
      
      curl_close ($ch);
      
      if (isset ($data[0]['msg']) or (isset ($data[0]['code']) and $data[0]['code'] == 400))
        throw new \ExchangeException ($data[0]['msg'], $data[0]['code'], $this->func, $proxy, $this->order); // Типа ошибка
      elseif (isset ($data['msg']) and !isset ($data['code']))
        throw new \ExchangeException ($data['msg'], 0, $this->func, $proxy, $this->order);
      elseif (isset ($data['msg']) and $data['code'] != 200)
        throw new \ExchangeException ($data['msg'], $data['code'], $this->func, $proxy, $this->order);
      
      return $data;
      
    }
    
    protected function time () {
      
      $ts = (microtime (true) * 1000) + $this->binance->timeOffset;
      return number_format ($ts, 0, '.', '');
      
    }
    
    protected function signature () {
      return hash_hmac ('sha256', http_build_query ($this->params), $this->binance->cred['secret']);
    }
    
    function socket ($key, $callback) {
      
      $this->socket->path = 'ws/'.$key;
      
      $this->socket->open ();
      
      $this->socket->read ($callback);
      
    }
    
  }