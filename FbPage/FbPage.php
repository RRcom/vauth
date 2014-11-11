<?php
require_once __DIR__.'/../AbstractFbObject.php';
require_once __DIR__.'/PageTab.php';

class FbPage extends AbstractFbObject
{
    public $id;
    public $about;
    public $can_post;
    public $category;
    public $cover = array();
    public $cover_id;
    public $offset_x;
    public $offset_y;
    public $source;
    public $has_added_app;
    public $is_community_page;
    public $is_published;
    public $new_like_count;
    public $likes;
    public $link;
    public $name;
    public $offer_eligible;
    public $parking = array();
    public $lot;
    public $street;
    public $valet;
    public $promotion_eligible;
    public $talking_about_count;
    public $unread_message_count;
    public $unread_notif_count;
    public $unseen_message_count;
    public $username;
    public $website;
    public $were_here_count;
    public $access_token;

    /**
     * List page tabs
     * @return PageTab[]
     * @throws Exception
     */
    public function getTabs()
    {
        $result = $this->getFb()->api('/'.$this->id.'/tabs', 'GET', array(
            'access_token' => $this->access_token
        ));
        if(isset($result['error'])) throw new Exception($result['error']['message']);
        $objectArray = array();
        if(isset($result['data'])) {
            foreach($result['data'] as $page) {
                $object = new PageTab();
                    foreach($page as $key => $value) {
                        $object->{$key} = $value;
                    }
                $objectArray[] = $object;
            }
        }
        return $objectArray;
    }

    /**
     * Add new tab to page
     * @param string $appId
     * @return bool
     */
    public function addTab($appId)
    {
        $result = $this->getFb()->api('/'.$this->id.'/tabs', 'POST', array(
            'access_token' => $this->access_token,
            'app_id' => $appId,
        ));
        return $result;
    }

    /**
     * Delete a page tab
     * @param string $appId
     * @return bool
     */
    public function deleteTab($appId)
    {
        $result = $this->getFb()->api('/'.$this->id.'/tabs', 'DELETE', array(
            'access_token' => $this->access_token,
            'app_id' => $appId,
        ));
        return $result;
    }
}