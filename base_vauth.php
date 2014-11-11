<?php // vauth client v0.9.9.0

include __DIR__.'/EventOnSuccessLogin.php';
require_once __DIR__.'/FbPage/AccountResult.php';

class base_vauth {

    const FB_PERMISSION_USER_LIKES = 'user_likes';
    const FB_PERMISSION_MANAGE_PAGE = 'manage_pages';
    
    const REQUEST_MODE_UNDEFINED = 0;
    const REQUEST_MODE_PUBLIC_PHOTOS = 1;
    const REQUEST_MODE_FB_ALBUM_LIST = 2;
    const REQUEST_MODE_UPLOAD_PHOTO = 3;
    const REQUEST_MODE_DELETE_PHOTO = 4;
    const REQUEST_MODE_DB_READ_ALL_MEMBERS = 5;
    const REQUEST_MODE_DB_CREATE_MEMBERS = 6;
    const REQUEST_MODE_DB_UPDATE_MEMBERS = 7;
    const REQUEST_MODE_COUPON_CHECK = 8;
    const REQUEST_MODE_COUPON_REDEEM = 9;
    const IMAGE_SERVER = 'http://image.vigattin.com';
    
    private $CONFIG;
    private $KEY;
    private $FB_APP_ID;
    private $FB_APP_SECRET;
    
    public $API_LINK = 'http://www.vigattin.com/vapi';
    public $SSL_CHECK_CERT = FALSE;
    
    public $AUTH_DOMAIN = 'http://www.vigattin.com/index.php/signin/activating';
    public $AUTH_DOMAIN_SECURE = 'https://www.vigattin.com/index.php/signin/activating';
    public $AUTH_DOMAIN_FBCONNECT = 'http://www.vigattin.com/index.php/signin/fbconnect';
    public $AUTH_DOMAIN_FBCONNECT_SECURE = 'https://www.vigattin.com/index.php/signin/fbconnect';
    public $AUTH_DOMAIN_LOGOUT = 'http://www.vigattin.com/index.php/signin/deactivating';
    public $AUTH_DOMAIN_LOGOUT_SECURE = 'https://www.vigattin.com/index.php/signin/deactivating';
    public $REDIRECT_URL;
    protected $CI;
    protected $REQUEST_EXPIRE = 1800; // 30 minutes
	protected $eventOnSuccessLogin;

    // initial
    public function __construct() {
        $this->CONFIG = $this->get_config();
        $this->KEY = $this->CONFIG['key'];
        $this->FB_APP_ID = $this->CONFIG['fb_app_id'];
        $this->FB_APP_SECRET = $this->CONFIG['fb_app_secret'];
        $this->CI = &get_instance();
        $this->CI->load->config('config');
        $this->CI->load->library('session');
        $this->CI->load->library('vauth/facebook', array('appId'  => $this->FB_APP_ID, 'secret' => $this->FB_APP_SECRET, 'cookie' => true));
        $this->REDIRECT_URL = $this->CI->config->item('base_url');
		$this->eventOnSuccessLogin = new EventOnSuccessLogin($this->CI);
        if((isset($_GET['logout'])) && ($_GET['logout'] ==  'user')) {
            header('P3P: CP="NOI ADM DEV COM NAV OUR STP"');
            $this->clear_data();
            exit('clear success');
        }
        if((isset($_GET['login'])) && ($_GET['login'] ==  'user')) {
            header('P3P: CP="NOI ADM DEV COM NAV OUR STP"');
            $info = $this->parse_info();
            if(count($info)) {
                if(isset($info['vauth_expire']) && (intval($info['vauth_expire']) > time())) {
                    $this->save_to_session($info);
					$this->eventOnSuccessLogin->onTrigger($this);
                    print_r($info);
                }
                else {
                    echo "auth expired";
                    exit();
                }
            }
            else {
                echo "auth failed";
            }
            exit();
        }
        $this->CI->facebook->setAccessToken($this->CI->session->userdata('facebook_access_token'));
    }
    
    public static function build_object() {
        return new self();
    }
    
    // login-logout (new API)
    
    /**
     * Create login URL
     * 
     * @param string $redirect Url to be redirected after login.
     * @param bool $secure Use https protocol for security. 
     * @return string Url for login.
     */
    public function get_login_url($redirect = '', $secure = FALSE) {
        if($redirect == '') $redirect = $this->get_current_url();
        if($secure) return $this->AUTH_DOMAIN_SECURE.'?redirect='.urlencode(urldecode($redirect));
        else return $this->AUTH_DOMAIN.'?redirect='.urlencode(urldecode($redirect));
    }
    
