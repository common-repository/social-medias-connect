<?php
class Renren extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://graph.renren.com/oauth/authorize';
	public $accesstoken_url='https://graph.renren.com/oauth/token';
	public $server_url='http://api.renren.com/restserver.do';
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
		$params['scope']='publish_share publish_feed status_update';
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
			return array('error'=>'token获取失败（'.$response['error_description'].'）');	
		}
	}
	function verify_credentials(){
		$params=$this->default_params();
		$params['method']='users.getInfo';
		$params['sig']=$this->sig($params);
		$resp=$this->oAuthRequest($this->server_url,'POST',$params);;
		if($resp['error_code']){
			throw new smcException($resp['error_msg'].'（'.$resp['error_code'].'）');
		}
		$resp=$resp[0];
		$user_login=$resp['name'];
		$r=array(
			'profile_image_url'=>$resp['headurl'],
			'user_login'=>$user_login,
			'uid'=>$resp['uid'],
			'display_name'=>$resp['name'],
			'url'=>'http://www.renren.com/'.$resp['uid'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>'',
			'statuses_count'=>0,
			'email'=>$resp['uid'].'@'.'renren.com',
			'weibo_slug'=>'renren'
		);
		return $r;
	}
	function refresh_access_token(){
		$params=array();
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
	function default_params(){
		$params=array();
		$params['access_token']=$this->access_token;
		$params['v']='1.0';
		$params['format']='json';
		return $params;
	}
	function sig($params){
		ksort($params);
		$sig_string='';
		foreach($params as $key=>$v){
			$sig_string.=$key.'='.$v;
		}
		$sig_string.=$this->client_secret;
		$sig_string=md5($sig_string);
		return $sig_string;
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']='';//smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,200,true);
		}else{
			$text=$weibo_data;
		}
		$params=$this->default_params();
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['method']='share.share';
			$params['url']=$weibo_data['url'];
			$params['type']='6';
		}else{
			$params['status']=$text;
			$params['method']='status.set';
		}
		$params['sig']=$this->sig($params);
		$resp=$this->oAuthRequest($this->server_url,'POST',$params);
		if($resp['error_code']){
			return false;
		}else{
			return 1;
		}
	}
}