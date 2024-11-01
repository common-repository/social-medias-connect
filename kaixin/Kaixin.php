<?php
class Kaixin extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='http://api.kaixin001.com/oauth2/authorize';
	public $accesstoken_url='https://api.kaixin001.com/oauth2/access_token';
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
		$params['scope']='user_intro create_records';
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
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'scope'=>$response['scope'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
	}
	function verify_credentials(){
		$params=$this->default_params();
		$params['fields']='uid,name,logo120,intro';
		$resp=$this->oAuthRequest('https://api.kaixin001.com/users/me.json','GET',$params);;
		if($resp['error_code']){
			throw new smcException($resp['error'].'（'.$resp['error_code'].'）');
		}
		$user_login=$resp['name'];
		$r=array(
			'profile_image_url'=>$resp['logo120'],
			'user_login'=>$user_login,
			'uid'=>$resp['uid'],
			'display_name'=>$resp['name'],
			'url'=>'http://www.kaixin001.com/home/?uid='.$resp['uid'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['intro'],
			'statuses_count'=>0,
			'email'=>$resp['uid'].'@'.'kaixin.com',
			'weibo_slug'=>'kaixin'
		);
		return $r;
	}
	function default_params(){
		$params=array();
		$params['access_token']=$this->access_token;
		return $params;
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']='';//smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,140,true);
		}else{
			$text=$weibo_data;
		}
		$params=$this->default_params();
		$params['content']=$text;
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['picurl']=$weibo_data['pic'];
		}
		$resp=$this->oAuthRequest('https://api.kaixin001.com/records/add.json','POST',$params);
		if($resp['error_code']){
			return false;
		}else{
			return $resp['rid'];
		}
	}
}