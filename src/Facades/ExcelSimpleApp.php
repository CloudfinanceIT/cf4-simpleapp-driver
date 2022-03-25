<?php
namespace CloudFinance\SimpleAppDriver\Facades;
use Illuminate\Support\Facades\Facade;


class ExcelSimpleApp extends Facade {
	
    protected static function getFacadeAccessor() { 
        return "cf-excel-simple-app";        
    }
}