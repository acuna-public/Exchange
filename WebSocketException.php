<?php
	
	class WebSocketException extends Exception {
		
		function __construct ($message, $code = 0) {
			
			parent::__construct ($message);
			
			$this->code = $code;
			
		}
		
	}