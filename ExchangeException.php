<?php
	
	class ExchangeException extends \Exception {
		
		public $info, $func;
		
		function __construct ($message, $code, $info, $func) {
			
			parent::__construct ($message, $code);
			
			$this->info = $info;
			$this->func = $func;
			
		}
		
	}