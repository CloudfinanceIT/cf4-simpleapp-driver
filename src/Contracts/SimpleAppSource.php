<?php
namespace CloudFinance\SimpleAppDriver\Contracts;

interface SimpleAppSource {
	public function getDataForRemoteRequest(): array;
	public function getCacheValue(): string;
	public function usesS3(): bool;
}