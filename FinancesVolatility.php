<?php
  
  namespace Finances;
  
  class Volatility {
    
    public $prices, $N;
    
    public $test = [
      
      [
        
        'close' => 596.2284,
        'high' => 600.3405,
        'low' => 591.9310,
        'open' => 596.2746,
        
      ],
      
    ];
    
    function __construct (array $prices) {
      
      $this->prices = $prices;
      
      $this->N = count ($this->prices);
      
    }
    
    function YangZhang () {
      
      $k = (0.34 / (1.34 + (($this->N + 1))));
      if ($this->N > 1) $k /= ($this->N - 1);
      
      $sigma  = $this->CloseOpen ();
      $sigma += $this->OpenClose () * $k;
      $sigma += $this->RogersSatchell () * (1 - $k);
      
      //$sigma = sqrt ($sigma);
      
      return $sigma;
      
    }
    
    function CloseOpen () {
      
      $average = 0;
      $last = 0;
      
      foreach ($this->prices as $i => $price) {
        
        if ($i > 0)
          $average += $this->value ($price['open'], $last);
        
        $last = $price['close'];
        
      }
      
      $mu = ($average / $this->N);
      
      $summ = 0;
      $last = 0;
      //debug ($mu);
      foreach ($this->prices as $i => $price) {
        
        //debug ([$i, $price['date'], date ('d', $price['date']), $price['open']]);
        
        if ($i > 0) {
          
          $average = ($this->value ($price['open'], $last) - $mu);
          //debug ([$price['open'], $last]);
          $summ += pow ($average, 2);
          //debug ('//'.$summ);
        }
        
        $last = $price['close'];
        
      }
      
      if ($this->N > 1) $summ /= ($this->N - 1);
      
      return $summ;
      
    }
    
    function OpenClose () {
      
      $average = 0;
      
      foreach ($this->prices as $price)
        $average += $this->value ($price['close'], $price['open']);
      
      $mu = ($average / $this->N);
      
      $summ = 0;
      
      foreach ($this->prices as $price) {
        
        $average = ($this->value ($price['close'], $price['open']) - $mu);
        $summ += pow ($average, 2);
        
      }
      
      if ($this->N > 1) $summ /= ($this->N - 1);
      
      return $summ;
      
    }
    
    function average () {
      
      $average = 0;
      
      foreach ($this->prices as $price)
        $average += $price;
      
      $mu = ($average / $this->N);
      
      $summ = 0;
      
      foreach ($this->prices as $price)
        $summ += pow ($price - $mu, 2);
      
      if ($this->N > 1) $summ /= ($this->N - 1);
      
      return sqrt ($summ);
      
    }
    
    function average2 () {
      
      $average = 0;
      
      foreach ($this->prices as $price) {
        
        debug ($price['close']);
        $average += $price['close'];
        
      }
      $mu = ($average / $this->N);
      
      $summ = 0;
      
      foreach ($this->prices as $price)
        $summ += pow ($price['close'] - $mu, 2);
      
      if ($this->N > 1) $summ /= ($this->N - 1);
      
      return sqrt ($summ);
      
    }
    
    function value ($open, $close) {
      return log ($open) - log ($close);
    }
    
    function RogersSatchell () {
      
      $sigma = 0;
      
      foreach ($this->prices as $price) {
        
        $high  = $this->value ($price['high'], $price['open']);
        $high *= $this->value ($price['high'], $price['close']);
        
        $sigma += $high;
        
        $high  = $this->value ($price['low'], $price['open']);
        $high *= $this->value ($price['low'], $price['close']);
        
        $sigma += $high;
        
      }
      
      return ($sigma / $this->N);
      
    }
    
  }