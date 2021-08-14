<?php
  
  namespace Exchange;
  
  class Payeer extends \Exchange {
    
    public
      $url = 'https://payeer.com';
    
    protected $sessid, $cookies;
    
    protected function pair ($cur1, $cur2) {
      return $cur1.'_'.$cur2;
    }
    
    protected function sessid () {
      
      if (!$this->sessid) {
        
        $data = $this->request ('ru/account/trade/', [], [], false);
        
        preg_match_all ('~<script type="text/javascript">(.+?)</script>~si', $data, $match);
        preg_match ('~\{(.+)\}~si', $match[1][1], $match);
        
        $this->sessid = json_decode (str_replace ('\'', '"', $match[0]), true)['bitrix_sessid'];
        
      }
      
      return $this->sessid;
      
    }
    
    function getCharts ($cur1, $cur2) {
      
      $summary = ['start' => 0, 'end' => 0, 'last_close' => 0, 'last_high' => 0, 'charts' => []];
      
      foreach ($this->request ('static/trade/chart/chart_'.$this->pair ($cur1, $cur2).'_30m.json') as $i => $value) {
        
        $summary['charts'][] = [
          
          'low' => $value[3],
          'high' => $value[2],
          'open' => $value[1],
          'close' => $value[4],
          'date' => ($value[0] / 1000),
          
        ];
        
        if ($i == 0)
          $summary['start'] = $value[2];
        
      }
      
      $summary['last_close'] = $value[4]; // Продажа
      $summary['last_high'] = $value[2]; // Покупка
      $summary['last_open'] = $value[1];
      
      return $summary;
      
    }
    
    function getBalances () {
      
      if (!$this->balances) {
        
        $data = $this->request ('bitrix/components/payeer/account.info2/templates/top2/ajax.php', [
          
          'action' => 'balance2',
          'sessid' => $this->sessid (),
          
        ]);
        
        foreach ($data['balance'] as $currency => $data)
          $this->balances[$currency] = $data;
        
      }
      
      return $this->balances;
      
    }
    
    function getBalances ($cur) {
      
      $balance = parent::getBalances ($cur);
      return ($balance[0].'.'.$balance[1]);
      
    }
    
    function createOrder ($type, $cur1, $cur2, $amount, $price) {
      
      $data = $this->request ('bitrix/components/trade/'.$type.'/templates/2020/ajax.php', [], [
        
        'amount' => $amount,
        'price' => $price,
        'total' => ($amount * $price),
        'curr_out' => $cur1,
        'curr_in' => $cur2,
        'action' => $type,
        'block' => 0,
        'sessid' => $this->sessid (),
        
      ]);
      
      return $this->output ($data);
      
    }
    
    protected function output ($data) {
      
      if ($data['error']) {
        
        $mess = '';
        foreach ($data['error'] as $error)
        $mess .= $error['value']."\n";
        
        throw new \TraderBotException ($mess);
        
      } else return $data;
      
    }
    
    function isOrderDone ($id) {
      
      $info = $this->getOrderInfo ($id);
      return ($info && $info['status'] == 'done');
      
    }
    
    function getOrderInfo ($id) {
      return $this->request ('orders/'.$id);
    }
    
    function getHolds ($id) {
      return $this->request ('balances/'.$id.'/holds');
    }
    
    protected $attempt = 2;
    
    protected function request ($path, $params = [], $postdata = [], $json = true) {
      
      $this->attempt++;
      
      $curl = curl_init ();
      
      if ($params) $path .= '?'.http_build_query ($params);
      
      $options = [
        
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $this->url.'/'.$path,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_PROXY => 'qQHMnH:wAPXpaNUHl@109.248.54.208:3000',
        
      ];
      
      if ($postdata) {
        
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = http_build_query ($postdata);
        
      }
      
      $options[CURLOPT_COOKIE] = 'BITRIX_SM_SOUND_LOGIN_PLAYED=Y; CTKN=36cbb5c9ae0a1184e6e72cd9f568c182721ead88a26a5a5d803c4bd3ee89bcbc; BITRIX_SM_SALE_UID=0; PHPSESSID=rpag8f1vldu46fkv8tk8018qiogloov1bka7fu2vu2tsqugs9pesa3g2mu55lq0j170qaulc0qbkbvcppgs2j92a2o3osdd6rre6vt3';
      
      $options[CURLOPT_HTTPHEADER] = [
        
      ];
      
      $options[CURLOPT_REFERER] = $this->url.'/ru/account/trade/';
      
      curl_setopt_array ($curl, $options);
      
      $resp = curl_exec ($curl);
      
      $code = curl_getinfo ($curl, CURLINFO_HTTP_CODE);
      
      if ($error = curl_error ($curl))
        throw new \TraderBotException ($error.' ('.curl_errno ($curl).')');
      elseif (!in_array ($code, [200]))
        throw new \TraderBotException ($code);
      
      if (!$resp and $this->attempt <= 2) {
        
        $data = $this->request ('ru/auth/', [], [], false);
        
        preg_match_all ('~<input type="hidden" name="sessid" id="sessid" value="(.+?)"\s*/>~si', $data, $match);
        preg_match_all ('~<input type="hidden" name="sign" value="(.+?)"\s*/>~si', $data, $match2);
        
        $resp = $this->request ('bitrix/components/auth/system.auth.authorize/templates/.default/ajax.php', [], [
          
          'action' => 'authorization',
          'block' => 0,
          'email' => $this->config['login'],
          'password' => $this->config['password'],
          'sessid' => $match[1][0],
          'sign' => $match2[1][0],
          'g-recaptcha-response' => '',
          'recaptcha' => '',
          'backurl' => '/ru/account/',
          'security_code' => 'QSFUp1DJiTLPoswXRp47mgvQQiRFjDkT',
          'recaptcha_v3' => '',
          
        ]);
        
        if ($resp['error'])
          throw new \TraderBotException ('Can\'t auth');
        
      } elseif ($json)
        $resp = json_decode ($resp, true);
      //else
      //  $this->cookies = curl_getinfo ($curl, CURLINFO_COOKIELIST);
      
      $this->cookies = 'BITRIX_SM_SOUND_LOGIN_PLAYED=Y; CTKN=36cbb5c9ae0a1184e6e72cd9f568c182721ead88a26a5a5d803c4bd3ee89bcbc; BITRIX_SM_SALE_UID=0; PHPSESSID=rpag8f1vldu46fkv8tk8018qiogloov1bka7fu2vu2tsqugs9pesa3g2mu55lq0j170qaulc0qbkbvcppgs2j92a2o3osdd6rre6vt3';
      
      //$this->attempt = 0;
      
      curl_close ($curl);
      
      return $resp;
      
    }
    
  }