<?php
class Netease extends smcHttp{
	public $oauth_1=true;
	public $client_id;
	public $json=false;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $requestoken_url='http://reg.163.com/services/oauth1/request_token';
	public $authorize_url='http://reg.163.com/oauth1/UserAuth.jsp';
	public $accesstoken_url='http://reg.163.com/services/oauth1/access_token';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
	}
	function getAuthorizeURL($callback_url=''){
		$params = array();
		$resp=$this->oAuthRequest($this->requestoken_url,'GET',$params);
		if(is_string($resp))parse_str($resp,$resp);
		if(!is_array($resp)||!$resp['oauth_token'])throw new smcException('Oauth Token获取失败');
		setcookie('smcOauth_secret_'.COOKIEHASH,$resp['oauth_token_secret'],0,COOKIEPATH,COOKIE_DOMAIN);
		$params['oauth_token']=$resp['oauth_token'];
		$params['oauth_callback']=$callback_url;
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$this->refresh_token=$_COOKIE['smcOauth_secret_'.COOKIEHASH];
		$params = array();
		$resp=$this->oAuthRequest($this->accesstoken_url, 'GET', $params);
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
		$resp=$this->oAuthRequest('http://reg.163.com/services/oauth1/get_user_info','GET',$params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['error']){
			throw new smcException($resp['error']);
		}
		$user_login=$resp['username']?$resp['username']:$resp['userId'];
		$r=array(
			'profile_image_url'=>$resp['profile_image_url_large'],
			'user_login'=>$user_login,
			'uid'=>$resp['userId'],
			'display_name'=>$resp['username'],
			'url'=>'',
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>0,
			'email'=>$resp['userid'].'@'.'net163.com',
			'weibo_slug'=>'netease'
		);
		return $r;
	}
}