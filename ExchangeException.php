<?php
	
	class ExchangeException extends \Exception {
		
		public $info, $func;
		
		function __construct ($message, $code = 0, $info = [], $func = '') {
			
			parent::__construct ($message, $code);
			
			$this->info = $info;
			$this->func = $func;
			
		}
		
	}