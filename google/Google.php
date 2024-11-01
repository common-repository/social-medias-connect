<?php
class Google extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $is_custom;
	public $default_authorize_url='https://accounts.google.com/o/oauth2/auth';
	public $authorize_url='http://smcstation.sinaapp.com?smc_oauth=google';
	public $accesstoken_url='https://accounts.google.com/o/oauth2/token';
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
		$params['access_type']='offline';
		$params['response_type']='code';
		$params['scope']='https://www.googleapis.com/auth/plus.me https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email';
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
	function verify_credentials(){
		$params=array();
		$params['access_token']=$this->access_token;
		$resp=$this->oAuthRequest('https://www.googleapis.com/oauth2/v1/userinfo','GET',$params);//wp_die(print_r($resp));
		if($resp['error']){
			throw new smcException($resp['error']['message'].'（'.$resp['error']['code'].'）');
		}
		$resp1=$this->oAuthRequest('https://www.googleapis.com/plus/v1/people/me','GET',$params);
		if(!$resp['error']){
			$resp=array_merge($resp,$resp1);
		}
		$user_login=$resp['given_name']?$resp['given_name']:$resp['id'];
		$r=array(
			'profile_image_url'=>@$resp['image']['url'],
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['displayName'],
			'url'=>$resp['link'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['aboutMe'],
			'statuses_count'=>0,
			'email'=>$resp['id'].'@'.'google.com',
			'real_email'=>$resp['email'],
			'weibo_slug'=>'google'
		);
		return $r;
	}
}