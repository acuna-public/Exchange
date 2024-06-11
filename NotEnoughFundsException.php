<?php
	
	class NotEnoughFundsException extends \Exception {
		
		public \Exchange $exchange;
		
		public $info, $func;
		
		function __construct (\Exchange $exchange, $message, $code = 0, $info = [], $func = '') {
			
			parent::__construct ($message, $code);
			
			$this->exchange = $exchange;
			$this->info = $info;
			$this->func = $func;
			
		}
		
	}