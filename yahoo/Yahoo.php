<?php
class Yahoo extends smcHttp{
	public $oauth_1=true;
	public $client_id;
	public $json=false;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $requestoken_url='https://api.login.yahoo.com/oauth/v2/get_request_token';
	public $authorize_url='https://api.login.yahoo.com/oauth/v2/request_auth';
	public $accesstoken_url='https://api.login.yahoo.com/oauth/v2/get_token';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
	}
	function getAuthorizeURL($callback_url=''){
		$params = array();
		$params['oauth_callback']=$callback_url;
		$resp=$this->oAuthRequest($this->requestoken_url,'GET',$params);
		if(is_string($resp))parse_str($resp,$resp);
		if(!is_array($resp)||!$resp['oauth_token'])throw new smcException('Oauth Token获取失败');
		setcookie('smcOauth_secret_'.COOKIEHASH,$resp['oauth_token_secret'],0,COOKIEPATH,COOKIE_DOMAIN);
		$params['oauth_token']=$resp['oauth_token'];
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$this->refresh_token=$_COOKIE['smcOauth_secret_'.COOKIEHASH];
		$params = array();
		$params['oauth_verifier']=$_GET['oauth_verifier'];
		$resp=$this->oAuthRequest($this->accesstoken_url, 'GET', $params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['oauth_token']){
			setcookie('smcOauth_secret_'.COOKIEHASH,'',time()-1,COOKIEPATH,COOKIE_DOMAIN);
			return array('access_token'=>$resp['oauth_token'],'refresh_token'=>$resp['oauth_token_secret'],'expires_in'=>$resp['oauth_expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'access token获取失败！');	
		}
	}
	function verify_credentials(){
		$this->json=true;
		$params=array();
		$params['format']='json';
		$resp=$this->oAuthRequest('http://social.yahooapis.com/v1/me/guid','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error']['description']);
		}
		$guid=$resp['guid']['value'];
		$resp=$this->oAuthRequest('http://social.yahooapis.com/v1/user/'.$guid.'/profile','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error']['description']);
		}
		$resp=$resp['profile'];
		$user_login=$resp['nickname']?$resp['nickname']:$resp['guid'];
		$r=array(
			'profile_image_url'=>$resp['image']['imageUrl'],
			'user_login'=>$user_login,
			'uid'=>$resp['guid'],
			'display_name'=>$resp['nickname'],
			'url'=>$resp['profileUrl'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>0,
			'email'=>$resp['guid'].'@'.'profile.yahoo.com',
			'weibo_slug'=>'yahoo'
		);
		return $r;
	}
}