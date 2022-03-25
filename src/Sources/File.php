<?php
namespace CloudFinance\SimpleAppDriver\Sources;
use CloudFinance\SimpleAppDriver\Contracts\SimpleAppSource;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

class File implements SimpleAppSource {
	protected $file;
	protected $cache_max_precision;
	protected $ck;
	
	public function __construct($w, bool $cache_max_precision=true){
		$this->file=$w instanceof SymfonyFile ? $w : new SymfonyFile($w);
		$this->cache_max_precision=$cache_max_precision;
	}
	
	public function getDataForRemoteRequest(): array {
		return [
			["name" => "file", "contents" => \GuzzleHttp\Psr7\Utils::tryFopen($this->file->getPathname(),"r")]
		];
	}
	public function getCacheValue(): string {
		if (empty($this->ck)){
			$this->ck = $this->cache_max_precision ? sha1_file($this->file->getPathname()) : $this->file->getPathname();
		}
		return $this->ck;
	}
 
}