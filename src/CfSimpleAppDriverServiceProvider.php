<?php
namespace CloudFinance\SimpleAppDriver;
use Illuminate\Support\ServiceProvider;
use CloudFinance\SimpleAppDriver\Driver\ExcelSimpleAppDriver;

class CfSimpleAppDriverServiceProvider extends ServiceProvider
{
   
    public function register()
    {
        $this->app->bind("cf-excel-simple-app", ExcelSimpleAppDriver::class);
               
    }

    
    public function boot()
    {
        
        if ($this->app->runningInConsole()) {          
        
            $this->publishes([
                __DIR__.'/Publish/Config/cf_simpleapp_driver.php' => config_path('cf_simpleapp_driver.php'),
            ]);
           
        }
    }
    
    
}
