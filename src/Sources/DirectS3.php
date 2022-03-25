<?php
namespace CloudFinance\SimpleAppDriver\Sources;
use CloudFinance\SimpleAppDriver\Contracts\SimpleAppSource;

class DirectS3 implements SimpleAppSource {
	protected $key;
	protected $bucket;
	
	public function __construct(string $keyName, $bucketName = null){
		$this->bucket = empty($bucket_name) ?  config("cf_simpleapp_driver.default_s3_bucket",config("filesystems.disk.s3.bucket","")) : $bucketName;
		$this->key=$keyName;
	}
	
	public function getDataForRemoteRequest(): array {
		return [
			["name" => "keyName", "contents" => $this->key],
			["name" => "bucketname", "contents" => $this->bucket]
		];
	}
	public function getCacheValue(): string {
		return $this->bucket."@".$this->key;
	}
}