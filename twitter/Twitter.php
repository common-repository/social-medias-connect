<?php
class Twitter extends smcHttp{
	public $oauth_1=true;
	public $client_id;
	public $json=false;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $requestoken_url='https://api.twitter.com/oauth/request_token';
	public $authorize_url='https://api.twitter.com/oauth/authorize';
	public $accesstoken_url='https://api.twitter.com/oauth/access_token';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
	}
	function getAuthorizeURL($callback_url=''){
		$params['oauth_callback']=$callback_url;
		$resp=$this->oAuthRequest($this->requestoken_url,'POST',$params);
		if(is_string($resp))parse_str($resp,$resp);
		if(!is_array($resp)||!$resp['oauth_token'])throw new smcException('Oauth Token获取失败');
		setcookie('smcOauth_secret_'.COOKIEHASH,$resp['oauth_token_secret'],0,COOKIEPATH,COOKIE_DOMAIN);
		$params['oauth_token']=$resp['oauth_token'];
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$this->refresh_token=$_COOKIE['smcOauth_secret_'.COOKIEHASH];
		$params = array('oauth_verifier'=>$_GET['oauth_verifier']);
		$resp=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['oauth_token']){
			setcookie('smcOauth_secret_'.COOKIEHASH,'',time()-1,COOKIEPATH,COOKIE_DOMAIN);
			return array('access_token'=>$resp['oauth_token'],'refresh_token'=>$resp['oauth_token_secret'],'expires_in'=>'','access_time'=>time());
		}else{
			return array('error'=>'access token获取失败！');	
		}
	}
	function verify_credentials(){
		$this->json=true;
		$params=array();
		$resp=$this->oAuthRequest('https://api.twitter.com/1.1/account/verify_credentials.json','GET',$params);
		if($resp['errors']){
			throw new smcException($resp['errors'][0]['message']);
		}
		$user_login=$resp['screen_name']?$resp['screen_name']:$resp['id'];
		$r=array(
			'profile_image_url'=>$resp['profile_image_url'],
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['name'],
			'url'=>$resp['url']?$resp['url']:'http://twitter.com/'.$user_login,
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['description'],
			'statuses_count'=>$resp['statuses_count'],
			'email'=>$user_login.'@'.'twitter.com',
			'weibo_slug'=>'twitter'
		);
		return $r;
	}
	function publish_post($weibo_data){
		$this->json=true;
		if(is_array($weibo_data)){
			$weibo_data['tags']=smcHttp::convtags($weibo_data['tags'],false);
			$text=smcHttp::format_post_data($weibo_data,138,false);
		}else{
			$text=$weibo_data;
		}
		$params=array();
		$params['status']=$text;
		$resp=$this->oAuthRequest('https://api.twitter.com/1.1/statuses/update.json','POST',$params);
		if($resp['id_str']){
			return $resp['id_str'];
		}else{
			return false;
		}
	}
}