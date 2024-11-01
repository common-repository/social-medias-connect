<?php
class Taobao extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $is_custom;
	public $default_authorize_url='https://oauth.taobao.com/authorize';
	public $authorize_url='http://smcstation.sinaapp.com?smc_oauth=taobao';
	public $accesstoken_url='https://oauth.taobao.com/token';
	public $server_url='http://gw.api.taobao.com/router/rest';
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
		//$params['scope']='item,promotion,usergrade';
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
			return array('access_token'=>$response['access_token'],'refesh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error_description'].'）');	
		}
	}
	function verify_credentials(){
		$params=$this->default_params();
		$params['method']='taobao.user.buyer.get';
		$params['fields']='user_id,nick,avatar';
		$params['sign']=$this->sig($params);
		$resp=$this->oAuthRequest($this->server_url,'GET',$params);
		if($resp['error_response']){
			throw new smcException($resp['error_response']['msg'].'（'.$resp['error_response']['code'].'）');
		}
		$resp=$resp['user_buyer_get_response']['user'];
		$user_login=$resp['nick']?$resp['nick']:$resp['user_id'];
		$r=array(
			'profile_image_url'=>$resp['avatar'],
			'user_login'=>$user_login,
			'uid'=>$resp['user_id'],
			'display_name'=>$resp['nick'],
			'url'=>'',
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>0,
			'email'=>$resp['user_id'].'@'.'taobao.com',
			'real_email'=>'',
			'weibo_slug'=>'taobao'
		);
		return $r;
	}
	function default_params(){
		$params=array();
		$params['app_key']=$this->client_id;
		$params['session']=$this->access_token;
		$params['v']='2.0';
		$params['timestamp']=time();
		$params['format']='json';
		$params['sign_method']='md5';
		return $params;
	}
	function sig($params){
		ksort($params);
		$sig_string='';
		foreach($params as $key=>$v){
			$sig_string.=$key.$v;
		}
		$sig_string=$this->client_secret.$sig_string.$this->client_secret;
		$sig_string=strtoupper(md5($sig_string));
		return $sig_string;
	}
}