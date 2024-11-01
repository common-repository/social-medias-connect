<?php
class qqSNS extends smcHttp{
	public $json=true;
	public $client_id;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $authorize_url='https://graph.qq.com/oauth2.0/authorize ';
	public $accesstoken_url='https://graph.qq.com/oauth2.0/token';
	public $openid_url='https://graph.qq.com/oauth2.0/me';
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
		$params['scope']='get_info,add_share';
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
		$resp=$this->parse_string($resp); $this->json=true;
		if($resp['access_token']){
			$openid=$this->getOpenid($resp['access_token']);
			return array('access_token'=>$resp['access_token'],'refresh_token'=>$resp['refresh_token'],'openid'=>$openid,'expires_in'=>$resp['expires_in'],'access_time'=>time());
		}else{
			return array('error'=>'token获取失败（'.$resp['error_description'].'）');	
		}
	}
	function getOpenid($access_token){
		$this->json=false;
		$params=array();
		$params['access_token']=$access_token;
		$resp=$this->oAuthRequest($this->openid_url, 'GET', $params);
		$resp=$this->parse_string($resp);
		if(empty($resp['openid'])){
			$resp['error']=true;
			$resp['error_description']='openid为空！';
		}
		if($resp['error'])throw new smcException('openid获取失败（'.$resp['error_description'].'）');
		$this->json=true;
		return $resp['openid'];
	}
	function parse_string($string=''){
		if(is_array($string)||is_object($string))return $string;
		if(strpos($string,'callback')!==false){
			preg_match('/\{.*\}/i',$string,$matches);
			if(count($matches))$string=json_decode($matches[0],true);
			else{
				$string=array('error'=>'10001','error_description'=>'数据解析错误！');
			}
		}else{
			parse_str($string,$string);
		}
		return $string;
	}
	function verify_credentials(){
		$params=$this->default_params();
		$params['format']='json';
		$resp=$this->oAuthRequest('https://graph.qq.com/user/get_info','GET',$params);
		if($resp['ret']){
			throw new smcException($resp['msg'].'（'.$resp['ret'].'）');
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
			'weibo_slug'=>'qqsns'
		);
		return $r;
	}
	function default_params(){
		$params=array();
		$params['oauth_consumer_key']=$this->client_id;
		$params['access_token']=$this->access_token;
		$params['openid']=$this->openid;
		return $params;
	}
	function publish_post($weibo_data){
		$params=$this->default_params();
		$params['format']='json';
		$params['title']=$weibo_data['title'];
		$params['url']=$weibo_data['url'];
		$params['summary']=smcHttp::sub_str($weibo_data['excerpt'],0,80);
		$params['nswb']='1';
		if($weibo_data['pic'])$params['images']=$weibo_data['pic'];
		if($weibo_data['comment'])$params['comment']=smcHttp::sub_str($weibo_data['comment'],0,40);
		$resp=$this->oAuthRequest('https://graph.qq.com/share/add_share','POST',$params);
		if($resp['ret']){
			return false;
		}else{
			return $resp['share_id'];
		}
	}
}