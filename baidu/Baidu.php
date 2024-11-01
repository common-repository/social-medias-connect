<?php
class Baidu extends smcHttp{
	public $client_id;
	public $json=true;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://openapi.baidu.com/oauth/2.0/authorize';
	public $accesstoken_url='https://openapi.baidu.com/oauth/2.0/token';
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
		$params['scope']='basic';
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='authorization_code';
		$params['code']=$code;
		$params['redirect_uri'] = $callback_url;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'scope'=>$response['scope'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error_description'].'）');	
		}
	}
	function refresh_access_token(){
		$params = array();
		$params['client_id']=$this->client_id;
		$params['client_secret']=$this->client_secret;
		$params['grant_type']='refresh_token';
		$params['refresh_token']=$this->refresh_token;
		$response=$this->oAuthRequest($this->accesstoken_url, 'POST', $params);
		if($response['access_token']){
			return array('access_token'=>$response['access_token'],'refresh_token'=>$response['refresh_token'],'scope'=>$response['scope'],'expires_in'=>$response['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$response['error'].'）');	
		}
	}
	function verify_credentials(){
		$params=array();
		$params['access_token']=$this->access_token;
		$params['format']='json';
		$params['fields']='userid,username,realname,userdetail,portrait,sex';
		$resp=$this->oAuthRequest('https://openapi.baidu.com/rest/2.0/passport/users/getInfo','POST',$params);
		if($resp['error']){
			throw new smcException($resp['error_description'].'（'.$resp['error'].'）');
		}
		$user_login=$resp['username']?$resp['username']:$resp['userid'];
		$r=array(
			'profile_image_url'=>'http://himg.bdimg.com/sys/portrait/item/'.$resp['portrait'].'.jpg',
			'user_login'=>$user_login,
			'uid'=>$resp['userid'],
			'display_name'=>$resp['realname'],
			'url'=>'http://hi.baidu.com/'.$user_login,
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['userdetail'],
			'statuses_count'=>0,
			'email'=>$resp['userid'].'@'.'baidu.com',
			'weibo_slug'=>'baidu'
		);
		return $r;
	}
}