<?php
class qqWeibo extends smcHttp{
	public $json=true;
	public $client_id;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://open.t.qq.com/cgi-bin/oauth2/authorize';
	public $accesstoken_url='https://open.t.qq.com/cgi-bin/oauth2/access_token';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL,$openid=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
		$this->openid=$openid;
	}
	function getAuthorizeURL($callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['redirect_uri']=$callback_url;
		$params['response_type']='code';
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$this->json=false;
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='authorization_code';
		$params['code']=$code;
		$params['redirect_uri'] = $callback_url;
		$resp=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['access_token']){
			return array('access_token'=>$resp['access_token'],'refresh_token'=>$resp['refresh_token'],'openid'=>$resp['openid'],'expires_in'=>$resp['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$resp['errorCode'].'）');	
		}
	}
	function verify_credentials(){
		$this->json=true;
		$params=$this->default_params();
		$resp=$this->oAuthRequest('https://open.t.qq.com/api/user/info','GET',$params);
		if($resp['errcode']){
			throw new smcException($resp['msg'].'（'.$resp['errcode'].'）');
		}
		$resp=$resp['data'];
		$user_login=$resp['name'];
		$r=array(
			'profile_image_url'=>$resp['head'].'/100',
			'user_login'=>$user_login,
			'uid'=>strtolower($resp['openid']),
			'display_name'=>$resp['nick'],
			'url'=>'http://t.qq.com/'.$user_login,
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['introduction'],
			'statuses_count'=>$resp['tweetnum'],
			'email'=>$user_login.'@'.'t.qq.com',
			'real_email'=>$resp['email'],
			'weibo_slug'=>'qqweibo'
		);
		return $r;
	}
	function refresh_access_token(){
		$this->json=false;
		$params = array();
		$params['client_id']=$this->client_id;
		//$params['client_secret']=$this->client_secret;
		$params['grant_type']='refresh_token';
		$params['refresh_token']=$this->refresh_token;
		$resp=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['access_token']){
			return array('access_token'=>$resp['access_token'],'refresh_token'=>$resp['refresh_token'],'openid'=>$resp['openid'],'expires_in'=>$resp['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token刷新失败（'.$response['error'].'）');	
		}
	}
	function default_params(){
		$params=array();
		$params['oauth_consumer_key']=$this->client_id;
		$params['access_token']=$this->access_token;
		$params['openid']=$this->openid;
		$params['format']='json';
		$params['scope']='all';
		$params['oauth_version']='2.a';
		return $params;
	}
	function publish_post($weibo_data){
		if(is_array($weibo_data)){
			$weibo_data['tags']=smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,138,true);
		}else{
			$text=$weibo_data;
		}
		$params=$this->default_params();
		$params['format']='json';
		$params['content']=$text;
		$params['clientip']=smcHttp::get_client_ip();
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['pic']=$weibo_data['pic'];
			$resp=$this->oAuthRequest('https://open.t.qq.com/api/t/add_pic','POST',$params,true);
			if($resp['errcode']){
				return $this->publish_post($text);
			}
		}else $resp=$this->oAuthRequest('https://open.t.qq.com/api/t/add','POST',$params);
		if($resp['errcode']){
			return false;
		}else{
			return $resp['data']['id'];
		}
	}
}