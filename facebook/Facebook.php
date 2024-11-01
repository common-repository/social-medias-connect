<?php
class Facebook extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $is_custom;
	public $default_authorize_url='https://www.facebook.com/dialog/oauth';
	public $authorize_url='http://smcstation.sinaapp.com?smc_oauth=facebook';
	public $accesstoken_url='https://graph.facebook.com/oauth/access_token';
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
		$params['scope']='user_status email offline_access publish_stream user_website user_about_me';
		return ($this->is_custom?$this->default_authorize_url."?":$this->authorize_url."&").http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$this->json=false;
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['code']=$code;
		$params['redirect_uri']=$this->is_custom?get_bloginfo('url').'/':'http://smcstation.sinaapp.com/';
		$resp=$clone_resp=$this->oAuthRequest($this->accesstoken_url, 'GET', $params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['access_token']){
			return array('access_token'=>$resp['access_token'],'refresh_token'=>'','expires_in'=>$resp['expires'],'access_time'=>time());
		}else{
			$resp=json_decode($clone_resp,true);
			return array('error'=>'token获取失败（'.$resp['error']['message'].'）');	
		}
	}
	function verify_credentials(){
		$this->json=true;
		$params=array();
		$params['access_token']=$this->access_token;
		$resp=$this->oAuthRequest('https://graph.facebook.com/me','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error']['message'].'（'.$resp['error']['type'].'）');
		}
		$user_login=$resp['username']?$resp['username']:$resp['id'];
		$r=array(
			'profile_image_url'=>'https://graph.facebook.com/'.$resp['id'].'/picture',
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['name'],
			'url'=>$resp['link'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['bio'],
			'statuses_count'=>0,
			'email'=>$resp['id'].'@'.'facebook.com',
			'real_email'=>$resp['email'],
			'weibo_slug'=>'facebook'
		);
		return $r;
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']='';
			$text=smcHttp::format_post_data($weibo_data,200,true);
		}else{
			$text=$weibo_data;
		}
		$params=array();
		$params['access_token']=$this->access_token;
		$params['message']=$text;
		$resp=$this->oAuthRequest('https://graph.facebook.com/me/feed','POST',$params);
		if($resp['error']){
			return false;
		}else{
			return $resp['id'];
		}
	}
}