<?php
  
  namespace Exchange\Socket;
  
  class BybitSocket extends \Exchange\Socket {
    
    public
      $streamsUrl = 'stream.bybit.com',
      $testStreamsUrl = 'testnet-dex.bybit.org';
    
    function connect ($path): \Socket {
      
      $this->url = $this->streamsUrl;
      
      $this->data = [
        
        'req_id' => 1,
        'op' => 'subscribe',
        'args' => [],
        
      ];
      
      foreach ($this->topics as $topic)
        $this->data['args'][] = $topic;
      
      return parent::connect ($path);
      
    }
    
    function publicConnect (): ?\Socket {
      return $this->connect ('v5/public/linear');
    }
    
    function privateConnect (): ?\Socket {
      return null;
    }
    
    function ping () {
      
      $this->url = $this->streamsUrl;
      
      $this->data = ['req_id' => 1, 'op' => 'ping'];
      
      return parent::connect ('v5/public/linear');
      
    }
    
    function getPricesTopic (int $type, string $base, string $quote, array $data): string {
      return 'kline.'.$this->exchange->intervalChanges[$data['interval']].'.'.$this->exchange->pair ($base, $quote);
    }
    
    function getPrice ($start): array {
      
      try {
        
        $con = $this->read ();
        
        if ($start)
          $con = explode ("\r\n\r\n", $con)[1];
        
        $data = json2array ($con);
        
        if (isset ($data['data'])) {
          
          $price = $data['data'][0];
          //if ($price['confirm']) debug ([$data['topic'], $this->exchange->date ($this->exchange->prepDate ($price['timestamp'] / 1000))]);
          return [
            
            'topic' => $data['topic'],
            'low' => (float) $price['low'],
            'high' => (float) $price['high'],
            'open' => (float) $price['open'],
            'close' => (float) $price['close'],
            'volume' => (float) $price['volume'],
            'closed' => $price['confirm'],
            'date' => $this->exchange->prepDate ($price['timestamp'] / 1000),
            'date_text' => $this->exchange->date ($this->exchange->prepDate ($price['timestamp'] / 1000)),
            
          ];
          
        }
        
      } catch (\JsonException $e) {
        
      }
      
      return [];
      
    }
    
  }