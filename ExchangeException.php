<?php
	
	class ExchangeException extends \Exception {
		
		public $func, $proxy, $order;
		
		function __construct ($message, $code = 0, $func = '', $proxy = [], $order = []) {
			
			parent::__construct ($message, $code);
			
			$this->func = $func;
			$this->proxy = $proxy;
			$this->order = $order;
			
		}
		
	}