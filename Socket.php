<?php
	
	namespace Exchange;
	
	abstract class Socket extends \Socket {
		
		public $func;
		public \Exchange $exchange;
		
		public $data = [], $topics = [];
		
		function __construct (\Exchange $exchange) {
			$this->exchange = $exchange;
		}
		
		abstract function ping ();
		abstract function getPrice ($start): array;
		abstract function getPricesTopic (int $type, string $base, string $quote, array $data): string;
		abstract function publicConnect (): ?\Socket;
		abstract function privateConnect (): ?\Socket;
		
		function connect ($path): \Socket {
			
			$this->debug = ($this->exchange->debug == 2 ? 1 : 0);
			
			return parent::connect ($path);
			
		}
		
	}