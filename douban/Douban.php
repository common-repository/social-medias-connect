<?php
class Douban extends smcHttp{
	public $oauth_1=true;
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
    public $refresh_token;
    public $is_custom;
	public $default_authorize_url='https://www.douban.com/service/auth2/auth';
	public $authorize_url='http://smcstation.sinaapp.com?smc_oauth=douban';
	public $accesstoken_url='https://www.douban.com/service/auth2/token';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
	}
	function getAuthorizeURL($callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['redirect_uri']=$this->is_custom?get_bloginfo('url'):$callback_url;
        $params['response_type']='code';
        $params['response_type']='code';
		return ($this->is_custom?$this->default_authorize_url."?":$this->authorize_url."&").http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='authorization_code';
		$params['code']=$code;
		$params['redirect_uri'] = $this->is_custom?get_bloginfo('url'):'http://smcstation.sinaapp.com';
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
    }
    function refresh_access_token(){
		$params=array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='refresh_token';
		$params['refresh_token']=$this->refresh_token;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'scope'=>$response['scope'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
	}
	function verify_credentials(){
		$resp=$this->oAuthRequest('https://api.douban.com/v2/user/~me','GET',null,false,array('Authorization'=>"Bearer {$this->access_token}"));
        $name=$resp['name'];
        $uid=$resp['uid'];
		$user_login=$uid?$uid:$resp['loc_id'];
		$r=array(
			'profile_image_url'=>$resp['avatar'],
			'user_login'=>$user_login,
			'uid'=>$uid,
			'display_name'=>$name,
			'url'=>$resp['alt'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['desc'],
			'statuses_count'=>0,
			'email'=>$uid.'@'.'douban.com',
			'weibo_slug'=>'douban'
		);
		return $r;
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']=smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,138,false);
		}else{
			$text=$weibo_data;
        }
        $params=array('source'=>$this->client_id);
        $params['text']=$text;
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['image']=$weibo_data['pic'];
			$resp=$this->oAuthRequest('https://api.douban.com/shuo/v2/statuses/','POST',$params,true,array('Authorization'=>"Bearer {$this->access_token}"));
			if(!is_array($resp) || !$resp['id']){
				return $this->publish_post($text);
			}
        }else $resp=$this->oAuthRequest('https://api.douban.com/shuo/v2/statuses/','POST',$params,false,array('Authorization'=>"Bearer {$this->access_token}"));
		if(is_array($resp) && $resp['id']){
			return $resp['id'];
		}else{
			return false;
		}
	}
}
