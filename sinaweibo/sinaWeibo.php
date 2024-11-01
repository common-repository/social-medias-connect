<?php
class sinaWeibo extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://api.weibo.com/oauth2/authorize';
	public $accesstoken_url='https://api.weibo.com/oauth2/access_token';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
	}
	function getAuthorizeURL($callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['redirect_uri']=$callback_url;
		$params['response_type']='code';
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='authorization_code';
		$params['code']=$code;
		$params['redirect_uri'] = $callback_url;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
	}
	function verify_credentials(){
		$params=array();
		$params['access_token']=$this->access_token;
		$resp=$this->oAuthRequest('https://api.weibo.com/2/account/get_uid.json','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error'].'（'.$resp['error_code'].'）');
		}
		$params['uid']=$resp['uid'];
		$resp=$this->oAuthRequest('https://api.weibo.com/2/users/show.json','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error'].'（'.$resp['error_code'].'）');
		}
		$user_login=$resp['domain']?$resp['domain']:$resp['idstr'];
		$r=array(
			'profile_image_url'=>$resp['profile_image_url'],
			'user_login'=>$user_login,
			'uid'=>$resp['idstr'],
			'display_name'=>$resp['screen_name'],
			'url'=>'http://weibo.com/'.$resp['profile_url'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['description'],
			'statuses_count'=>$resp['statuses_count'],
			'email'=>$user_login.'@'.'weibo.com',
			'weibo_slug'=>'sinaweibo'
		);
		return $r;
	}
	function refresh_access_token(){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='refresh_token';
		$params['refresh_token']=$this->refresh_token;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']=smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,138,true);
		}else{
			$text=$weibo_data;
		}
		$params=array(); $params['access_token']=$this->access_token;
		$params['status']=$text;
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['pic']=$weibo_data['pic'];
			$resp=$this->oAuthRequest('https://upload.api.weibo.com/2/statuses/upload.json','POST',$params,true);
			if($resp['error']){
				return $this->publish_post($text);
			}
		}else $resp=$this->oAuthRequest('https://api.weibo.com/2/statuses/update.json','POST',$params);
		if($resp['error']){
			return false;
		}else{
			return $resp['id'];
		}
	}
	function add_follow($uid){
		$params=array(); 
		$params['access_token']=$this->access_token;
		$params['uid']=$uid;
		$resp=$this->oAuthRequest('https://api.weibo.com/2/friendships/create.json','POST',$params);
		if($resp['error']){
			return false;
		}else{
			return true;
		}
	}
}
