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
    
    public $interval = '1m', $timeOffset;
    
    protected $userKey, $futuresKey;
    
    function __construct ($timeOffset = -1) {
      
      parent::__construct ([]);
      
      $this->timeOffset = $timeOffset;
      
      if ($this->timeOffset < 0) {
        
        $request = $this->getRequest (__FUNCTION__);
        
        $request->signed = false;
        
        $data = $request->connect ('v3/time');
        
        if (isset ($data['serverTime']))
          $this->timeOffset = ($data['serverTime'] - (microtime (true) * 1000));
        
      }
      
    }
    
    function pair ($cur1, $cur2) {
      return $cur1.$cur2;
    }
    
    function getCharts ($cur1, $cur2) {
      
      $summary = ['first_close' => 0, 'last_close' => 0, 'last_high' => 0, 'charts' => []];
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'interval' => $this->interval,
        //'limit' => $this->limit,
        
      ];
      
      $request->signed = false;
      $request->debug = 0;
      
      foreach ($request->connect ('v3/klines') as $i => $value) {
        
        if ($i == 0) $summary['first_close'] = $value[4];
        
        $summary['charts'][] = [
          
          'date' => ($value[0] / 1000),
          'low' => $value[3],
          'high' => $value[2],
          'open' => $value[1],
          'close' => $value[4],
          
        ];
        
      }
      
      $summary['last_close'] = $value[4]; // Продажа
      $summary['last_high'] = $value[2]; // Покупка
      $summary['last_open'] = $value[1];
      
      return $summary;
      
    }
    
    function getBalances () {
      
      if (!$this->balances)
        foreach ($this->request ('v3/account')['balances'] as $data)
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
      
      return $request->connect ('v3/order');
      
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
      
      return $request->connect ('v3/order');
      
    }
    
    function getOrders ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $data = $request->connect ('v3/allOrders');
      
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
      
      return $this->orderData ($request->connect ('v3/order'));
      
    }
    
    function getHolds ($id) {
      return $this->request ('v3/balances/'.$id.'/holds');
    }
    
    function getFuturesBalances () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->market = Request::FUTURES;
      
      foreach ($request->connect ('v2/balance') as $data)
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
      
      return $request->connect ('v1/leverage');
      
    }
    
    function setMarginType ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'marginType' => $this->marginType,
        
      ];
      
      $request->method = Request::POST;
      $request->market = Request::FUTURES;
      
      return $request->connect ('v1/marginType');
      
    }
    
    function getFuturesPositions ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      
      return $request->connect ('v2/positionRisk');
      
    }
    
    function createUserStreamKey () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->method = Request::POST;
      
      $data = $request->connect ('v3/userDataStream');
      $this->userKey = $data['listenKey'];
      
    }
    
    function updateUserStreamKey () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'listenKey' => $this->userKey,
        
      ];
      
      $request->method = Request::PUT;
      
      return $request->connect ('v3/userDataStream');
      
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
      
      $data = $request->connect ('v1/listenKey');
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
      
      return $request->connect ('v1/userTrades');
      
    }
    
    function getExchangeInfo () {
      
      $request = $this->getRequest (__FUNCTION__);
      return $request->connect ('v3/exchangeInfo');
      
    }
    
    function getFuturesOpenOrders ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      
      return $request->connect ('v1/openOrders');
      
    }
    
    function createFuturesOrder ($orders) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'batchOrders' => json_encode ($orders)
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('v1/batchOrders');
      
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
      
      return $request->connect ('v1/order');
    
    }
    
    protected function amount ($amount) {
      return mash_number_format ($amount, 3, '.', '');
    }
    
    protected function price ($amount) {
      return mash_number_format ($amount, 2, '.', '');
    }
    
    protected function createFuturesBatchOrder ($orders, $func) {
      
      //debug ($orders);
      
      $request = $this->getRequest ($func, $orders);
      
      $request->params = [
        
        'batchOrders' => json_encode ($orders)
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('v1/batchOrders');
      
    }
    
    function createFuturesMarketTakeProfitOrder ($orders) {
      return $this->createFuturesTypeOrder ($orders, 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET', __FUNCTION__);
    }
    
    function createFuturesMarketStopOrder ($orders) {
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
        'reduceOnly' => 'true',
        
      ];
      
      if (isset ($order['price']))
        $request->params['activationPrice'] = $this->price ($order['price']);
      
      $request->market = Request::FUTURES;
      $request->method = Request::POST;
      
      return $request->connect ('v1/order');
      
    }
    
    function cancelFuturesOpenOrders ($cur1, $cur2) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::DELETE;
      
      return $request->connect ('v1/allOpenOrders');
      
    }
    
    function longShortRatio ($cur1, $cur2) {
      
      $summary = [];
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        
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
      
      return $request->connect ('v1/batchOrders');
      
    }
    
    function cancelFuturesOrdersNames ($cur1, $cur2, $ids) {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->params = [
        
        'symbol' => $this->pair ($cur1, $cur2),
        'origClientOrderIdList' => json_encode ($ids),
        
      ];
      
      $request->market = Request::FUTURES;
      $request->method = Request::DELETE;
      
      return $request->connect ('v1/batchOrders');
      
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
      
      $data = $request->connect ('v1/premiumIndex');
      
      return ['mark_price' => $data['markPrice'], 'index_price' => $data['indexPrice']];
      
    }
    
    function getAccountStatus () {
      
      $request = $this->getRequest (__FUNCTION__);
      
      $request->market = Request::ACCOUNT;
      
      return $request->connect ('v1/account/status')['data'];
      
    }
    
  }
  
  class Request {
    
    public
      $apiUrl = 'https://api.binance.com/api',
      $futuresUrl = 'https://fapi.binance.com/fapi',
      $accountUrl = 'https://api.binance.com/sapi',
      $streamsUrl = 'tls://stream.binance.com',
      
      $testApiUrl = 'https://testnet.binance.vision/api',
      $testFuturesUrl = 'https://testnet.binancefuture.com/fapi',
      $testStreamsUrl = 'tls://testnet-dex.binance.org/api';
    
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
    const FUTURES = 'FUTURES', ACCOUNT = 'ACCOUNT';
    
    protected $socket, $binance;
    
    function __construct ($binance, $func, $order = []) {
      
      $this->binance = $binance;
      $this->func = $func;
      $this->order = $order;
      
      //$this->socket = new \WebSocket ($this->streamsUrl, 9443);
      
    }
    
    function connect ($path) {
      
      $ch = curl_init ();
      
      if ($this->binance->debug and $this->debug)
        $url = ($this->market == self::FUTURES ? $this->testFuturesUrl : $this->testApiUrl);
      else {
        
        if ($this->market == self::FUTURES)
          $url = $this->futuresUrl;
        elseif ($this->market == self::ACCOUNT)
          $url = $this->accountUrl;
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
      
      if ($this->method == self::POST) {
        
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = http_build_query ($this->params);
        
      } elseif ($this->method == self::PUT)
        $options[CURLOPT_PUT] = true;
      elseif ($this->method != self::GET)
        $options[CURLOPT_CUSTOMREQUEST] = $this->method;
      
      $options[CURLOPT_HTTPHEADER] = [];
      
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
        
      }
      
      curl_setopt_array ($ch, $options);
      
      $data = curl_exec ($ch);
      $info = curl_getinfo ($ch);
      
      $this->binance->queryNum++;
      
      if ($error = curl_error ($ch))
        throw new \ExchangeConnectException ($error, curl_errno ($ch), $this->func, $this->order);
      elseif (in_array ($info['http_code'], $this->errorCodes))
        throw new \ExchangeException ($options[CURLOPT_URL].' '.http_get_message ($info['http_code']), $info['http_code'], $this->func, $this->order);
      
      $data = json_decode ($data, true);
      
      curl_close ($ch);
      
      if (isset ($data[0]['msg']) or (isset ($data[0]['code']) and $data[0]['code'] == 400))
        throw new \ExchangeException ($data[0]['msg'], $data[0]['code'], $this->func, $this->order); // Типа ошибка
      elseif (isset ($data['msg']) and !isset ($data['code']))
        throw new \ExchangeException ($data['msg'], 0, $this->func, $this->order);
      elseif (isset ($data['msg']) and $data['code'] != 200)
        throw new \ExchangeException ($data['msg'], $data['code'], $this->func, $this->order);
      
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