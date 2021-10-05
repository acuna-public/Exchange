<?php
  
  class Volatility {
    
    public $prices, $N;
    
    public static $test = [
      
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
    
    function YangZhang2 () {
      
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
    
    function RogersSatchell2 () {
      
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
    
    // all the estimators compute sigma^2
    
    // the preferred volatility estimator
    // for more info, see paper "Drift-independent Volatility Estimation Based on High, Low, Open and Close Prices"
    
    function YangZhang ($open, $high, $low) {
      
      $No = log ($open) - log (Ref ($price['close'], -1)); // normalized open
      $Nu = log ($high) - log ($open); // normalized high
      $Nd = log ($low) - log ($open); // normalized low
      $Nc = log ($price['close']) - log ($open); // normalized close
      
      $Vrs = 1 / $this->N * Sum ($Nu * ($Nu - $Nc) + $Nd * ($Nd - $Nc), $this->N); // RS volatility estimator
      
      $NoAvg = 1 / $this->N * Sum ($No, $this->N);
      $Vo = 1 / ($this->N - 1) * Sum (($No - $NoAvg) ^ 2, $this->N);
      
      $NcAvg = 1 / $this->N * Sum ($Nc, $this->N);
      $Vc = 1 / ($this->N - 1) * Sum (($Nc - $NcAvg) ^ 2, $this->N);
      
      $k = 0.34 / (1.34 + ($this->N + 1) / ($this->N - 1));
      
      return $Vo + $k * $Vc + (1 - $k) * $Vrs;
      
    }
    
    // the Parkinson volatility estimator
    
    function Parkinson ($open, $high, $low) {
      
      //$No = log ($open) - log (Ref ($price['close'], -1)); // normalized open
      $Nu = log ($high) - log ($open); // normalized high
      $Nd = log ($low) - log ($open); // normalized low
      //$Nc = log ($price['close']) - log ($open); // normalized close
      
      return 1 / ($this->N * 4 * log (2)) * Sum (($Nu - $Nd) ^ 2, $this->N);
      
    }
    
    // volatility recommended by Rogers AND Satchell (1991) AND Rogers, Satchell, AND Yoon (1994)
    
    function RogersSatchell ($open, $high, $low) {
      
      //$No = log ($open) - log (Ref ($price['close'], -1)); // normalized open
      $Nu = log ($high) - log ($open); // normalized high
      $Nd = log ($low) - log ($open); // normalized low
      $Nc = log ($price['close']) - log ($open); // normalized close
      
      return 1 / $this->N * Sum ($Nu * ($Nu - $Nc) + $Nd * ($Nd - $Nc), $this->N);
      
    }
    
    // the traditional close-to-close volatility
    
    function c2c () {
      
      $avg = 0;
      
      foreach ($this->prices as $i => $price) {
        
        if ($i > 0)
          $avg += log ($price['close']) - log ($last);
        
        $last = $price['close'];
        
      }
      
      $avg *= 1 / $this->N;
      $summ = 0;
      
      foreach ($this->prices as $i => $price) {
        
        if ($i > 0) {
          
          $ret = log ($price['close']) - log ($last);
          $summ += pow ($ret - $avg, 2);
          
        }
        
        $last = $price['close'];
        
      }
      
      return 1 / ($this->N > 1 ? $this->N - 1 : 1) * $summ;
      
    }
    
  }