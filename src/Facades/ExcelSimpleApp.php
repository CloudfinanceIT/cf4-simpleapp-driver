<?php
namespace CloudFinance\SimpleAppDriver\Facades;

class ExcelSimpleApp extends Facade {
	
    protected static function getFacadeAccessor() { 
        return "cf-excel-simple-app";        
    }
}