<?php
require_once 'facebook.php';

interface FbAwareInterface
{
    /**
     * @param Facebook $fb
     */
    public function setFb(Facebook $fb);

    /**
     * @return Facebook
     */
    public function getFb();
}