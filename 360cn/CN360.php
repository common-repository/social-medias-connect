<?php
class CN360 extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://openapi.360.cn/oauth2/authorize';
	public $accesstoken_url='https://openapi.360.cn/oauth2/access_token';
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
		$response=$this->oAuthRequest($this->accesstoken_url, 'GET', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败');	
		}
	}
	function verify_credentials(){
		$params=array();
		$params['access_token']=$this->access_token;
		$resp=$this->oAuthRequest('https://openapi.360.cn/user/me.json','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error'].'（'.$resp['error_code'].'）');
		}
		$user_login=$resp['name']?$resp['name']:$resp['id'];
		$r=array(
			'profile_image_url'=>$resp['avatar'],
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['name'],
			'url'=>'',
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>0,
			'email'=>$resp['id'].'@360.cn',
			'weibo_slug'=>'360cn'
		);
		return $r;
	}
	function refresh_access_token(){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='refresh_token';
		$params['refresh_token']=$this->refresh_token;
		$response=$this->oAuthRequest($this->accesstoken_url, 'GET', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败');	
		}
	}
}