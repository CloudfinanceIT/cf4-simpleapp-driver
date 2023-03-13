<?php
namespace CloudFinance\SimpleAppDriver\Driver;

use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\Mime\MimeTypes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Macroable;
use JsonSerializable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use CloudFinance\SimpleAppDriver\Contracts\SimpleAppSource;
use CloudFinance\SimpleAppDriver\Exceptions\SimpleAppException;

class ExcelSimpleAppDriver implements Arrayable, JsonSerializable, Jsonable {
    
	use Macroable {
        __call as protected macroCall;
    }
	
	
    protected $iTimeout=60;
    protected $iSource;
    protected $iIntervals=[];
    protected $iValues=[];
	protected $iFillCells=[];
    protected $iSheetPassword=null;
	protected $cache_enabled;

	
	public function __construct(){
		$this->cache_enabled=(config("cf_simpleapp_driver.cache.enabled") === true);
	}
    		
	public function caching(bool $v){		
		$this->cache_enabled=($v && config("cf_simpleapp_driver.cache.enabled") === true);		
		return $this;
	}
	
    public function source(SimpleAppSource $w){        		
		$this->iSource=$w;		
        return $this;
    }    

	public function __call($name, $args){
		if (Str::endsWith($name,"Source")){                    
			$name="CloudFinance\SimpleAppDriver\Sources\\".ucfirst(substr($name,0,-6));                        
			if (class_exists($name) && is_subclass_of($name,SimpleAppSource::class,true)){
				return $this->source(new $name(...$args));
			}
		}else if (static::hasMacro($name)) {
            return $this->macroCall($name, $args);
        }
	}
	
	public function fillCells($w,$v=null){
		if ($this->coordinateIsRange($w) && in_array(strtolower($v),["down","up","left","right"])){       
			$w=strtoupper($w);
			$this->iFillCells[$w]=strtolower($v);
        }else if (is_array($w) || $w instanceof \Traversable){
			foreach ($w as $wa => $wd){
				$this->fillCells($wa,$wd);
            }            
        }
        return $this;
	}
    
	public function sheetsPassword($password){
        $this->iSheetPassword=(is_string($password) && !empty($password)) ? $password : null;
        return $this;
    }
	
    public function grep($w){              
		if (is_string($w)){
			$w=strtoupper($w);
			if (($this->coordinateIsRange($w) || $this->coordinateIsValid(Str::after($w, "!"))) && !in_array($w,$this->iIntervals)){
				$this->iIntervals[]=$w;
			}
		}else if (is_iterable($w)){
			foreach ($w as $wa){
                $this->grep($wa);
            } 
		}        
        return $this;
    }
    
    public function send($w,$v=null){        
        if (is_array($v) && !empty($v) && !Arr::isAssoc($v) && $this->coordinateIsRange($w)){            
			$w=strtoupper($w);
			$this->iValues[$w]=["interval" => $w, "data" => $v];                       
        }else if (is_iterable($w)){
			foreach ($w as $k => $v){
				$this->send($k,$v);
            }            
        }
        return $this;
    }
    
    public function timeout(int $q){
        $this->iTimeout=$q;
        return $this;
    }
	
	public function make(array $settings){
		$ret=new static;
		foreach ($settings as $fun => $args){
			if (in_array($fun,["timeout","send","grep","sheetsPassword","fillCells","source","caching"]) && is_array($args) && !Arr::isAssoc($args)){
				call_user_func_array([$ret,$fun],$args);
			}
		}
		return $ret;
	}
        
    public function toArray(): array {
        $raw=$this->getRemoteJsonData();
        $ret=array();
        foreach ($raw as $cff_row){
            $ret[$cff_row['interval']]=$cff_row['data'];
        }
        return $ret;
    }
    
    public function each(\Closure $cb){
        $raw=$this->getRemoteJsonData();
        $i=0;
        $q=count($raw);
        foreach ($raw as $cff_row){
            $a=call_user_func($cb, $cff_row, $i, $q);
            if ($a===false){
                break;
            }
            $i++;
        }        
        return $raw;
    }
    
     public function eachSpread(\Closure $cb){
        $raw=$this->getRemoteJsonData();
        $i=0;
        $q=count($raw);
        foreach ($raw as $cff_row){
            $a=call_user_func($cb, $cff_row['interval'], $cff_row['data'], $i, $q);
            if ($a===false){
                break;
            }
            $i++;
        }        
        return $raw;
    }

    public function toFile(){
		$fcContents=$this->usingCache("xlsx",function (){
			$response=$this->baseRequest("api/fileassembly");        
			$mime=$response->getHeader("Content-Type");        
			if (empty($mime)){
				throw new SimpleAppException("No mimetype received from the server!");
			}
			$ext=MimeTypes::getDefault()->getExtensions($mime[0]);
			if (empty($ext)){
				throw new SimpleAppException("Invalid mimetype '$mime' received from the server!");
			}		
			return (string) $response->getBody();
		});
		
				
		$tempFileName = sys_get_temp_dir().DIRECTORY_SEPARATOR."esaw-".Str::random(16).".".$ext[0];
		file_put_contents($tempFileName,$fcContents);
		
		return new SymfonyFile($tempFileName);        
    }
    
    public function first(){
        $f=Arr::first($this->getRemoteJsonData());
        return (is_array($f) && isset($f['data'])) ? $f['data'] : null;
    }
    
    public function last(){
        $f=Arr::last($this->getRemoteJsonData());
        return (is_array($f) && isset($f['data'])) ? $f['data'] : null;
    }

    public function jsonSerialize() {
        return $this->toArray();
    }

    public function toJson($options = 0): string {
        return json_encode($this->toArray(),$options);
    }
    
