<?php
class Tianya extends smcHttp{
	public $oauth_1=true;
	public $client_id;
	public $json=false;
	public $client_secret;
	public $access_token;
	public $refresh_token;
	public $requestoken_url='http://open.tianya.cn/oauth/request_token.php';
	public $authorize_url='http://open.tianya.cn/oauth/authorize.php';
	public $accesstoken_url='http://open.tianya.cn/oauth/access_token.php';
	function __construct($client_id,$client_secret,$access_token=NULL,$refresh_token=NULL){
		$this->client_id=$client_id;
		$this->client_secret=$client_secret;
		$this->access_token=$access_token;
		$this->refresh_token=$refresh_token;
	}
	function getAuthorizeURL($callback_url=''){
		$params=$this->default_params();
		$resp=$this->oAuthRequest($this->requestoken_url,'GET',$params);
		if(is_string($resp))parse_str($resp,$resp);
		if(!is_array($resp)||!$resp['oauth_token'])throw new smcException('Oauth Token获取失败');
		setcookie('smcOauth_secret_'.COOKIEHASH,$resp['oauth_token_secret'],0,COOKIEPATH,COOKIE_DOMAIN);
		$params['oauth_token']=$resp['oauth_token'];
		$params['oauth_callback']=$callback_url;
		return $this->authorize_url."?".http_build_query($params);
	}
	function getAccessToken($code='',$callback_url=''){
		$this->refresh_token=$_COOKIE['smcOauth_secret_'.COOKIEHASH];
		$params = array();
		$resp=$this->oAuthRequest($this->accesstoken_url, 'GET', $params);
		if(is_string($resp))parse_str($resp,$resp);
		if($resp['oauth_token']){
			setcookie('smcOauth_secret_'.COOKIEHASH,'',time()-1,COOKIEPATH,COOKIE_DOMAIN);
			return array('access_token'=>$resp['oauth_token'],'refresh_token'=>$resp['oauth_token_secret'],'expires_in'=>'','access_time'=>time());
		}else{
			return array('error'=>'access token获取失败！');	
		}
	}
	function verify_credentials(){
		$this->json=true;
		$this->oauth_1=false;
		$params=$this->default_params();
		$resp=$this->oAuthRequest('http://open.tianya.cn/api/user/info.php','GET',$params);
		if($resp['error_code']){
			throw new smcException($resp['error_msg'].'（'.$resp['error_code'].'）');
		}
		$resp=$resp['user'];
		$user_login=$resp['user_name']?$resp['user_name']:$resp['user_id'];
		$r=array(
			'profile_image_url'=>$resp['head'],
			'user_login'=>$user_login,
			'uid'=>$resp['user_id'],
			'display_name'=>$resp['user_name'],
			'url'=>'http://my.tianya.cn/'.$resp['user_id'],
			'access_token'=>$this->access_token,
			'refresh_token'=>$this->refresh_token,
			'description'=>$resp['describe'],
			'statuses_count'=>0,
			'email'=>$resp['user_id'].'@'.'t.tianya.cn',
			'weibo_slug'=>'tianya'
		);
		return $r;
	}
	function default_params(){
		$params=array();
		$params['appkey']=$this->client_id;
		$params['oauth_token']=$this->access_token;
		$params['oauth_token_secret']=$this->refresh_token;
		$params['timestamp']=time();
		$params['tempkey']=strtoupper(md5($params['timestamp'].$params['appkey'].$params['oauth_token'].$params['oauth_token_secret'].$this->client_secret));
		return $params;
	}
	function publish_post($weibo_data){
		$this->json=true; $this->oauth_1=true;
		if(is_array($weibo_data)){
			$weibo_data['tags']=smcHttp::convtags($weibo_data['tags'],true);
			$text=smcHttp::format_post_data($weibo_data,130,true);
		}else{
			$text=$weibo_data;
		}
		$params=$this->default_params();
		$params['word']=$text;
		if(is_array($weibo_data) && $weibo_data['pic']){
			$params['media']=$weibo_data['pic'];
			$resp=$this->oAuthRequest('http://open.tianya.cn/api/weibo/addimg.php','POST',$params);
			if(empty($resp)||$resp['error']){
				return $this->publish_post($text);
			}
		}else $resp=$this->oAuthRequest('http://open.tianya.cn/api/weibo/add.php','POST',$params);
		if(empty($resp)||$resp['error']){
			return false;
		}else{
			return $resp['data']['id'];
		}
	}
}