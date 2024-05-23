<?php
	
	namespace Exchange;
	
	class Calculator {
		
		public \Exchange $exchange;
		
		protected $price;
		
		function grid ($from, $to, $num, $balance) {
			
			$quantity = 0;
			$margin = ($balance / $num);
			
			if ($this->price == 0)
				$to = $to - (($to - $from) / 2);
			else
				$to = $to - (($to - $from) / 2);
			
			$step = ($to - $from) / $num;
			
			for ($i = 1; $i <= $num; $i++) {
				
				$price = ($to - ($step * $i));
				$quantity += ($margin / $price);
				//debug ($i.'. '.implode (' - ', [$margin, $price, ($step * $i), ($margin / $price), $quantity]));
				
			}
			
			$quantity2 = ($quantity / $num);
			
			$balance = 0;
			debug ();
			
			for ($i = 2; $i <= $num + 1; $i++) {
				
				$this->price = ($to + ($step * $i));
				$balance += ($quantity2 * $this->price);
				
				$quantity -= $quantity2;
				//debug ($i.'. '.implode (' - ', [$quantity, $this->price, ($step * $i), ($quantity2 * $this->price), $balance]));
				
			}
			debug ($balance);
			return $balance;
			
		}
		
	}