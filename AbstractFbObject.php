<?php
require_once 'FbAwareInterface.php';
require_once 'facebook.php';

abstract class AbstractFbObject implements FbAwareInterface
{
    protected $fb;

    /**
     * @param Facebook $fb
     */
    public function setFb(Facebook $fb)
    {
        $this->fb = $fb;
    }

    /**
     * @return Facebook
     */
    public function getFb()
    {
        return $this->fb;
    }
}