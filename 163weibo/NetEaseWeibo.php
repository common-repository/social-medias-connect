<?php
class NetEaseWeibo extends smcHttp{
	public $client_id;
	public $json=true;
	public $is_custom;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $default_authorize_url='https://api.t.163.com/oauth2/authorize';
	public $authorize_url='http://smcstation.sinaapp.com?smc_oauth=163weibo';
	public $accesstoken_url='https://api.t.163.com/oauth2/access_token';
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
		$resp=$this->oAuthRequest('https://api.t.163.com/users/show.format.json','GET',$params);
		if($resp['error']){
			throw new smcException($resp['error'].'（'.$resp['error_code'].'）');
		}
		$user_login=$resp['screen_name']?$resp['screen_name']:$resp['id'];
		$r=array(
			'profile_image_url'=>$resp['profile_image_url'],
			'user_login'=>$user_login,
			'uid'=>$resp['id'],
			'display_name'=>$resp['screen_name'],
			'url'=>'http://t.163.com/'.$user_login,
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['description'],
			'statuses_count'=>$resp['statuses_count'],
			'email'=>$resp['id'].'@'.'t.163.com',
			'weibo_slug'=>'163weibo'
		);
		return $r;
	}
	function refresh_access_token(){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='refresh_token';
		$params['refresh_token']=$this->refresh_token;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']=smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,160,true);
		}else{
			$text=$weibo_data;
		}
		$params=array(); $params['access_token']=$this->access_token;
		$params['status']=$text;
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['pic']=$weibo_data['pic'];
			$resp=$this->oAuthRequest('https://api.t.163.com/statuses/upload.json','POST',$params,true);
			if($resp['upload_image_url']){
				return $this->publish_post($text.' '.$resp['upload_image_url']);
			}else{
				return $this->publish_post($text);
			}
		}else $resp=$this->oAuthRequest('https://api.t.163.com/statuses/update.json','POST',$params);
		if($resp['error']){
			return false;
		}else{
			return $resp['id'];
		}
	}
}