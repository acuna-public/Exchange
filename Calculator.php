<?php
	
	namespace Exchange;
	
	class Calculator {
		
		public \Exchange $exchange;
		
		function grid ($from, $to, $num, $part, $balance = 0) {
			
			$quantity = 0;
			
			for ($price = $to; $price >= $from; $price--)
				$quantity += ($part / $price);
			
			$quantity /= $num;
			
			for ($price = ($from + 1); $price <= $to; $price++)
				$balance += ($quantity * $price);
			
			return $balance;
			
		}
		
	}