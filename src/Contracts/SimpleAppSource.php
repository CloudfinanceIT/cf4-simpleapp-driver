<?php
namespace CloudFinance\SimpleAppDriver\Contracts;

interface SimpleAppSource {
	public function simpleAppGetDataForRemoteRequest(): array;
	public function simpleAppGetCacheKey(): string;
	public function simpleAppUsesS3(): bool;
}