    public function getRemoteJsonData(){
        
        return json_decode($this->usingCache("json",function (){
			return (string) $this->baseRequest($this->my_source()->usesS3() ? "api/readfrombucket" : "api/readfromfile")->getBody();
		}),true) ?? [];
    }
    
     protected function baseRequest(string $uri){
        $data=[
            ["name" => "app_secret", "contents" => config("cf_simpleapp_driver.secret")]
        ];
        if (!empty($this->iIntervals)){            
            foreach ($this->iIntervals as $i => $v){
                $data[]=["name" => "intervals[$i]", "contents" => $v];
            }
        }
        if (!empty($this->iValues)){            
            $data[]=["name" => 'values', "contents" => json_encode(array_values($this->iValues))];
        }        
        
        if (!empty($this->password)){
            $data[]=["name" => 'password', "contents" => $this->iSheetPassword];
        }
		
		if (!empty($this->iFillCells)){
			$i=0;
			foreach ($iFillCells as $ad => $dir){
				$data[]=["name" => "fills[$i][interval]", "contents" => $id];
				$data[]=["name" => "fills[$i][value]", "contents" => $dir];
				$i++;
			}
			unset($i);
		}
	
		$data=array_merge($data,$this->my_source()->getDataForRemoteRequest());
		
        $url=$this->getUrl($uri);
        $mypid=strtoupper(uniqid("ESAW-"));
        $mypid_start=microtime(true);
        if (config("cf_simpleapp_driver.debug")!=false){            
            Log::debug($mypid." REQUEST ".json_encode([
                "url" => $url,
                "id" => spl_object_id($this),
                "at" => $mypid_start,
				"source" => get_class($this->my_source())." ::: ".$this->my_source()->getCacheValue(),
                "grep" => $this->iIntervals,
                "send" => (config("cf_simpleapp_driver.debug")=='full') ? $this->iValues : array_keys($this->iValues)
            ]));
        }        
        
        $client = new \GuzzleHttp\Client([
            'timeout'         => $this->iTimeout,
            'allow_redirects' => false,      
        ]);    
        
        $response = $client->request("POST", $url,['multipart' => $data]);
        
        $responseBodyStr=(string) $response->getBody();
        
        if (config("cf_simpleapp_driver.debug")=='full'){  
            Log::debug($mypid." RESPONSE ".json_encode([
                "duration" => microtime(true)-$mypid_start,
                "status" => $response->getStatusCode(),
                "headers" => $response->getHeaders(),
                "body" => $responseBodyStr
            ]));
        }
        
        if ($response->getStatusCode()!=200){
            $m=$mypid." CfExcelSimpleDriver requesto to '$url' failed: HTTP ".$response->getStatusCode()." ".$responseBodyStr;
            Log::error($m);            
            throw new SimpleAppException($m);
        }
        
        return $response;
    }

    protected function getUrl(string $uri){
        $uri=Str::start($uri,"/");
        $base_uri=config("cf_simpleapp_driver.base_url","");
        if (Str::endsWith($base_uri, "/")){
            $base_uri=Str::beforeLast($base_uri, "/");
        }
        return $base_uri.$uri;
    }
    
    protected function coordinateIsValid($pCoordinateString){
        return (preg_match('/^[a-z]+[1-9]+[0-9]*$/i',$pCoordinateString) > 0);        
    }

    protected function coordinateIsRange($coord){
        if (!is_string($coord)){
            return false;
        }
        $coord=explode(":",$coord);
        if (count($coord)!=2){
            return false;
        }
        $coord[0]=Str::after($coord[0], "!");
        
       return ($this->coordinateIsValid($coord[0]) && $this->coordinateIsValid($coord[1]));
    }
	
	protected function usingCache(string $rootKey, $filler){
		if (!$this->cache_enabled){
			return value($filler);
		}
	
		$key=$rootKey."-".sha1(json_encode([
			config("cf_simpleapp_driver.base_url",""),
			$this->iIntervals,
			$this->iValues,
			$this->iFillCells,
			$this->iSheetPassword,
			$this->my_source()->usesS3(),
			$this->my_source()->getCacheValue()
		]));		
		$nf=config("cf_simpleapp_driver.cache.folder","");
		if (!empty($nf)){
			$nf=Str::finish($nf,DIRECTORY_SEPARATOR);
		}
		$nf.=$key.".dat";
		if ($this->storage()->exists($nf)){
			$duration=intval(config("cf_simpleapp_driver.cache.duration",0))*60;
			if ($duration<=0 || (time()-$this->storage()->lastModified($nf))<=$duration){					
				if (config("cf_simpleapp_driver.debug")!=false){            
					Log::debug(strtoupper(uniqid("ESAW-"))." REQUEST ".json_encode([						
						"id" => spl_object_id($this),
						"at" => microtime(true),
						"source" => get_class($this->my_source())." ::: ".$this->my_source()->getCacheValue(),
						"grep" => $this->iIntervals,
						"send" => (config("cf_simpleapp_driver.debug")=='full') ? $this->iValues : array_keys($this->iValues)
					])." SERVED BY CACHE FILE ".$nf);
				}        			
				return unserialize($this->storage()->get($nf));
			}else{
				$this->storage()->delete($nf);
			}
		}
		$ret=value($filler);
		$this->storage()->put($nf,serialize($ret));
		return $ret;
	}
    
	protected function storage(){
		return Storage::disk(config("cf_simpleapp_driver.cache.disk",""));
	}
	
	protected function my_source() {
		if ($this->iSource instanceof SimpleAppSource){
			return $this->iSource;
		}
		throw new SimpleAppException("Invalid source given!");
	}
}