    /**
     * Create logout URL
     * 
     * @param string $redirect Url to be redirected after logout.
     * @param bool $secure Use https protocol for security. 
     * @return string Url for logout.
     */
    public function get_logout_url($redirect = '', $secure = FALSE) {
        if($redirect == '') $redirect = $this->get_current_url();
        if($secure) return $this->AUTH_DOMAIN_LOGOUT_SECURE.'?redirect='.urlencode(urldecode($redirect));
        else return $this->AUTH_DOMAIN_LOGOUT.'?redirect='.urlencode(urldecode($redirect));
    }
    
    /**
     * Check if user is login or not
     * 
     * @return boolean true if login false if not.
     */
    public function is_login() {
        if($this->CI->session->userdata('vauth_vigid')) return true;
        else return false;
    }
    
    //================= user data (new API) =====================
    
    /**
     * Get user facebook ID from vigattin members database
     * 
     * @return int Facebook ID.
     */
    public function get_id() {
        return strval($this->CI->session->userdata('vauth_id'));
    }
    
    /**
     * Get user unique ID from vigattin members database
     * 
     * @return int User ID
     */
    public function get_vigid() {
        return $this->CI->session->userdata('vauth_vigid');
    }
    
    /**
     * Get user email from vigattin members database
     * 
     * @return string User email.
     */
    public function get_email() {
        return $this->CI->session->userdata('vauth_email');
    }
    
    /**
     * Get user first name from vigattin members database
     * 
     * @return string User first name.
     */
    public function get_first_name() {
        return $this->CI->session->userdata('vauth_first_name');
    }
    
    /**
     * Get user last name from vigattin members database
     * 
     * @return string User lastname.
     */
    public function get_last_name() {
        return $this->CI->session->userdata('vauth_last_name');
    }
    
    /**
     * Get user profile photo link from vigattin members database
     * 
     * @return string User profile photo URL.
     */
    public function get_profile_photo() {
        return $this->CI->session->userdata('vauth_profile_photo');
    }
    
    /**
     * Get user cover photo link from vigattin members database
     * 
     * @return string User cover photo URL.
     */
    public function get_cover_photo() {
        return $this->CI->session->userdata('vauth_cover_photo');
    }

    // ====================== FACEBOOK ========================

    /**
     * Get facebook access token, can be use to check if currently connected to facebook. You can connect using fb_request_token method.
     * @return string
     */
    public function fb_get_token() {
        return $this->CI->session->userdata('facebook_access_token');
    }

    /**
     * @param string $permissions requested permission ex. user_likes or friends_photos, read_stream, publish_stream etc.
     * @return string url to connect to vigattin fb connect
     */
    public function fb_request_token($permissions = '') {
        return $this->AUTH_DOMAIN_FBCONNECT.'?permissions='.urlencode($permissions);
    }

    /**
     * Get facebook profile picture
     * @param string $size can be square, small, normal or large
     * @return string image url
     */
    public function fb_get_picture($size = 'normal' /* small, normal or large */) {
        return 'http://graph.facebook.com/'.$this->get_id().'/picture?type='.$size;
    }

