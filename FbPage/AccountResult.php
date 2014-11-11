<?php
require_once __DIR__.'/../AbstractFbObject.php';
require_once __DIR__.'/FbPage.php';

class AccountResult extends AbstractFbObject
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $category;

    /**
     * @var string
     */
    public $accessToken;

    /**
     * @var array
     */
    public $permission;

    /**
     * @var string number string;
     */
    public $id;

    /**
     * @param $id
     * @param $accessToken
     * @param Facebook $fb
     */
    public function __construct($id, $accessToken, Facebook $fb)
    {
        $this->id = $id;
        $this->accessToken = $accessToken;
        $this->fb = $fb;
    }

    /**
     * Get instance of page object
     * @return FbPage
     * @throws Exception
     */
    public function fetchObject()
    {
        $result = $this->fb->api('/'.$this->id);
        if(isset($result['error'])) throw new Exception($result['error']['message']);
        $fbPage = new FbPage();
        foreach($result as $key => $value) {
            $fbPage->{$key} = $value;
        }
        $fbPage->access_token = $this->accessToken;
        $fbPage->setFb($this->getFb());
        return $fbPage;
    }
}