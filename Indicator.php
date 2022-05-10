<?php
	
	class Indicator {
		
		public $prices = [];
		
		public $period = 0, $factor = 18;
		
		protected $start = 0, $num = 0;
		
		function SMA () {
			
			$sma = 0;
			
			$this->num = count ($this->prices);
			$this->start = ($this->num - $this->period);
			
			for ($i = $this->start; $i < $this->num; $i++) $sma += $this->prices[$i]['close'];
			
			$sma /= $this->period;
			
			return $sma;
			
		}
		
		function EMA () {
			
			$multiplier = ($this->factor / ($this->period + 1));
			
			$ema = $this->SMA ();
			
			for ($i = $this->start; $i < $this->num - 1; $i++) {
				
				$price = $this->prices[$i];
				
				$ema = ((($price['close'] - $ema) * $multiplier) + $ema);
				
			}
			
			return $ema;
			
		}
		
	}