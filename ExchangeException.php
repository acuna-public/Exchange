<?php
  
  class ExchangeException extends Exception {
    
    public $func, $order;
    
    function __construct ($message, $code, $func, $order) {
      
      parent::__construct ($message, $code);
      
      $this->func = $func;
      $this->order = $order;
      
    }
    
  }