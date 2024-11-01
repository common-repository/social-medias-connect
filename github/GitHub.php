<?php
class GitHub extends smcHttp{
	public $client_id;
	public $json=false;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://github.com/login/oauth/authorize';
	public $accesstoken_url='https://github.com/login/oauth/access_token';
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
		//$params['response_type']='code';
		$params['scope']='repo,user';
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		//$params['grant_type']='authorization_code';
		$params['code']=$code;
		$params['redirect_uri'] = $callback_url;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		$resp=$this->parse_string($response);
		if($resp['access_token']){
			return array('access_token'=>$resp['access_token'],'refresh_token'=>$resp['refresh_token'],'expires_in'=>$resp['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$resp['error'].'）');	
		}
	}
	function parse_string($string=''){
		if(is_array($string)||is_object($string))return $string;
		parse_str($string,$string);
		return $string;
	}
	function verify_credentials(){
		$this->json=true;
		$params=array();
		$params['access_token']=$this->access_token;
		$resp=$this->oAuthRequest('https://api.github.com/user','GET',$params);
		if($resp['message']){
			throw new smcException($resp['message']);
		}
		$user_login=$resp['login']?$resp['login']:$resp['id'];
		$r=array(
			'profile_image_url'=>$resp['avatar_url'],
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['login'],
			'url'=>$resp['html_url'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>'',
			'email'=>$user_login.'@'.'github.com',
			'weibo_slug'=>'github'
		);
		return $r;
	}
}