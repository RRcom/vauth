<?php

class EventOnSuccessLogin
{
	protected $CI;

	public function __construct($ci)
	{
		$this->CI = $ci;
	}
	
	public function onTrigger($vauth)
    {
        echo "EventOnSuccessLogin Triggered";
    }
}