<?php
class MSNLive extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://login.live.com/oauth20_authorize.srf';
	public $accesstoken_url='https://login.live.com/oauth20_token.srf';
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
		$params['scope']='wl.offline_access';
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['code']=$code;
		$params['grant_type']='authorization_code';
		$params['redirect_uri'] = $callback_url;
		$resp=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($resp['access_token']){
			return array('access_token'=>$resp['access_token'],'refresh_token'=>$resp['refresh_token'],'expires_in'=>$resp['expires'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$resp['error_description'].'）');	
		}
	}
	function verify_credentials(){
		$params=array();
		$params['access_token']=$this->access_token;
		$resp=$this->oAuthRequest('https://apis.live.net/v5.0/me','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error_description'].'（'.$resp['error'].'）');
		}
		$user_login=$resp['last_name']?$resp['last_name']:$resp['id'];
		$r=array(
			'profile_image_url'=>'',
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['name'],
			'url'=>'',
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>0,
			'email'=>$resp['id'].'@'.'live.com',
			'weibo_slug'=>'msnlive'
		);
		return $r;
	}
}