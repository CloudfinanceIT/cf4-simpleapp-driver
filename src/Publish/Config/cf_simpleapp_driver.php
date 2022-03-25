<?php
return [
   "base_url" => env("CF_SIMPLEAPP_URL"),	
   "secret" => env("CF_SIMPLEAPP_SECRET"),
   "debug" => env("SIMPLEAPP_LOG",false),
   "default_s3_bucket" => env('AWS_BUCKET'),  
   "cache" => [
		"enabled" => env("CF_SIMPLEAPP_CACHE_ENABLED",true),
		"disk" => env('FILESYSTEM_DRIVER', 'local'),
		"folder" => env("CF_SIMPLEAPP_CACHE_FOLDER","CfSimpleAppCacheData"),	
		"duration" => 0, // Minutes. 0 = Infinite
	]
];

