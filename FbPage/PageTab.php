<?php

class PageTab
{
    /** @var string */
    public $id;
    /** @var string */
    public $image_url;
    /** @var string */
    public $name;
    /** @var string */
    public $link;
    /** @var App */
    public $application;
    /** @var  bool */
    public $is_permanent;
    /** @var int */
    public $position;
    /** @var bool */
    public $is_non_connection_landing_tab;
}