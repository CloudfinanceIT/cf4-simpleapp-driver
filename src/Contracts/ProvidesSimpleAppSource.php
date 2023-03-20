<?php
namespace CloudFinance\SimpleAppDriver\Contracts;

interface ProvidesSimpleAppSource {
	public function getSimpleAppSource() : SimpleAppSource;
}