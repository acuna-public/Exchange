<?php
	
	class Indicator {
		
		public $prices = [], $start = 0, $period = 0, $factor = 0;
		
		protected $num = 0;
		
		function SMA () {
			
			$this->num = count ($this->prices);
			
			$value = 0;
			
			for ($i = $this->start; $i < $this->num; $i++)
				$value += $this->prices[$i]['close'];
			
			$value /= $this->period;
			
			return $value;
			
		}
		
		function EMA () {
			
			$value = $this->SMA ();
			
			$multiplier = ($this->factor / ($this->period + 1));
			
			for ($i = $this->start; $i < $this->num; $i++)
				$value = ((($this->prices[$i]['close'] - $value) * $multiplier) + $value);
			
			return $value;
			
		}
		
	}