    /**
     * Get album list from facebook
     * @param string $uid facebook user id
     * @param int $start offset of result list
     * @param int $limit result limit
     * @return array
     */
    public function fb_get_album_list($uid = '', $start = 0, $limit = 30) {
        /*
        RETURN
        Array
        (
            [status] => ok
            [total] => 2
            [albums] => Array
                (
                    [0] => Array
                        (
                            [url_large] => https://fbcdn-sphotos-d-a.akamaihd.net/hphotos-ak-ash4/s2048x2048/295606_256368404381532_5874251_n.jpg
                            [url_medium] => https://fbcdn-sphotos-d-a.akamaihd.net/hphotos-ak-ash4/295606_256368404381532_5874251_n.jpg
                            [url_xmedium] => https://fbcdn-sphotos-d-a.akamaihd.net/hphotos-ak-ash4/s320x320/295606_256368404381532_5874251_n.jpg
                            [url_small] => https://fbcdn-photos-d-a.akamaihd.net/hphotos-ak-ash4/295606_256368404381532_5874251_a.jpg
                            [url_xsmall] => https://fbcdn-photos-d-a.akamaihd.net/hphotos-ak-ash4/295606_256368404381532_5874251_s.jpg
                            [title] => Web Cam
                            [description] => 
                            [total] => 1
                            [aid] => 100000251232910_68767
                        )

                    [1] => Array
                        (
                            [url_large] => https://fbcdn-sphotos-h-a.akamaihd.net/hphotos-ak-prn2/s2048x2048/734893_534413109910392_1726481955_n.jpg
                            [url_medium] => https://fbcdn-sphotos-h-a.akamaihd.net/hphotos-ak-prn2/734893_534413109910392_1726481955_n.jpg
                            [url_xmedium] => https://fbcdn-sphotos-h-a.akamaihd.net/hphotos-ak-prn2/s320x320/734893_534413109910392_1726481955_n.jpg
                            [url_small] => https://fbcdn-photos-h-a.akamaihd.net/hphotos-ak-prn2/734893_534413109910392_1726481955_a.jpg
                            [url_xsmall] => https://fbcdn-photos-h-a.akamaihd.net/hphotos-ak-prn2/734893_534413109910392_1726481955_s.jpg
                            [title] => Profile Pictures
                            [description] => 
                            [total] => 5
                            [aid] => 100000251232910_27281
                        )
                )
        ) 
        */
        
        if($uid === '') $uid = $this->get_id();
        $result = array('status' => 'error', 'total' => 0, 'albums' => array());
        if(!$uid) {
            $result['status'] = 'invalid user id '.$uid;
            return $result;
        }
        $uid = strval($uid);
        $albums = array();
        $cover = array();
        $multiQuery = array (
            "all_aid" => "SELECT aid FROM album WHERE owner = $uid AND type IN ( 'profile','mobile','wall','normal')",
            "albums" => "SELECT aid, object_id, cover_object_id, name, description, photo_count FROM album WHERE owner = $uid AND type IN ( 'profile','mobile','wall','normal') ORDER BY object_id DESC LIMIT $start,$limit",
            "covers" => "SELECT images, aid, object_id FROM photo WHERE object_id IN (SELECT cover_object_id FROM #albums)",
            "user" => "SELECT id, name FROM profile WHERE id = $uid",
        );
        $params = array (
            'method' => 'fql.multiquery',    
            'queries' => $multiQuery,       
            'callback' => ''
        );
        $fb_result = $this->CI->facebook->api($params);
        if(!is_array($fb_result)) {
            $result['status'] = 'failed';
            return $result;
        }
        foreach($fb_result[3]['fql_result_set'] as $key => $value) {
            $cover[strval($value['aid'])] = $value;
        }

        foreach($fb_result[0]['fql_result_set'] as $key => $value) {
            $temp = array();
            if(isset($cover[$value['aid']])) {
                $temp['url_large']      = $cover[$value['aid']]['images'][0]['source']; // width max
                $temp['url_medium']     = $cover[$value['aid']]['images'][2]['source']; // width 720
                $temp['url_xmedium']    = $cover[$value['aid']]['images'][5]['source']; // width 320
                $temp['url_small']      = $cover[$value['aid']]['images'][6]['source']; // width 180
                $temp['url_xsmall']     = $cover[$value['aid']]['images'][7]['source']; // width 130
                $temp['title']          = $value['name'];
                $temp['description']    = $value['description'];
                $temp['total']          = $value['photo_count'];
                $temp['aid']            = $value['aid'];
            }
            else {
                $temp['url_large']      = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_medium']     = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_xmedium']    = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_small']      = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_xsmall']     = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['title']          = $value['name'];
                $temp['description']    = $value['description'];
                $temp['total']          = $value['photo_count'];
                $temp['aid']            = $value['aid'];
            }
            $albums[] = $temp;
        }
        $result['status'] = 'ok';
        $result['total'] = count($fb_result[1]['fql_result_set']);
        $result['albums'] = $albums;
        return $result;
    }

    /**
     * Get list of photo from facebook album
     * @param string $aid the album id
     * @param int $start offset of result list
     * @param int $limit result limit
     * @return array
     */
    public function fb_get_album_photos($aid = '', $start = 0, $limit = 30) {
        /*
        RETURN
        Array
        (
            [status] => ok
            [total] => 2
            [photos] => Array
                (
                    [0] => Array
                        (
                            [url_large] => https://fbcdn-sphotos-e-a.akamaihd.net/hphotos-ak-prn1/s2048x2048/41153_145976728754034_1475750_n.jpg
                            [url_medium] => https://fbcdn-sphotos-e-a.akamaihd.net/hphotos-ak-prn1/41153_145976728754034_1475750_n.jpg
                            [url_xmedium] => https://fbcdn-sphotos-e-a.akamaihd.net/hphotos-ak-prn1/s320x320/41153_145976728754034_1475750_n.jpg
                            [url_small] => https://fbcdn-photos-e-a.akamaihd.net/hphotos-ak-prn1/41153_145976728754034_1475750_a.jpg
                            [url_xsmall] => https://fbcdn-photos-e-a.akamaihd.net/hphotos-ak-prn1/41153_145976728754034_1475750_s.jpg
                            [caption] => 
                            [object_id] => 145976728754034
                            [aid] => 100000251232910_22586
                        )

                    [1] => Array
                        (
                            [url_large] => https://fbcdn-sphotos-c-a.akamaihd.net/hphotos-ak-ash3/s2048x2048/40650_145976698754037_658873_n.jpg
                            [url_medium] => https://fbcdn-sphotos-c-a.akamaihd.net/hphotos-ak-ash3/40650_145976698754037_658873_n.jpg
                            [url_xmedium] => https://fbcdn-sphotos-c-a.akamaihd.net/hphotos-ak-ash3/s320x320/40650_145976698754037_658873_n.jpg
                            [url_small] => https://fbcdn-photos-c-a.akamaihd.net/hphotos-ak-ash3/40650_145976698754037_658873_a.jpg
                            [url_xsmall] => https://fbcdn-photos-c-a.akamaihd.net/hphotos-ak-ash3/40650_145976698754037_658873_s.jpg
                            [caption] => 
                            [object_id] => 145976698754037
                            [aid] => 100000251232910_22586
                        )
                )
        )
        */
        
        $aid = strval($aid);
        $result = array('status' => 'error', 'total' => 0, 'photos' => array());
        if(!$aid) {
            $result['status'] = 'invalid album id '.$aid;
            return $result;
        }
        $photos = array();
        $multiQuery = array (
            "photos" => "SELECT object_id, images, caption, comment_info, aid FROM photo WHERE aid = '$aid' ORDER BY position DESC LIMIT $start,$limit",
            "photos_count" => "SELECT photo_count FROM album WHERE aid = '$aid'"
        );
        $params = array (
            'method' => 'fql.multiquery',       
            'queries' => $multiQuery,
            'callback' => ''
        );
        $fb_result = $this->CI->facebook->api($params);
        if(!is_array($fb_result)) {
            $result['status'] = 'failed';
            return $result;
        }
        foreach($fb_result[0]['fql_result_set'] as $value) {
            $temp = array();
            if(isset($value['images'])) {
                $temp['url_large']      = $value['images'][0]['source']; // width max
                $temp['url_medium']     = $value['images'][2]['source']; // width 720
                $temp['url_xmedium']    = $value['images'][5]['source']; // width 320
                $temp['url_small']      = $value['images'][6]['source']; // width 180
                $temp['url_xsmall']     = $value['images'][7]['source']; // width 130
                $temp['caption']        = $value['caption'];
                $temp['object_id']      = $value['object_id'];
                $temp['aid']            = $value['aid'];
            }
            else {
                $temp['url_large']      = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_medium']     = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_xmedium']    = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_small']      = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['url_xsmall']     = 'http://www.facebook.com//images/photos/empty-album.png';
                $temp['caption']        = $value['caption'];
                $temp['object_id']      = $value['object_id'];
                $temp['aid']            = $value['aid'];
            }
            $photos[] = $temp;
        }
        $result['status'] = 'ok';
        $result['total'] = $fb_result[1]['fql_result_set'][0]['photo_count'];
        $result['photos'] = $photos;
        return $result;
    }

    /**
     * Check if facebook page is already liked
     * @param $page_id fb page id in number string format
     * @return bool true if liked false otherwise
     */
    public function fb_is_page_like($page_id) {
        try {
            $fb_result = $this->CI->facebook->api('/v2.0/me/likes/'.urlencode($page_id));
        } catch(FacebookApiException $e) {
            $fb_result = array('error' => $e->getMessage());
        }
        if($fb_result['error']) return false;
        if(count($fb_result['data'])) return true;
        return false;
    }

    /**
     * @return bool true if have permission to read likes otherwise false
     */
    public function fb_has_like_permission() {
        try {
            $fb_result = $this->CI->facebook->api('/v2.0/me/permissions/user_likes');
        } catch(FacebookApiException $e) {
            $fb_result = array('error' => $e->getMessage());
        }
        if(isset($fb_result['error'])) return false;
        if(count($fb_result['data']) && ($fb_result['data'][0]['status'] == 'granted')) return true;
        return false;
    }

    /**
     * List page account handle by this user
     * @param int $offset
     * @param int $limit
     * @return AccountResult[]
     * @throws Exception
     */
    public function fb_get_accounts($offset = 0, $limit = 30) {
        $result = $this->CI->facebook->api('/me/accounts', 'GET', array(
            'offset' => intval($offset),
            'limit' => intval($limit))
        );
        if(isset($result['error'])) throw new Exception($result['error']['message']);
        $objectArray = array();
        if(isset($result['data'])) {
            foreach($result['data'] as $page) {
                $object = new AccountResult($page['id'], $page['access_token'], $this->CI->facebook);
                $object->name = $page['name'];
                $object->category = $page['category'];
                $object->permission = $page['perms'];
                $objectArray[] = $object;
            }
        }
        return $objectArray;
    }

    // ================= Vigattin API tools =====================

    public function get_public_photos($uid = '', $start = 0, $limit = 30) {
        /*
        RETURN
        Array
        (
            [status] => ok
            [total] => 19
            [photos] => Array
                (
                    [0] => Array
                        (
                            [pid] => 81448
                            [url_large] => http://image.vigattin.com/box/normal/81/447_18261932442090028117.jpg
                            [url_medium] => http://image.vigattin.com/box/medium/81/447_18261932442090028117.jpg
                            [url_xmedium] => http://image.vigattin.com/box/cover/81/447_18261932442090028117.jpg
                            [url_small] => http://image.vigattin.com/box/small/81/447_18261932442090028117.jpg
                            [url_square] => http://image.vigattin.com/box/thumb/81/447_18261932442090028117.jpg
                            [title] =>
                            [description] =>
                        )

                    [1] => Array
                        (
                            [pid] => 81447
                            [url_large] => http://image.vigattin.com/box/normal/81/446_1955015526693422459.jpg
                            [url_medium] => http://image.vigattin.com/box/medium/81/446_1955015526693422459.jpg
                            [url_xmedium] => http://image.vigattin.com/box/cover/81/446_1955015526693422459.jpg
                            [url_small] => http://image.vigattin.com/box/small/81/446_1955015526693422459.jpg
                            [url_square] => http://image.vigattin.com/box/thumb/81/446_1955015526693422459.jpg
                            [title] =>
                            [description] =>
                        )
                )
        )
        */

        if($uid === '') $uid = $this->get_id();
        $result = array('status' => '', 'total' => 0, 'photos' => array());
        if(!$uid) {
            $result['status'] = 'invalid user id '.$uid;
            return $result;
        }
        $fetched_photos = $this->api_call(array(
            'mode'  => self::REQUEST_MODE_PUBLIC_PHOTOS,
            'uid'   => $uid,
            'start' => $start,
            'limit' => $limit,
            'desc'  => true
        ));
        if(!$fetched_photos) {
            $result['status'] = 'failed';
            return $result;
        }
        $fetched_photos = json_decode($fetched_photos, true);
        $result['status'] = $fetched_photos['status'];
        $result['total'] = $fetched_photos['data']['total'];
        $photos = (isset($fetched_photos['data']['data']) && is_array($fetched_photos['data']['data'])) ? $fetched_photos['data']['data'] : array();
        foreach($photos as $photo) {
            $result['photos'][] = array(
                'pid' => $photo['id'],
                'url_large' => self::IMAGE_SERVER.'/box/normal/'.$photo['image_dir'].'.jpg',
                'url_medium' => self::IMAGE_SERVER.'/box/medium/'.$photo['image_dir'].'.jpg',
                'url_xmedium' => self::IMAGE_SERVER.'/box/cover/'.$photo['image_dir'].'.jpg',
                'url_small' => self::IMAGE_SERVER.'/box/small/'.$photo['image_dir'].'.jpg',
                'url_square' => self::IMAGE_SERVER.'/box/thumb/'.$photo['image_dir'].'.jpg',
                'title' => $photo['image_title'],
                'description' => $photo['note']
            );
        }
        return $result;
    }

    public function save_photo_to_public($image_link, $fb_object_id = '', $title = '', $description = '') {
        /*
        RETURN
        Array
        (
            [status] => ok
            [photo] => Array
                (
                    [image_id] => 81447
                    [url_large] => http://image.vigattin.com/box/normal/81/446_1955015526693422459.jpg
                    [url_medium] => http://image.vigattin.com/box/medium/81/446_1955015526693422459.jpg
                    [url_xmedium] => http://image.vigattin.com/box/cover/81/446_1955015526693422459.jpg
                    [url_small] => http://image.vigattin.com/box/small/81/446_1955015526693422459.jpg
                    [url_square] => http://image.vigattin.com/box/thumb/81/446_1955015526693422459.jpg
                )

        )
        */

        $result = array('status' => '', 'photo' => array());
        $request = array(
            'mode'  => self::REQUEST_MODE_UPLOAD_PHOTO,
            'url'   => $image_link,
            'uid'   => $this->get_id(),
            'oid'   => $fb_object_id,
            'title' => $title,
            'name'  => $this->get_name(),
            'email' => $this->get_email(),
            'description'  => $description
        );
        $result_data = json_decode($this->api_call($request), true);
        $result['status'] = $result_data['status'];
        if($result['status'] == 'ok') {
            $result['photo'] = array(
                'image_id' => $result_data['data']['image_id'],
                'url_large' => self::IMAGE_SERVER.'/box/normal/'.$result_data['data']['link'],
                'url_medium' => self::IMAGE_SERVER.'/box/medium/'.$result_data['data']['link'],
                'url_xmedium' => self::IMAGE_SERVER.'/box/cover/'.$result_data['data']['link'],
                'url_small' => self::IMAGE_SERVER.'/box/small/'.$result_data['data']['link'],
                'url_square' => self::IMAGE_SERVER.'/box/thumb/'.$result_data['data']['link']
            );
        }
        return $result;
    }

    public function delete_public_photo($pid) {
        /*
        RETURN
        Array
        (
            [status] => ok
            [data] => Array
                (
                    [status] => success
                )

        )
        */

        $request = array(
            'mode'  => self::REQUEST_MODE_DELETE_PHOTO,
            'pid'   => $pid,
            'uid'   => $this->get_id()
        );
        $result_data = $this->api_call($request);
        return json_decode($result_data, true);
    }

    /**
     * Make api call to vigattin api server
     * @param array $request
     * @return mixed
     */
    public function api_call($request = array()) {
        /*  sample request
            $request = array(
            'mode'  => self::REQUEST_MODE_UPLOAD_PHOTO,
            'url'   => $image_link,
            'uid'   => $this->get_id(),
            'oid'   => $fb_object_id,
            'title' => $title,
            'name'  => $this->get_name(),
            'email' => $this->get_email(),
            'description'  => $description
         */

        $request['vapi_request_expire'] = time()+$this->REQUEST_EXPIRE;
        $request = base64_encode(serialize($request));
        $hash = sha1($request.$this->KEY);
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL             => $this->API_LINK,
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POSTFIELDS      => array(
                'request'           => $request, 
                'hash'              => $hash
            ),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => "vauth client",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_SSL_VERIFYPEER => $this->SSL_CHECK_CERT
        );
        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        //echo curl_error($ch);
        curl_close($ch);
        return $result;
    }

    public function get_current_url() {
        $pageURL = 'http';
        if(isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on")) {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }
    
    /**
     * Read vigattin.com members database table
     * 
     * @param int $start_id id + 1 where the start of reading will begin ex. if $start_id = 10 the reading will start from 11, 12, 13 and soon
     * @param int $limit tha max output number of row 
     * @return array result of the request
     */
    
    public function db_read_all_members($start_id = 0, $limit = 30) {
        $request = array(
            'mode' => self::REQUEST_MODE_DB_READ_ALL_MEMBERS,
            'start_id' => $start_id,
            'limit' => $limit
        );
        $result_data = $this->api_call($request);
        return json_decode($result_data, true);
        
        /*
        Sample Return:
            Array
            (
                [status] => ok
                [result] => Array
                    (
                        [0] => Array
                            (
                                [id] => 0000000011
                                [email] => ellainegomez29@yahoo.com
                                [password] => cec41dbfb58bf5ffdb1cc24ebd285f5f30b8a41e
                                [first_name] => Ellaine
                                [last_name] => Gomez
                                [birthday] => 315532800
                                [gender] => 0
                                [country] => Anonymous
                                [verified] => 1
                                [type] => 1
                                [fbid] => 100001774838846
                                [name] => Ellaine Gomez
                                [time] => 1368753612
                                [profile_photo] => 
                                [cover_photo] => 
                                [offset_y] => 0
                                [is_local] => 0
                                [new_user] => 1
                                [version] => 0
                            )

                        [1] => Array
                            (
                                [id] => 0000000012
                                [email] => lovettejam@yahoo.com
                                [password] => 090cd6f86b11ec238ef6830a4f4ac3ed0a5556e0
                                [first_name] => Lovette
                                [last_name] => Jam
                                [birthday] => 478310400
                                [gender] => 0
                                [country] => Anonymous
                                [verified] => 1
                                [type] => 0
                                [fbid] => 1132617639
                                [name] => Lovette Jam
                                [time] => 0
                                [profile_photo] => 
                                [cover_photo] => 
                                [offset_y] => 0
                                [is_local] => 0
                                [new_user] => 1
                                [version] => 0
                            )

                        [2] => Array
                            (
                                [id] => 0000000013
                                [email] => temp_email_100003515029676@vig.com
                                [password] => cbaf9974bec6ae589bf36734b11994707acda927
                                [first_name] => Ian
                                [last_name] => Freelancemasseur
                                [birthday] => 315532800
                                [gender] => 1
                                [country] => Anonymous
                                [verified] => 0
                                [type] => 0
                                [fbid] => 100003515029676
                                [name] => Ian Freelancemasseur
                                [time] => 0
                                [profile_photo] => 
                                [cover_photo] => 
                                [offset_y] => 0
                                [is_local] => 0
                                [new_user] => 1
                                [version] => 0
                            )

                    )

                [request] => Array
                    (
                        [mode] => 5
                        [start_id] => 10
                        [limit] => 3
                        [vapi_request_expire] => 1379407068
                    )
        */
    }
    
    /**
     * Update vigattin.com members database table
     * 
     * @param string $id_or_email the id or email of member from vigattin members table
     * @param array $field_array array of key value pair to be updated ex. update member first_name and last_name: $field_array = array('first_name' => 'vigattin', 'last_name' => 'inc.');
     * @return array result of the request
     */
    
    public function db_update_member($id_or_email, $field_array = array()) {
        $request = array(
            'mode' => self::REQUEST_MODE_DB_UPDATE_MEMBERS,
            'id_or_email' => $id_or_email,
            'field_array' => $field_array
        );
        $result_data = $this->api_call($request);
        return json_decode($result_data, true);
        
        /*
        Sample Return:
            Array
            (
                [status] => ok
                [result] => update success
                [request] => Array
                    (
                        [mode] => 7
                        [id_or_email] => resty_rizal2@live.com
                        [field_array] => Array
                            (
                                [first_name] => Test
                                [last_name] => User
                            )

                        [vapi_request_expire] => 1379406901
                    )

            )
        */
    }
    
    /**
     * Create new vigattin.com member
     * 
     * @param string $email member email (must be a valid email)
     * @param string $password member password
     * @param string $first_name member first name
     * @param string $last_name member last name
     * @param string $gender member gender, posible value are male or female
     * @param ing $birthday member birthday in epoch time (optional)
     * @param int $verified Force the email to set as verified
     * @param int $version Add version to the inserted account for use in deleting or updating if problem occurs when inserting multiple account
     * @return array result of the request
     */
    
    public function db_create_member($email, $password, $first_name, $last_name, $gender, $birthday = '', $verified = 0, $version = 0, $username = '') {
        $request = array(
            'mode' => self::REQUEST_MODE_DB_CREATE_MEMBERS,
            'email' => $email,
            'password' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'birthday' => $birthday,
            'verified' => $verified,
            'version' => $version,
            'username' => $username
        );
        $result_data = $this->api_call($request);
        return json_decode($result_data, true);
        
        /*
        Sample Return:
            Array
            (
                [status] => ok
                [result] => Array
                    (
                        [id] => 7843
                        [error] => 
                    )

                [request] => Array
                    (
                        [mode] => 6
                        [email] => resty_rizal2@live.com
                        [password] => 12345
                        [first_name] => resty
                        [last_name] => rizal
                        [gender] => male
                        [birthday] => 
                        [vapi_request_expire] => 1379406732
                    )

            )
        */
        
    }
    
    /**
     * since v0.9.8.0 config are now seperated from main code
     * @return array vauth config from config.php
     */
    
    public function get_config() {
        $config_dir = __DIR__.'/config.php';
        if(is_file($config_dir)) {
            return include $config_dir;
        }
        else return array();
    }

    // Coupon Code

    public function get_promo() {
        return $this->CI->session->userdata('vauth_promo');
    }

    /**
     * Check coupon status
     * @param string $code coupon code
     * @param string $item item code
     * @param float $price price of the item
     * @return array coupon status
     * <br>
     * <pre>
     *  Array(
    [status] => ok
    [result] => Array(
    [error] =>
    [old_price] => 500
    [new_price] => 0
    [save_type_code] => 3
    [save_type_description] => get item for free
    [code] => 25659SVB
    [save] => 10
    [item] => dfsdfs
    [usable] => 5
    [expire] => 1380211200
    )
    [request] => Array(
    [mode] => 9
    [code] => 25659SVB
    [item] => dfsdfs
    [price] => 500
    [vapi_request_expire] => 1380173902
    )
    )
     * </pre>
     */
    public function coupon_check($code, $item, $price) {
        $request = array(
            'mode' => self::REQUEST_MODE_COUPON_CHECK,
            'code' => $code,
            'item' => $item,
            'price' => $price
        );
        $result_data = $this->api_call($request);
        return json_decode($result_data, true);
    }

    /**
     * Redeem the coupon
     * @param string $code coupon code
     * @param string $item item code
     * @param float $price price of the item
     * @return array coupon status
     * <br>
     * <pre>
     *  Array(
    [status] => ok
    [result] => Array(
    [error] =>
    [old_price] => 500
    [new_price] => 0
    [save_type_code] => 3
    [save_type_description] => get item for free
    [code] => 25659SVB
    [save] => 10
    [item] => dfsdfs
    [usable] => 5
    [expire] => 1380211200
    )
    [request] => Array(
    [mode] => 9
    [code] => 25659SVB
    [item] => dfsdfs
    [price] => 500
    [vapi_request_expire] => 1380173902
    )
    )
     * </pre>
     */
    public function coupon_redeem($code, $item, $price) {
        $request = array(
            'mode' => self::REQUEST_MODE_COUPON_REDEEM,
            'code' => $code,
            'item' => $item,
            'price' => $price
        );
        $result_data = $this->api_call($request);
        return json_decode($result_data, true);
    }

    // Core method

    protected function parse_info() {
        $info = array();
        if(isset($_GET['info'])) {
            $hash = isset($_GET['hash']) ? $_GET['hash'] : '';
            if($hash == sha1($_GET['info'].$this->KEY)) {
                $info = unserialize(urldecode(base64_decode($_GET['info'])));
            }
        }
        return $info;
    }

    protected function save_to_session($info) {
        if(is_array($info)) {
            return $this->CI->session->set_userdata($info);
        }
        else return false;
    }

    // old api
    public function login($redirect = '', $secure = FALSE) {
        $url = $this->get_login_url($redirect, $secure);
        header('Location: '.$url);
        exit();
    }
    public function logout($redirect = '', $secure = FALSE) {
        $url = $this->get_logout_url($redirect, $secure);
        header('Location: '.$url);
        exit();
    }
    public function clear_data() {
        $this->CI->session->sess_destroy();
    }
    public function get_token() {
        return $this->CI->session->userdata('facebook_access_token');
    }
    public function get_type() {
        return $this->CI->session->userdata('vauth_type');
    }
    public function get_username() {
        return $this->CI->session->userdata('vauth_username');
    }
    public function get_gender() {
        return $this->CI->session->userdata('vauth_gender');
    }
    public function get_name() {
        return $this->CI->session->userdata('vauth_name');
    }
    public function get_offset_y() {
        return $this->CI->session->userdata('vauth_offset_y');
    }
    public function get_birtday() {
        return $this->CI->session->userdata('vauth_birthday');
    }
    public function get_verified() {
        return $this->CI->session->userdata('vauth_verified');
    }
    public function get_picture($size = 'normal' /* small, normal or large */) {
        return 'http://graph.facebook.com/'.$this->get_id().'/picture?type='.$size;
    }
}
