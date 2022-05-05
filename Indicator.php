<?php
	
	class Indicator {
		
		public $prices = [];
		
		public $period = 0, $factor = 2;
		
		function SMA () {
			
			$sma = 0;
			foreach ($this->prices as $price) $sma += $price['close'];
			
			$sma /= $this->period;
			
			return $sma;
			
		}
		
		function EMA () {
			
			$multiplier = ($this->factor / ($this->period + 1));
			
			$ema = $this->SMA ();
			
			for ($i = 0; $i < count ($this->prices); $i++) {
				
				$price = $this->prices[$i];
				
				$ema = ((($price['close'] - $ema) * $multiplier) + $ema);
				
			}
			
			return $ema;
			
		}
		
	}