<?php
/**
 * 通知、错误显示
 */
class smcNotice{
	public $messages=array();

	function add($msg='',$type='error',$caps=''){
		$this->messages[]=array($msg,$type,$caps);
	}
	
	function admin_notice(){
		global $SMC;
		$msgs=$this->messages;
		if(count($msgs)){
			foreach($msgs as $msg){
				if(empty($msg[2]) || $msg[2] && $SMC->is_admin_access())echo '<div class="'.$msg[1].'"><p>'.$msg[0].'</p></div>';
				else echo '<div class="error"><p>内部错误，请联系站点管理员解决。</p></div>';
			}
		}
	}
}

/**
 * 主程运行间的异常类
 */
class smcException extends Exception{
	public static $throw_exception=true;
	function __construct($message='',$code=0){
		global $SMC;
		parent::__construct($message,$code);
		$SMC->notice->add($message,'error',$code);
	}
}

/**
 * 用户注册等表单的异常类
 */
class smcFormException extends Exception{
	function __construct($message='',$code=0){
		parent::__construct($message,$code);
	}
}

/**
 * 社交媒体连接主类
 */
class smcClass{
	public $vesion=SMC_VERSION;
	public $weibo_array;
	private $option_url='http://1.socialmedias.sinaapp.com';
	private $transfer_url='http://smcstation.sinaapp.com/';
	private $config;
	public $option;
	public $notice;
	public $smcUser;
	public $base_dir;
	public $is_login_page;
	public $weibo_loaded;
	
	public function __construct($weibo=null){
		$this->notice=new smcNotice;
		$this->config=get_option('smc_global_option');
		$this->option=get_option('smc_weibo_options');
		$this->weibo_array=$this->config?array_keys($this->config):array();
		$this->base_dir=WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__));
		
		/**
		 * 这里绑定了一些插件需要的通用行为
		 * 包括头像显示、插件所需文件的引入
		 */
		add_action('init', array($this,'init'));
		add_action('wp_head', array($this,'wp_head'));
		add_action('admin_head', array($this,'admin_head'));
		add_action('login_head', array($this,'login_head'));
		add_action('admin_menu', array($this,'admin_menu'));
		add_action('comment_post', array($this,'comment_post'));
		add_filter('get_avatar',array($this,'get_avatar'),10,4);
		add_action('login_form', array($this,'smc_print_weibo'));
		add_action('add_meta_boxes',array($this,'add_meta_box'));
		add_action('register_form', array($this,'smc_print_weibo'));
		add_action('login_form_login', array($this,'login_redirect'));
		add_action('lostpassword_form', array($this,'smc_print_weibo'));
		add_action('login_form_register', array($this,'login_redirect'));
		add_action('admin_notices', array($this->notice,'admin_notice'));
		add_action('login_form_lostpassword', array($this,'login_redirect'));
		add_filter('plugin_action_links',array($this,'plugin_action_link'),10,4);
	}
	
	/**
	 * 页面初始化
	 */
	public function init(){
		$this->smcUser=new smcUser;
		try{
			if(is_user_logged_in()){
				if(is_admin()){
					$this->admin_quest_action();
				}
			}else{
				if($_GET['smcaction']=='smcregister'){
					$this->insert_new_user($_POST);
				}
			}
			
			if(empty($this->option)){
				$this->initialize_option();
			}

			if(empty($this->config)){
				if($_GET['smc_request']!='getglobaloption' && is_admin())
					throw new smcException('插件需要更新配置，请<a href="'.$this->get_menupage_url('social-medias-connect').'&smc_request=getglobaloption">点此获取</a>插件配置',1);
			}
			
			if(isset($_GET['socialmedia'])){
				if(!in_array($_GET['socialmedia'],$this->weibo_array))
					throw new smcException('您请求连接的网站不被支持！');
				$weibo=new smcWeibo($_GET['socialmedia']);
				$callback_url=isset($_GET['callback_url'])?$_GET['callback_url']:get_bloginfo('home');
				setcookie('smcRedirect_uri_'.COOKIEHASH,$callback_url,0,COOKIEPATH,COOKIE_DOMAIN);
				$weibo->request_token($this->transfer_url.'?redirect_uri='.$callback_url);
			}
			if(isset($_GET['code'])||isset($_GET['oauth_token'])){
				$oauth_code=isset($_GET['code'])?$_GET['code']:$_GET['oauth_token'];
				if(empty($_COOKIE['smcWeibo_'.COOKIEHASH]))throw new smcException('code已经失效！请重新认证！');
				$weibo=new smcWeibo($_COOKIE['smcWeibo_'.COOKIEHASH]);
				$weibo->getAccessToken($oauth_code,$this->transfer_url.'?redirect_uri='.$_COOKIE['smcRedirect_uri_'.COOKIEHASH],$_GET['openid']);
				$weibo->get_verify_credentials();
			}
		}catch(smcException $m){
			$this->show_message($m);
		}
		$option=$this->option;
		$post_types=$this->option['post_type'];
		if(!is_array($post_types)){
			$post_types=array();
		}
		foreach($post_types as $type){
			add_action('publish_'.$type,array($this,'publish_post'));
		}
		if($option['auto_insert_form']){
			add_action('comment_form', array($this,'smc_print_weibo'));
			add_action('comment_form_must_log_in_after', array($this,'smc_print_weibo'));
		}
	}
	
	public function wp_head(){
		echo "<!-- start social medias connect V$this->vesion -->\n";
		if(is_user_logged_in() && (isset($_GET['code'])||$_GET['smcregister']=='success')){
			echo "<script>try{window.opener.smcAction();window.close();}catch(e){window.location.href=window.location.href.replace(/[?&](code|smcregister).*/,\"\");}</script>";
		}
		echo "<script type=\"text/javascript\">
			window.smcAction=function(url){
				url=url||window.location.href;
				window.location.href=url.replace(/&smc.*/i,\"\");
				setTimeout(function(){
					window.location.reload();
				},1000);
				return true;
			}
		</script>";
		echo "\n<!-- end social medias connect V$this->vesion -->\n";
	}
	
	public function admin_head(){
		if((isset($_GET['code'])||$_GET['smcregister']=='success') && empty($this->notice->messages)){
			echo '<script>try{window.opener.smcAction();window.close();}catch(e){window.location.href=window.location.href.replace(/[?&](code|smcregister).*/,"");}</script>';
		}
		echo '<link rel="stylesheet" media="all" href="'.$this->base_dir.'/css/smc-admin.css?s='.$this->vesion.'" type="text/css" />';
		echo '<script type="text/javascript" src="'.$this->base_dir.'/js/smc-admin.js?s='.$this->vesion.'"></script>';
	}
	
	public function login_head(){
		$this->wp_head();
		echo '<link rel="stylesheet" media="all" href="'.$this->base_dir.'/css/smc-style.css?s='.$this->vesion.'" type="text/css" />';
		$this->is_login_page=true;
	}
	
	public function plugin_action_link($actions,$plugin_file,$plugin_data){
		if(strpos($plugin_file,'social-medias-connect')!==false && is_plugin_active($plugin_file)){
			unset($actions['edit']);
			$myactions=array('bind'=>'<a href="'.$this->get_menupage_url('social-medias-connect').'">绑定</a>',
				'option'=>'<a href="'.$this->get_menupage_url('smc_bind_weibo_option').'">设置</a>');
			$actions=array_merge($myactions,$actions);
		}
		return $actions;
	}
	
	public function get_avatar($avatar,$id_or_email='',$size='32',$default_key=''){
		if(is_object($id_or_email)){
			if(!empty($id_or_email->user_id)){
				$user_id=(int)$id_or_email->user_id;
			}
		}else if(is_numeric($id_or_email)){
			$user_id=$id_or_email;
		}else{
			$user=get_user_by('email',$id_or_email);
			$user_id=(int)$user->ID;
		}
		if(!empty($user_id)){
			$newUser=new smcUser($user_id);
			$avatar=$newUser->get_avatar($size,$avatar);
		}
		return $avatar;
	}
	
	public function add_meta_box(){
		$post_types=$this->option['post_type'];
		foreach($post_types as $type){
			add_meta_box( 
				'smc-meta-box','社交媒体连接',array($this,'show_meta_box'),$type,'side','high'
			);
		}
	}
	
	public function show_meta_box(){
		global $post;
		$option=$this->option;
		$config=$this->config;
		if(empty($config)){
			echo '插件配置错误。';
			return false;
		}
		$weibo_array=$this->weibo_array; $sync_array=array();
		foreach($weibo_array as $weibo_slug){
			if(get_post_meta($post->ID, '_'.$weibo_slug.'_sync', true)){
				$sync_array[]='<code>'.$this->get_weibo_name($weibo_slug).'</code>';
			}
		}
		if(count($sync_array)){
		echo '<div class="submitbox">';
		echo '<p><strong>同步状态：</strong><br/>';
		echo '这篇文章已经同步到'.implode(', ',$sync_array).'</p>';
		echo '</div>';
		}
		$sync_user_id=$option['syncaccount'];
		$curr_user_id=smcUser::get_current_user_id();
		echo '<div class="submitbox">';
		echo '<p><strong>选择要同步的账户：</strong><br/>';
		echo '<select data-url="'.admin_url().'" id="smc-user-change" name="smc-user">';
		echo '<option value="">不进行同步</option>';
		if($sync_user_id){
			$myuser=new WP_User($sync_user_id);
			echo '<option value="'.$sync_user_id.'">'.$myuser->display_name.'（默认账号）</option>';
		}
		if($sync_user_id!=$curr_user_id){
			$myuser=new WP_User($curr_user_id);
			echo '<option value="'.$curr_user_id.'">'.$myuser->display_name.'（你自己）</option>';
		}
		echo '</select> <img id="smc-loading-img" src="'.$this->base_dir.'/images/loading-publish.gif" style="display:none;" alt="" /></p>';
		echo '<div id="smc-weibo-bind" style="display:none;"></div>';
		echo '</div>';
	}
	
	/**
	 * 在页面上显示微博按钮
	 * 
	 * @param array|string $args 配置参数
	 * 		callback_url: 回调地址
	 */
	public function smc_print_weibo($args=''){
		$option=$this->option;
		$defaults=array(
			'callback_url'=>'',
			'login_format'=>$option['smc_login_format'],
			'sync_format'=>$option['smc_sync_format'],
			'size'=>$option['icon_width']
		);
		$r=wp_parse_args($args,$defaults);
		extract($r);
		$loaded=$this->weibo_loaded?'-'.$this->weibo_loaded:'';
		$callback_url=$callback_url?$callback_url:smcClass::get_current_page_url();
		$str_smc="\n<!-- social medias connect $this->vesion -->\n";
		if(is_user_logged_in()){
			$bind_array=$this->get_comment_bind();
			if(empty($bind_array)){
				$foramt='';
			}else{
				foreach($bind_array as $weibo_slug=>$cfg){
					$str_smc.='<label class="smc_label"'.($cfg['timeout']?' title="授权已过期" onclick="if(confirm(\'授权已过期，需要重新绑定！立即进行绑定？\'))window.open(\''.get_bloginfo('home').'?socialmedia='.$weibo_slug.'\',\'smcWindow\',\'width=800,height=600,left=150,top=100,scrollbar=no,resize=no\');return false;"':' title="同步到'.$this->get_weibo_name($weibo_slug).'" ').'><input style="width:auto;height:auto;float:none;" '.($option['force_sync_comment']||$cfg['timeout']?'disabled ':'').'type="checkbox" '.($option['force_sync_comment']&&!$cfg['timeout']?'checked="checked" ':'').'name="sycnsocialmedia[]" value="'.$weibo_slug.'" /><img src="'.$this->base_dir.'/images/icons/'.$weibo_slug.'.png" style="display:inline;width:16px;height:16px;" title="'.$this->get_weibo_name($weibo_slug).'" alt="'.$this->get_weibo_name($weibo_slug).'" /></label>';
				}
				$foramt=$sync_format;
			}
		}else{
			$connects=(array)$option['connect'];
			foreach($connects as $weibo_slug){
				$str_smc.='<a class="smc_link" rel="external nofollow" title="Login with '.$this->get_weibo_name($weibo_slug).'" href="'.get_bloginfo('home').'?socialmedia='.$weibo_slug.'&callback_url='.$callback_url.'" onclick="window.open(this.href,\'smcWindow\',\'width=800,height=600,left=150,top=100,scrollbar=no,resize=no\');return false;"><span class="smc_span"><img class="smc_icon smc_icon_'.$size.'" width="'.$size.'" height="'.$size.'" src="'.$this->base_dir.'/images/icons/'.$weibo_slug.'.png" alt="'.$this->get_weibo_name($weibo_slug).'" /></span></a>';
			}
			if($this->is_login_page)$foramt='<p class="smc-login-area" id="smc-login-area%%id%%">%%smc%%</p>';
			else $foramt=$login_format;
		}
		$str_smc.="\n<!-- end social medias connect $this->vesion -->\n";
		echo str_ireplace(array('%%id%%','%%smc%%'),array($loaded,$str_smc),$foramt);
		$this->weibo_loaded=mt_rand(1,999999999);
	}
	
	/**
	 * 判断当前用户是否有设置权限
	 */
	public function is_admin_access(){
		return current_user_can('manage_options');
	}
	
	/**
	 * 显示后台菜单
	 */
	public function admin_menu(){
		add_menu_page('社交媒体连接设置','社交媒体连接','0','social-medias-connect',array($this,'bind_weibo_sync_posts'),(WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__))).'/images/favicon.png');
		add_submenu_page('social-medias-connect', '绑定您的微博账号', '微博账号绑定', '0', 'social-medias-connect', array($this,'bind_weibo_sync_posts'));
		add_submenu_page('social-medias-connect', '文章同步设置', '插件设置', 'administrator', 'smc_bind_weibo_option', array($this,'bind_weibo_option'));
		add_submenu_page('social-medias-connect', '卸载插件', '卸载插件', 'administrator', 'smc_bind_weibo_uninstall', array($this,'bind_weibo_uninstall'));
		add_submenu_page('social-medias-connect', '帮助信息', '帮助', 0, 'smc_bind_weibo_help', array($this,'smc_bind_weibo_help'));
	}
	
	public function bind_weibo_sync_posts(){
		include 'smctable.php';
		include 'bindweibo.php';
	}
	
	public function bind_weibo_option(){
		@include 'bindoption.php';
	}
	
	public function bind_weibo_uninstall(){
		@include 'uninstall.php';
	}
	
	public function smc_bind_weibo_help(){
		@include 'help.php';
	}
	
	private function admin_quest_action(){
		if(isset($_COOKIE['smc_redirect_msg_'.COOKIEHASH])&&!$_GET['code']&&$_GET['smcregister']!='success'){
			$msg=$_COOKIE['smc_redirect_msg_'.COOKIEHASH];
			$type=isset($_GET['smcdo'])?'error':'updated';
			$this->notice->add($msg,$type);
			setcookie('smc_redirect_msg_'.COOKIEHASH,'',time()-1,COOKIEPATH,COOKIE_DOMAIN);
		}

		if($_GET['smcaction']=='bindpage'){
			$msg='';
			if($_GET['action']=='delete'||$_GET['action2']=='delete'){
				if(isset($_GET['smccheck'])){
					$delete_array=$_GET['smccheck'];
					array_walk($_GET['smccheck'],array($this->smcUser,'unbind'));
					$smcheck=array_map(array($this,'get_weibo_name'),$delete_array);
					setcookie('smc_redirect_msg_'.COOKIEHASH,'您刚刚成功删除了'.implode(', ',$smcheck),0,COOKIEPATH,COOKIE_DOMAIN);
				}elseif(isset($_GET['weibo_slug'])){
					$this->smcUser->unbind($_GET['weibo_slug']);
					setcookie('smc_redirect_msg_'.COOKIEHASH,'您刚刚成功删除了'.$this->get_weibo_name($_GET['weibo_slug']),0,COOKIEPATH,COOKIE_DOMAIN);
				}
				wp_redirect($this->get_menupage_url('social-medias-connect&smcdo=delete'));
				exit;
			}elseif($_GET['action']=='refresh'){
				$resp=smcWeibo::refresh_token($_GET['weibo_slug']);
				$msg=$resp?'您刚刚成功刷新了授权！':'授权未成功刷新！';
			}elseif($_GET['default_weibo']){
				$this->smcUser->set_default_weibo($_GET['default_weibo']);
				$msg='您已经将'.$this->get_weibo_name($_GET['default_weibo']).'设置为默认显示帐号';
			}
			if(!empty($msg))setcookie('smc_redirect_msg_'.COOKIEHASH,$msg,0,COOKIEPATH,COOKIE_DOMAIN);
			wp_redirect($this->get_menupage_url('social-medias-connect'));
			exit;
		}
		
		if(isset($_GET['smc-get-post-bind'])){
			$user_id=$_GET['smc-get-post-bind'];
			$post_id=$_GET['smc-post-id'];
			echo json_encode($this->get_post_bind($post_id,$user_id));
			exit;
		}
		
		if($_GET['smcaction']=='smc_update_plugin'){
			$this->vesion_compatible();
			setcookie('smc_redirect_msg_'.COOKIEHASH,'恭喜，升级已完成',0,COOKIEPATH,COOKIE_DOMAIN);
			wp_redirect($this->get_menupage_url('smc_bind_weibo_uninstall'));
			exit;
		}

		if($_POST['smcaction']=='smcorder'){
			$result=array();
			if(empty($_POST['order'])){
				echo json_encode(array('status'=>'error','message'=>'你没有选择任何微博！'));
			}else{
				$http=new WP_Http;
				$current_user=wp_get_current_user();
				$site_admin=$current_user->user_login?$current_user->user_login:$current_user->data->user_login;
				$resp=$http->request($this->option_url,array('method' => 'POST',"user-agent"=>'social medias connect v'.$this->vesion.' Order','headers'=>array('SMC-ACTION'=>'order'),'body'=>array('action'=>'order','site_email'=>get_option('admin_email'),'site_admin'=>$site_admin,'site_name'=>get_bloginfo('name'),'site_url'=>get_bloginfo('url'),'order'=>$_POST['order'])));
				if(is_array($resp)){
					$resp=json_decode($resp['body'],true);
					if($resp['error']){
						echo json_encode(array('status'=>'error','message'=>$resp['error_message']));
					}else echo json_encode(array('status'=>'success','linkto'=>esc_url($resp['url'])));
				}else echo json_encode(array('status'=>'error','message'=>'请求失败！'));
			}
			exit;	
		}
		if($this->is_admin_access()){
			if($_GET['smc_request']=='getglobaloption')
				$this->get_global_option();
			if($_GET['smc_request']=='smcsetappkey')
				$this->set_appkey_form();
			
			if($_POST['smcaction']=='bindoption'){
				$options=$this->option;
				$weibo_array=$this->weibo_array;
				if(isset($_POST['connect'])){
					$connect_array=$_POST['connect'];
					$options['connect']=array();
					foreach($connect_array as $weibo_slug){
						if(in_array($weibo_slug,$weibo_array))
							$options['connect'][]=$weibo_slug;
					}
				}
				if(isset($_POST['syncaccount'])){
					$options['syncaccount']=$_POST['syncaccount'];
				}
				$options['post_type']=isset($_POST['post_type'])?$_POST['post_type']:array();
				if(isset($_POST['smc_shorturl_service'])){
					$options['smc_use_short']=$_POST['smc_use_short'];
					$options['smc_shorturl_service']=$_POST['smc_shorturl_service'];
				}
				if(isset($_POST['smc_post_format'])){
					$options['smc_post_format']=stripslashes($_POST['smc_post_format']);
				}
				if(isset($_POST['smc_comment_format'])){
					$options['smc_comment_format']=stripslashes($_POST['smc_comment_format']);
				}
				if(isset($_POST['smc_login_format'])){
					$options['smc_login_format']=stripslashes($_POST['smc_login_format']);
				}
				if(isset($_POST['smc_sync_format'])){
					$options['smc_sync_format']=stripslashes($_POST['smc_sync_format']);
				}
				
				$options['throw_exception']=isset($_POST['throw_exception'])?$_POST['throw_exception']:'';
				$options['smc_auto_remember']=isset($_POST['smc_auto_remember'])?$_POST['smc_auto_remember']:'';
				$options['auto_register']=isset($_POST['auto_register'])?$_POST['auto_register']:'';
				$options['smc_user_notice']=isset($_POST['smc_user_notice'])?$_POST['smc_user_notice']:'';
				$options['smc_admin_notice']=isset($_POST['smc_admin_notice'])?$_POST['smc_admin_notice']:'';
				$options['smc_thumb']=isset($_POST['smc_thumb'])?$_POST['smc_thumb']:'';
				$options['add_follow']=isset($_POST['add_follow'])?$_POST['add_follow']:'';
				$options['auto_insert_form']=isset($_POST['auto_insert_form'])?$_POST['auto_insert_form']:'';
				$options['force_sync_comment']=isset($_POST['force_sync_comment'])?$_POST['force_sync_comment']:'';
				$options['icon_width']=$_POST['icon_width'];
				
				update_option('smc_weibo_options',$options);
				$this->option=$options;
				setcookie('smc_redirect_msg_'.COOKIEHASH,'设置已保存。',0,COOKIEPATH,COOKIE_DOMAIN);
				if(isset($_POST['submit_box1'])){
					$box='&box=1';
				}elseif(isset($_POST['submit_box2'])){
					$box='&box=2';
				}elseif(isset($_POST['submit_box3'])){
					$box='&box=3';
				}
				wp_redirect($this->get_menupage_url('smc_bind_weibo_option').$box);
				exit;
			}
		}
	}
	
	/**
	 * 初始化设置
	 *
	 */
	public function initialize_option(){
		$options=array(
			'connect'=>array(
					'sinaweibo','qqweibo','qqsns','github','google','renren','163weibo','baidu','fanfou'
				),
			'syncaccount'=>'',
			'post_type'=>array('post'),
			'throw_exception'=>'',
			'auto_register'=>'',
			'auto_insert_form'=>'1',
			'smc_user_notice'=>'1',
			'smc_admin_notice'=>'1',
			'icon_width'=>'22',
			'auto_insert_form'=>'1',
			'smc_shorturl'=>'',
			'smc_use_short'=>'1',
			'smc_thumb'=>'1',
			'add_follow'=>'',
			'smc_post_format'=>'【文章发布】%%title%% %%tags%% %%excerpt%% - %%url%%',
			'smc_comment_format'=>'我对《%%title%%》的观点: %%comment%% - %%url%%',
			'smc_login_format'=>'<p class="smc-login-area" id="smc-login-area%%id%%">%%smc%%</p>',
			'smc_sync_format'=>'<p class="smc-sync-area" id="smc-sync-area%%id%%"><strong>同步到:</strong>%%smc%%</p>',
			'smc_shorturl_service'=>'sinaurl',
			'smc_auto_remember'=>'',
			'force_sync_comment'=>''
		);
		$_old=(array)$this->option;
		$options=array_merge($options,$_old);
		$this->update_option($options);
	}
	
	/**
	 * 获取插件的配置信息。并且获取成功或者失败后在页面上显示相关信息
	 */
	public function get_global_option(){
		$http=new WP_Http;
		$resp=$http->request($this->option_url,array(
			"method"=>'POST',
			"timeout"=>50,
			"sslverify"=>false,
			"user-agent"=>'social medias connect v'.$this->vesion.' getConfig',
			"body"=>array('getConfig'=>'','vesion'=>$this->vesion,'site_url'=>get_bloginfo('url')),
			"headers"=>array('SMC-ACTION'=>'getConfig')
		));
		if(is_array($resp)){
			$resp=json_decode($resp['body'],true);
			if($resp['info']=='success'){
				$config=$this->check_file_exists($resp['result']);
				$config=$this->merge_appkey($config);
				$this->update_config($config);
				$this->notice->add('获取配置成功！'.($resp['msg']?'（'.$resp['msg'].'）':''),'updated');
				return true;
			}
		}
		$this->notice->add('获取配置失败！'.(is_array($resp)?$resp['result']:$resp->errors['http_request_failed'][0]),'error');
	}
	
	public function update_config($config){
		$this->config=$config;
		update_option('smc_global_option',$config);
		$this->weibo_array=array_keys($config);
	}
	
	public function update_option($option){
		update_option('smc_weibo_options',$option);
		$this->option=$option;
	}
	
	public function check_file_exists($config){
		foreach($config as $weibo_slug=>$cfg){
			$filename=dirname(__FILE__).'/'.$weibo_slug;
			if(!is_dir($filename)) unset($config[$weibo_slug]);
		}
		return $config;
	}
	
	public function login_redirect(){
		if(is_user_logged_in() && !$_GET['code'] && $_GET['smcregister']!='success'){
			if($_GET['redirect_to'] ){
				wp_redirect($_GET['redirect_to']);
				exit;
			}else{
				wp_redirect(admin_url(''));
				exit;
			}
		}
	}
	
	/**
	 * 获取某个用户的可用的文章同步微博列表
	 *
	 * @param string $post_id 文章id
	 * @param string $user_id 用户uid
	 * @return array $weibo_sync 返回指定用户的某一篇文章的同步状态
	 */
	public function get_post_bind($post_id='',$user_id=''){
		if(!$user_id) $user_id=smcUser::get_current_user_id();
		$smcuser=new smcUser($user_id);
		$smcdata=$smcuser->get_smcdata(); $weibo_sync=array();
		$weibo_array=array_keys($smcdata['socialmedia']);
		foreach($weibo_array as $weibo_slug){
			$suppots=$this->get_supports($weibo_slug);
			$cfg=$smcdata['socialmedia'][$weibo_slug];
			if(in_array('syncpost',$suppots))
				$weibo_sync[$weibo_slug]=array(
					'name'=>$this->get_weibo_name($weibo_slug),
					'timeout'=>$cfg['expires_in']&&(intval($cfg['expires_in'])+$cfg['access_time'])<time(),
					'sync'=>!!get_post_meta($post_id, '_'.$weibo_slug.'_sync', true)
				);
		}
		return $weibo_sync;
	}
	
	/**
	 * 获取当前用户的可用的评论同步微博列表
	 *
	 * @return array $weibo_sync 返回指定用户的某一篇文章的同步状态
	 */
	public function get_comment_bind(){
		$smcuser=$this->smcUser;
		$smcdata=$smcuser->get_smcdata(); $weibo_sync=array();
		$weibo_array=array_keys($smcdata['socialmedia']);
		foreach($weibo_array as $weibo_slug){
			$suppots=$this->get_supports($weibo_slug);
			$cfg=$smcdata['socialmedia'][$weibo_slug];
			if(in_array('syncpost',$suppots))
				$weibo_sync[$weibo_slug]=array(
					'name'=>$this->get_weibo_name($weibo_slug),
					'timeout'=>$cfg['expires_in']&&(intval($cfg['expires_in'])+$cfg['access_time'])<time()
				);
		}
		return $weibo_sync;
	}
	
	/**
	 * 获取菜单对应的页面URL
	 *
	 * @param string $pagename 菜单对应的页面别名
	 * @return string
	 */
	public function get_menupage_url($pageslug){
		return site_url('/wp-admin/admin.php?page='.$pageslug);
	}
	
	/**
	 * 获取微博网站名称
	 *
	 * @param string $weibo_slug 微博网站对应的简码
	 * @return string
	 */
	public function get_weibo_name($weibo_slug=''){
		return $this->config[$weibo_slug]['name'];
	}
	
	/**
	 * 获取微博网站对应的OAuth类名
	 *
	 * @param string $weibo_slug 微博网站对应的简码
	 * @return string
	 */
	public function get_weibo_OAuth_name($weibo_slug=''){
		return $this->config[$weibo_slug]['OAuthClass'];
	}
	
	/**
	 * 获取微博网站对应的的appKey和appSecret
	 *
	 * @param string $weibo_slug 微博网站对应的简码
	 * @return array array(0=>appkey, 1=>appsecret)
	 */
	public function get_key_and_secret($weibo_slug=''){
		if(in_array($weibo_slug,$this->weibo_array)){
			return $this->config[$weibo_slug]['appkey'];
		}
		return array(0,0);
	}
	
	/**
	 * 获取微博网站对应字母拼音
	 *
	 * @param string $weibo_slug 微博网站对应的简码
	 * @return string
	 */
	public function get_pinyin_of_weibo($weibo_slug=''){
		if(in_array($weibo_slug,$this->weibo_array)){
			return $this->config[$weibo_slug]['pinyin'];
		}
		return '';
	}
	
	/**
	 * 获取微博所支持的功能
	 *
	 * @param string $weibo_slug 微博网站对应的简码
	 * @return array
	 */
	public function get_supports($weibo_slug=''){
		if(in_array($weibo_slug,$this->weibo_array)){
			return $this->config[$weibo_slug]['supports'];
		}
		return array();
	}
	
	/**
	 * 获取网站文章类型honghutiancheng
	 * @param int $f 开始截取位置，1和2可选，1不保留page类型
	 * @return array $types 当前网站包括post page在内的所有新注册的文章类型(不包括媒体、menus等wp默认的类型)
	 */
	public static function get_all_post_type($f=1){
		$types=get_post_types();
		array_splice($types,$f,3,array());
		return $types;
	}
	
	public function merge_appkey($config=''){
		$appopt=get_option('smc_weibo_appkey');
		if(empty($config))$config=array();
		$this->config=$config;
		if(!$appopt)$appopt=array();
		foreach($config as $weibo_slug=>$opt){
			if(!in_array('customappkey',$this->get_supports($weibo_slug)))
				continue;
			if(is_array($appopt[$weibo_slug])){
				if(!$config[$weibo_slug]['original_appkey'])
					$config[$weibo_slug]['original_appkey']=$config[$weibo_slug]['appkey'];
				$config[$weibo_slug]['appkey']=$appopt[$weibo_slug];
			}elseif(is_array($config[$weibo_slug]['original_appkey'])){
				$config[$weibo_slug]['appkey']=$config[$weibo_slug]['original_appkey'];
				unset($config[$weibo_slug]['original_appkey']);
				$this->smcUser->unbind($weibo_slug);
			}
		}
		return $config;
	}
	
	public function is_custom_appkey($weibo_slug=''){
		$ignore=array(
			'sohuweibo',
			'tianya',
			'fanfou',
			'twitter'
		);
		$cfg=$this->config[$weibo_slug];
		return !in_array($weibo_slug,$ignore) && $cfg && $cfg['original_appkey'];
	}
	
	/**
	 * 同步评论
	 *
	 * @param string $comment_id 评论id
	 *
	 */
	public function comment_post($comment_id=''){
		$option=$this->option;
		$config=$this->config;
		$post_id=$_POST['comment_post_ID'];
		$_post=get_post($post_id);
		$_comment=get_comment($comment_id);
		if(!is_user_logged_in()||$_comment->comment_post_ID!=$post_id||!$_post||!$_comment||!$option['force_sync_comment']&&!isset($_POST['sycnsocialmedia'])){
			return;
		}
		$thumb=$option['smc_thumb']?$this->get_post_thumb_url($_post):'';
		$post_url=get_permalink($post_id);
		if($option['smc_use_short']){
			$post_url=$this->get_short_url($option['smc_shorturl_service'],$post_url,$_post);
		}
		$weibo_data=array('id'=>$post_id,'title'=>__($_post->post_title),'url'=>$post_url,'pic'=>$thumb,'tags'=>wp_get_post_tags($post_id),'comment'=>strip_tags($_comment->comment_content));
		smcHttp::$COMMENT=true;
		$smcuser=new smcUser();
		$weibo_sync=array();
		if($option['force_sync_comment']){
			$weibo_array=$this->get_comment_bind();
			foreach($weibo_array as $weibo_slug=>$weibo_cfg){
				if(!$weibo_cfg['timeout'])
					$weibo_sync[]=$weibo_slug;
			}
		}elseif(isset($_POST['sycnsocialmedia'])){
			$weibo_sync=$_POST['sycnsocialmedia'];
		}
		$smcuser->publish_post($weibo_sync,$weibo_data);
	}
	
	/**
	 * 同步文章
	 *
	 * @param string $post_id 文章id
	 *
	 */
	public function publish_post($post_id=''){
		$post_id=$post_id?$post_id:$_POST['post_ID'];
		$option=$this->option;
		$config=$this->config;
		$user_id=isset($_POST['smc-user'])?$_POST['smc-user']:$option['syncaccount'];	
		if($_POST['action'] == "autosave"/*自动保存*/ 
			|| $_POST['action'] == "inline-save" || isset($_POST['_inline_edit']) /*快速编辑*/ 
			|| $_POST['post_status'] == "draft" /*保存草稿*/ 
			|| $_POST['post_status'] == "private" /*私密文章*/ 
			|| isset($_GET['bulk_edit']) /*批量编辑*/  
			|| empty($option) /*插件设置丢失*/
			|| empty($config) /*插件配置丢失*/
			|| empty($user_id) /*没有要同步到的用户*/
		){
			return false; //不进行同步
		}
		$_post=get_post($post_id);
		$thumb=$option['smc_thumb']?$this->get_post_thumb_url($_post):'';
		$post_url=get_permalink($post_id);
		if($option['smc_use_short']){
			$post_url=$this->get_short_url($option['smc_shorturl_service'],$post_url,$_post);
		}
		$excerpt=empty($_post->post_excerpt)?strip_tags(__($_post->post_content)):strip_tags(__($_post->post_excerpt));
		$weibo_data=array('id'=>$post_id,'title'=>__($_post->post_title),'url'=>$post_url,'pic'=>$thumb,'tags'=>wp_get_post_tags($post_id),'excerpt'=>$excerpt);
		$smcuser=new smcUser($user_id);
		$weibo_sync=array();
		if(isset($_POST['smc-user']) && isset($_POST['sycnsocialmedia']))
			$weibo_sync=$_POST['sycnsocialmedia'];
		else{//后台发布
			$weibo_array=$this->get_post_bind($post_id,$user_id);
			foreach($weibo_array as $weibo_slug=>$weibo_cfg){
				if(!$weibo_cfg['sync']&&!$weibo_cfg['timeout'])
					$weibo_sync[]=$weibo_slug;
			}
		}
		try{
			smcException::$throw_exception=!!$option['throw_exception'];
			$smcuser->publish_post($weibo_sync,$weibo_data);
		}catch(smcException $m){wp_die($m->getMessage());}
	}
	
	/**
	 * 获取文章的缩略图地址
	 *
	 * @param object $post 文章实例
	 * @return string $src 图片的src
	 */
	public function get_post_thumb_url($post=false){
		if(!$post)global $post;
		$thumb_id=get_post_meta($post->ID,'_thumbnail_id',true);
		if($thumb_id){
			$timthumbs=wp_get_attachment_image_src($thumb_id,'full');
			$src=$timthumbs[0];
		}else{
			preg_match('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post->post_excerpt.$post->post_content, $matches);
			if($matches[1]){
				$src=$matches[1];
			}
		}
		return $src;
	}
	
	/**
	 * 获取短链接
	 *
	 * @param string $service 链接缩短服务商简称
	 * @param string $url 原来的长连接
	 * @param string $post_id 文章id
	 * @return string $shorturl 缩短后的链接
	 */
	public function get_short_url($service,$url,$_post){
		$http=new WP_Http;
		$shorturl='';
		switch($service){
			case 'baidudwz':
					$response=$http->request('http://dwz.cn/create.php',array('method' => 'POST','body'=>array('url'=>$url)));
					if(is_array($response)){
						$response=json_decode($response['body']);
						if($response->status=='0'){
							$shorturl=$response->tinyurl;
						}
					}
					break;
			case '126am':
					$response=$http->request('http://126.am/api!shorten.action',array('method' => 'POST','body'=>array('key'=>'58961a78da5142cda1e55fbed914ae3a','longUrl'=>$url)));
					if(is_array($response)){
						$response=json_decode($response['body']);
						if($response->status_code==200){
							$shorturl=esc_url($response->url);
						}
					}
					break;
			case 'bitly':
					$response=$http->request('http://api.bitly.com/v3/shorten?login=qiqiboy&apiKey=R_580153ea12cdeedc598e81f486d10a14&format=json&longUrl='.$url);
					if(is_array($response)){
						$response=json_decode($response['body']);
						if($response->status_code=='200'){
							$shorturl=$response->data->url;
						}
					}
					break;
			case 'sinaurl':
					$response=$http->request('http://api.t.sina.com.cn/short_url/shorten.json?source=3033277072&url_long='.urlencode($url));
					if(is_array($response)){
						$response=json_decode($response['body']);
						if(!$response->error){
							$shorturl=$response[0]->url_short;
						}
					}
					break;
			case 'wp_short':
			default:$shorturl=get_option('home').'/';
				switch($_post->post_type){
					case 'post':
						$shorturl.='?p='.$_post->ID;
						break;
					case 'page':
						$shorturl.='?page_id='.$_post->ID;
						break;
					default:$shorturl.="?{$_post->post_type}={$_post->post_name}";
				}
		}
		if(strpos($shorturl,'http://')===0 || strpos($shorturl,'https://')===0)return $shorturl;
		else return $this->get_short_url('wp_short',$url,$_post);
	}
	
	/**
	 * 注册一个用户
	 *
	 * @param array $r 从社交网站获取到的用户信息
	 */
	public function insert_new_user($r){
		if(!is_array($r) || empty($r['identity']) || empty($r['weibo_slug']))
			throw new smcException('注册失败！');
		$option=$this->option;
		$auto_register=!!$option['auto_register'];
		$user_id=smcUser::get_current_user_id();
		if($smcuser=smcUser::get_user_by_smcdata('smcidentity_'.$r['weibo_slug'],$r['identity'])){
			$smcuser=$smcuser[0];
			$user_id=$smcuser->user_id;
		}
		$can_follow=!!($option['add_follow'] && $option['syncaccount'] && in_array('addfollow',$this->get_supports($r['weibo_slug'])));
		if(empty($user_id)){
			try{
				$user_login=preg_replace('/\s+/','_',sanitize_user($r['user_login'],$auto_register));
				$user_email=$r['real_email']?$r['real_email']:$r['email'];
				$password=$r['password'];
				$user_email=apply_filters('user_registration_email',$user_email);
				if(!$auto_register && empty($_POST) && empty($r['real_email'])){
					$user_email='';
					throw new smcFormException(__('<strong>提示</strong>:请完善注册信息.'),'10000');
				}
				if(empty($user_login)){
					throw new smcFormException(__('<strong>ERROR</strong>: Please enter a username.'),'10001');
				}
				if(!validate_username($user_login)){
					throw new smcFormException(__('<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.'),'10002');
				}
				if(strlen($user_login)>12){
					throw new smcFormException(__('<strong>ERROR</strong>: This length of this username is too long.(Max: 12)'),'10003');
				}
				if(username_exists($user_login)){
					throw new smcFormException(__('<strong>ERROR</strong>: This username is already registered, please choose another one.'),'10004');
				}
				if(empty($user_email)){
					throw new smcFormException(__('<strong>ERROR</strong>: Please type your e-mail address.'),'10005');
				}
				if(!is_email($user_email)){
					throw new smcFormException(__('<strong>ERROR</strong>: The email address isn&#8217;t correct.'),'10006');
				}
				if(email_exists($user_email)){
					throw new smcFormException(__('<strong>ERROR</strong>: This email is already registered, please choose another one.'),'10007');
				}
				$user_pass=empty($password)?wp_generate_password(12,false):$password;
				$user_id=wp_create_user($user_login,$user_pass,$user_email);
				if(!$user_id){
					throw new smcFormException('<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:'.get_option( 'admin_email' ).'">webmaster</a> !','10000');
				}
				wp_update_user(array('ID'=>$user_id,'nickname'=>$r['display_name'],'user_url'=>$r['url'],'description'=>$r['description'],'display_name'=>$r['display_name']));
				if(empty($password))
					update_user_option($user_id,'default_password_nag',true,true);
				$this->new_user_notification($user_id,$user_pass,$r);
				$this->new_user_email($r);
			}catch(smcFormException $m){
				$error_code=$m->getCode();
				$redirect_to=$_GET['smcredirect_to']?$_GET['smcredirect_to']:preg_replace('/[?&]code.*/','',smcClass::get_current_page_url());
				$insert_page=new smcPage('请填写必要的信息 - '.$this->get_weibo_name($r['weibo_slug']).'用户注册 &rsaquo; '.get_bloginfo('name'));
				$body='<div id="login_error">'.$m->getMessage().'</div>';
				$body.='<form id="smcregister" action="'.site_url('/?smcaction=smcregister&smcredirect_to='.$redirect_to).'" method="post">
							<p>
								<label>用户名 *'.($error_code=='10004'?'（是你的账号？<a target="_blank" onClick="try{window.opener.smcAction(\''.site_url('/wp-login.php').'\');window.open(\'\',\'_self\',\'\');window.close();return false;}catch(e){window.open(\'\',\'_self\',\'\');window.close();}" href="'.site_url('/wp-login.php').'">点此登陆</a>）':'').'<br/>
								<input type="text" name="user_login" id="user_login" class="input'.(in_array($error_code,array('10001','10002','10003','10004'))?' input-error':'').'" placeholder="必填，仅支持数字字母注册" value="'.esc_attr($user_login).'" maxLength="12" /></label>
							</p>
							<p>
								<label>电子邮件 *'.($error_code=='10007'?'（是你的邮箱？<a target="_blank" onClick="try{window.opener.smcAction(\''.site_url('/wp-login.php?action=lostpassword').'\');window.open(\'\',\'_self\',\'\');window.close();return false;}catch(e){window.open(\'\',\'_self\',\'\');window.close();}" href="'.site_url('/wp-login.php?action=lostpassword').'">点此找回账号</a>）':'').'<br/>
								<input type="email" name="email" id="user_email" class="input'.(in_array($error_code,array('10005','10006','10007'))?' input-error':'').'" placeholder="必填，您的真实邮箱" value="'.esc_attr($user_email).'" /></label>
							</p>
							<p>
								<label>网站<br />
								<input type="text" name="url" id="user_url" class="input" placeholder="您的博客或者微博地址" value="'.esc_url($r['url']).'" /></label>
							</p>
							<p>
							<label>密码<br />
								<input type="password" name="password" id="password" class="input" placeholder="您可以设置一个易记的密码" value="'.esc_attr($r['password']).'" /></label>
							</p>';
				if($can_follow)
					$body.='<p>
							<label>
								<input type="checkbox" name="add_follow" checked="checked" value="1" /> 关注站长微博</label>
							</p>';
				$body.=		'<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="注册" />
								<input type="hidden" name="profile_image_url" value="'.$r['profile_image_url'].'" />
								<input type="hidden" name="uid" value="'.$r['uid'].'" />
								<input type="hidden" name="display_name" value="'.$r['display_name'].'" />
								<input type="hidden" name="access_token" value="'.$r['access_token'].'" />
								<input type="hidden" name="refresh_token" value="'.$r['refresh_token'].'" />
								<input type="hidden" name="description" value="'.$r['description'].'" />
								<input type="hidden" name="statuses_count" value="'.$r['statuses_count'].'" />
								<input type="hidden" name="weibo_slug" value="'.$r['weibo_slug'].'" />
								<input type="hidden" name="expires_in" value="'.$r['expires_in'].'" />
								<input type="hidden" name="access_time" value="'.$r['access_time'].'" />
								<input type="hidden" name="identity" value="'.$r['identity'].'" />
							</p>
							<div style="clear:both;line-height:0;height:0"></div>
							<p style="float:right;-webkit-text-size-adjust:none;"><br/><a target="_blank" href="http://www.qiqiboy.com/plugins/social-medias-connect/">Powered by © Social Medias Connect.</a></p>
						</form>
						';
				$insert_page->show($body);
			}
		}
		$smcuser=new smcUser($user_id);
		$smcuser->set_smcdata($r);
		if($can_follow && $r['add_follow'])$smcuser->add_follow($r['weibo_slug'],$option['syncaccount']);
		wp_set_auth_cookie($user_id,!!$option['smc_auto_remember'],false);
		wp_set_current_user($user_id);
		if($redirect_to=$_GET['smcredirect_to']){
			$redirect_to.=(strpos($redirect_to,'?')===false?'?':'&').'smcregister=success';
			wp_redirect($redirect_to);
			exit;
		}
	}
	
	/**
	 * 新用户注册时通知管理员
	 *
	 * @param array $r 注册用户时的用户信息
	 * 
	 */
	public function new_user_email($r){
		if(!$this->option['smc_admin_notice'])return false;
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$name = $this->get_weibo_name($r['weibo_slug']);
		$uname = $r['display_name'];
		$ulogin = $r['user_login'];
		$uemail = $r['email'];
		$uurl = $r['url'];
		$subj = "新的{$name}连接用户注册 - $blogname";
		$body = "在 $blogname 新注册用户信息：\r\n";
		$body.= "用户名：$ulogin\r\n";
		$body.= "昵称：$uname\r\n";
		$body.= "邮箱：$uemail\r\n";
		$body.= "地址：$uurl\r\n";
		$body.= "\r\n";
		$body.= "-----------------------------------\r\n";
		$body.= "这是一封自动发送的邮件。 \r\n";
		$body.= "来自 {$blogname}。\r\n";
		$body.= "请不要回复本邮件。\r\n";
		$body.= "Powered by © Social Medias Connect。\r\n";
		$admin_email = get_option('admin_email');
		wp_mail($admin_email, $subj, $body, $headers = '');
	}
	
	/**
	 * 新用户注册时向其发送用户名、密码等邮件
	 *
	 * @param string $user_id 该用户的用户uid
	 * @param string $user_pass 该用户的密码
	 * @param array $r 该用户的个人信息
	 * 
	 */
	public function new_user_notification($user_id,$user_pass,$r){
		if(!$this->option['smc_user_notice'])return false;
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$name = $this->get_weibo_name($r['weibo_slug']);
		$user_login = stripslashes($r['user_login']);
		$user_email = stripslashes($r['real_email']?$r['real_email']:$r['email']);
		$subj = "您的用户名和密码 - $blogname";
		$body = "您使用{$name}账号在 $blogname 的注册信息：\r\n";
		$body.= "用户名：$user_login\r\n";
		$body.= "密码：$user_pass\r\n";
		$body.= "登陆地址：".site_url('/wp-login.php')."\r\n";
		$body.= "\r\n";
		$body.= "-----------------------------------\r\n";
		$body.= "这是一封自动发送的邮件。 \r\n";
		$body.= "来自 {$blogname}。\r\n";
		$body.= "请不要回复本邮件。\r\n";
		$body.= "Powered by © Social Medias Connect。\r\n";
		wp_mail($user_email, $subj, $body, $headers = '');
	}

	/**
	 * 获取当前页面地址
	 *
	 * @return string
	 */
	public static function get_current_page_url(){
		$current_page_url = 'http';
		if($_SERVER["HTTPS"] == "on"){
			$current_page_url .= "s";
		}
		$current_page_url .= "://";
		$current_page_url .= $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
		return $current_page_url;
	}
	
	public function show_message($smcException){
		if(is_admin() && !isset($_GET['code']) && !$_GET['socialmedia'] && !$_GET['oauth_token']){}
		else wp_die('<h3>来自社交媒体连接插件的消息：</h3>'.$smcException->getMessage());
	}
	
	public static function is_smc_bind_page(){
		return is_user_logged_in() && $_GET['page']=='social-medias-connect' && is_admin();
	}
	
	public static function weibo_time_since($timestamp){
		$since = time()-$timestamp; $gmt_offset = get_option('gmt_offset') * 3600;
		$prefix = $since>0?'前':'后'; $since=abs($since);
		$timestamp += $gmt_offset; $current_time = mktime() + $gmt_offset;
		if(floor($since/3600)){
			if(gmdate('Y-m-d',$timestamp) == gmdate('Y-m-d',$current_time)){
				$output = '今天 ';
				$output.= gmdate('H:i',$timestamp);
			}else{
				if(gmdate('Y',$timestamp) == gmdate('Y',$current_time)){
					$output = gmdate('m月d日 H:i',$timestamp);
				}else{
					$output = gmdate('Y年m月d日 H:i',$timestamp);
				}
			}
		}else{
			if(($output=floor($since/60))){
				$output = $output.'分钟'.$prefix;
			}else $output = '刚刚';
		}
		return $output;
	}
	
	public function set_appkey_form(){
		try{
			$weibo_slug=$_GET['weibo_slug']; $appopt=get_option('smc_weibo_appkey');
			if(!$appopt)$appopt=array();
			if(!in_array($weibo_slug,$this->weibo_array) || !in_array('customappkey',$this->get_supports($weibo_slug))){
				wp_redirect(admin_url('?smcregister=success'));
				exit;
			}
			if(!isset($_POST['appkey'])&&!isset($_POST['appsecret']))
				throw new smcFormException('您正在设置'.$this->get_weibo_name($_GET['weibo_slug']).'的appkey');
			if(empty($_POST['appkey'])&&empty($_POST['appsecret'])){
				unset($appopt[$weibo_slug]);
				setcookie('smc_redirect_msg_'.COOKIEHASH,'您刚刚成功删除了'.$this->get_weibo_name($_GET['weibo_slug']).'的appkey和appsecret！',0,COOKIEPATH,COOKIE_DOMAIN);
			}else{
				$appopt[$weibo_slug]=array($_POST['appkey'],$_POST['appsecret']);
				setcookie('smc_redirect_msg_'.COOKIEHASH,'您刚刚成功设置了'.$this->get_weibo_name($_GET['weibo_slug']).'的appkey和appsecret！',0,COOKIEPATH,COOKIE_DOMAIN);
			}
			update_option('smc_weibo_appkey',$appopt);
			$config=$this->merge_appkey($this->config);
			$this->update_config($config);
			wp_redirect($this->get_menupage_url('social-medias-connect&smcregister=success'));
			exit;
		}catch(smcFormException $m){
			$appopt=$appopt[$weibo_slug];
			if($appopt){
				$appkey=$appopt[0];
				$appsecret=$appopt[1];
			}
			$error_code=$m->getCode();
			$appkeyform=new smcPage('设置'.$this->get_weibo_name($_GET['weibo_slug']).'的appkey &rsaquo; '.get_bloginfo('name'));
			$body='<div id="login_error">'.$m->getMessage().'</div>';
			$body.='<form id="smcsetappkey" action="'.admin_url("?smc_request=smcsetappkey&weibo_slug=$weibo_slug").'" method="post">
						<p>
							<label>Appkey(可能也叫appid) <br/>
							<input type="text" name="appkey" class="input" placeholder="您注册应用时得到的appkey" value="'.esc_attr($appkey).'" /></label>
						</p>
						<p>
							<label>AppSescret <br/>
							<input type="text" name="appsecret" class="input" placeholder="您注册应用时得到的appsecret" value="'.esc_attr($appsecret).'" /></label>
						</p>
						<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="提交" /></p>
					</form>';
			$appkeyform->show($body);
		}
	}
	
	/**
	 * 兼容转换，使2.0插件兼容早期版本插件
	 *
	 */
	public function vesion_compatible(){
		$users=get_users(array('meta_key'=>'smcdata'));
		foreach($users as $u){
			$uid=$u->ID;
			$smcdata=get_user_meta($uid,'smcdata',true);
			if(!isset($smcdata['socialmedia'])){
				$_smcdata=array(
					'socialmedia'=>array(
						$smcdata['smcweibo']=>array(
							"avatar" => $smcdata['avatar'],
							"name" => $smcdata['username'],
							"access_token" => $smcdata['oauth_access_token'],
							"refresh_token" => $smcdata['oauth_access_token_secret']
						)
					),
					'default'=>''
				);
				switch($smcdata['smcweibo']){
					case 'sinaweibo':$emailend='weibo.com';break;
					case 'qqweibo':$emailend='t.qq.com';break;
					case 'sohuweibo':$emailend='t.sohu.com';break;
					case '163weibo':$emailend='t.163.com';break;
					default:continue;
				}
				delete_user_meta($uid,'smc_weibo_email_bind');
				update_user_meta($uid,'smcdata',$_smcdata);
				update_user_meta($uid,'smcidentity_'.$smcdata['smcweibo'],md5($smcdata['username'].'@'.$emailend));
			}
		}
		update_option('smc_vesion_compatible','1');
	}

}

/**
 * 用户操作类
 * 
 */
class smcUser{
	public $smcdata;
	public $user_id;
	
	public function __construct($user_id=null){
		if($user_id===null){
			$user_id=self::get_current_user_id();
		}
		$this->user_id=$user_id;
		$this->smcdata=get_user_meta($this->user_id,'smcdata',true);
		$this->old_version=get_user_meta($this->user_id,'smcoldversion',true);
		$this->user_compatible();
	}
	
	public function user_compatible(){
		if($this->old_version!=SMC_VERSION){
			if(!is_string($this->old_version))$this->old_version='1.0';
			if(version_compare($this->old_version,'2.0.10')<0){
				$smcdata=$this->get_smcdata();
				if(count($smcdata['socialmedia'])>1){
					$this->unbind('qqweibo');
					$smcdata=$this->get_smcdata();
				}
				if(count($smcdata['socialmedia'])>1){
					$this->unbind('qqsns');
				}
			}
			update_user_meta($this->user_id,'smcoldversion',SMC_VERSION);
		}
	}
	
	/**
	 * 获取用户的绑定信息
	 * 
	 */
	public function get_smcdata(){
		return is_array($this->smcdata)&&$this->smcdata['socialmedia'] ? $this->smcdata : array('socialmedia'=>array(),'default'=>'');
	}
	
	/**
	 * 设置用户的绑定信息
	 * @param object $r 用户的注册信息
	 * 
	 */
	public function set_smcdata($r){;
		if(empty($this->user_id)){
			throw new smcException('你需要先登录才能进行账号绑定！');
		}
		$smcdata=$this->parse_verify_data($r);
		update_user_meta($this->user_id,'smcdata',$smcdata);
		update_user_meta($this->user_id,'smcidentity_'.$r['weibo_slug'],$r['identity']);
		$this->smcdata=$smcdata;
	}
	
	/**
	 * 取消某个社交网站的绑定
	 * @param string $weibo_slug 社交网站对应的简码
	 * 
	 */
	public function unbind($weibo_slug){
		$smcdata=$this->get_smcdata();
		unset($smcdata['socialmedia'][$weibo_slug]);
		$this->smcdata=$smcdata; 
		if($weibo_slug==$smcdata['default'])
			$smcdata['default']='';
		update_user_meta($this->user_id,'smcdata',$smcdata);
		delete_user_meta($this->user_id,'smcidentity_'.$weibo_slug);
	}
	
	/**
	 * 格式化用户注册信息
	 * @param object $r 用户注册信息
	 * 
	 */
	public function parse_verify_data($r){
		$info=array(
			"avatar" => $r['profile_image_url'],
			"uid" => $r['uid'],
			"name" => $r['display_name']?$r['display_name']:$r['user_login'],
			'expires_in' => $r['expires_in'],
			'access_time' => $r['access_time'],
			"access_token" => $r['access_token'],
			"refresh_token" => $r['refresh_token'],
			"openid" => $r['refresh_token']
		);
		if($r['openid'])$info['openid']=$r['openid'];
		$smcdata=$this->get_smcdata();
		$smcdata['socialmedia'][$r['weibo_slug']]=$info;
		return $smcdata;
	}
	
	/**
	 * 获取当前用户id，未登录返回0
	 * 
	 */
	public static function get_current_user_id(){
		$user=wp_get_current_user(); 
		return isset($user->ID)?(int)$user->ID:0;
	}
	
	/**
	 * 根据绑定信息获取用户
	 * @param string $metakey metakey
	 * @param string $metavalue metavalue
	 * @return object 获取到的所有用户
	 * 
	 */
	public static function get_user_by_smcdata($meta_key,$meta_value){
		global $wpdb;
  		$sql="SELECT user_id, user_login FROM $wpdb->users u LEFT JOIN $wpdb->usermeta um on um.user_id = u.ID WHERE um.meta_key = '%s' AND um.meta_value = '%s'";
 		return $wpdb->get_results($wpdb->prepare($sql, $meta_key, $meta_value));
	}
	
	/**
	 * 设置某个社交网站为默认
	 * @param string $weibo_slug 社交网站对应的简码
	 * 
	 */
	public function set_default_weibo($weibo_slug){
		$smcdata=$this->get_smcdata();
		$smcdata['default']=$weibo_slug;
		update_user_meta($this->user_id,'smcdata',$smcdata);
		$this->smcdata=$smcdata;
	}
	
	/**
	 * 更新用户的授权信息
	 * @param string $weibo_slug 社交网站对应的简码
	 * @param object $newtoken 用户获取的授权
	 * @return boolean 成功true，失败false
	 */
	public function refresh_oauth_token($weibo_slug, $newtoken){
		$smcdata=$this->get_smcdata();
		if($smcdata['socialmedia'][$weibo_slug]){
			$smcdata['socialmedia'][$weibo_slug]=array_merge($smcdata['socialmedia'][$weibo_slug],$newtoken);
			update_user_meta($this->user_id,'smcdata',$smcdata);
			$this->smcdata=$smcdata;
			return true;
		}
		return false;
	}
	
	/**
	 * 获取oauth_token
	 * @param string $weibo_slug 社交网站对应的简码
	 * @return object 返回oauth_token数组
	 */
	public function get_oauth_token($weibo_slug){
		$smcdata=$this->get_smcdata();
		if($d=$smcdata['socialmedia'][$weibo_slug]){
			return array($d['access_token'],$d['refresh_token'],$d['openid']);
		}
		return false;
	}
	
	/**
	 * 获取头像
	 * @param int $size 头像大小
	 * @param string $avatar 默认的gravatar头像
	 * @return string $avatar 头像图片 <img src="" width="$size" height="$size" />
	 */
	public function get_avatar($size='32',$avatar){
		$smcdata=$this->get_smcdata();
		$binds=$smcdata['socialmedia'];
		$df=$smcdata['default'];
		if(empty($binds)) return $avatar;
		$d=array_key_exists($df,$binds)?$binds[$df]:reset($binds);
		$out=$d['avatar'];
		if(!$out)return $avatar;
		$avatar="<img alt='' src='{$out}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
		return $avatar;
	}
	
	/**
	 * 发布文章
	 * @param array $weibo_array 要发布到的社交网站数组
	 * @param array $weibo_data 要发布的内容
	 * 
	 */
	public function publish_post($weibo_array,$weibo_data){
		$smcdata=$this->get_smcdata();
		foreach($weibo_array as $weibo_slug){
			if($sd=$smcdata['socialmedia'][$weibo_slug]){
				$weibo=new smcWeibo($weibo_slug);
				$resp=$weibo->publish_post($weibo_data,$sd['access_token'],$sd['refresh_token'],$sd['openid']);
				if($resp&&!smcHttp::$COMMENT){
					$post_id=$weibo_data['id'];
					if(!update_post_meta($post_id,'_'.$weibo_slug.'_sync',$resp))
						add_post_meta($post_id,'_'.$weibo_slug.'_sync',$resp,true);
				}
			}
		}
	}
	
	public function add_follow($weibo_slug,$admin_id=''){
		if(empty($admin_id))return false;
		$adminuser=new smcUser($admin_id);
		$admindata=$adminuser->get_smcdata();
		$smcdata=$this->get_smcdata();
		if(($sd=$smcdata['socialmedia'][$weibo_slug]) && ($ad=$admindata['socialmedia'][$admindata['socialmedia']['default']])){
			$weibo=new smcWeibo($weibo_slug);
			$weibo->add_follow($ad['uid'],$sd['access_token'],$sd['refresh_token']);
		}
	}
	
}

/**
 * 单个社交网站操作类
 * 
 */
class smcWeibo{
	public $weibo;
	public $oauth;
	public $error_info;
	public $consumer_key;
	public $consumer_secret;
	public $token_array;
	
	function __construct($weibo_slug=''){
		global $SMC;
		if(!in_array($weibo_slug,$SMC->weibo_array))
			throw new smcException('该网站已经不被支持！');
		$this->weibo=$weibo_slug;
		$appkeys=$SMC->get_key_and_secret($weibo_slug);
		if(!$appkeys[0]||!$appkeys[1]){
			throw new smcException('appkey和appSecret缺少！');
		}
		$this->consumer_key=$appkeys[0];
		$this->consumer_secret=$appkeys[1];
		$this->oauth=$SMC->get_weibo_OAuth_name($this->weibo);
		if(!class_exists($this->oauth)){
			$oauth_file=dirname(__FILE__).'/'.$this->weibo.'/'.$this->oauth.'.php';
			if(!file_exists($oauth_file))throw new smcException('插件文件丢失，请重新安装插件！');
			include $oauth_file;
		}
	}
	
	/**
	 * 引导用户授权
	 * 
	 */
	function request_token($callback_url=''){
		global $SMC;
		$to=new $this->oauth($this->consumer_key,$this->consumer_secret);
		if($SMC->is_custom_appkey($this->weibo)){
			if(!$_COOKIE['smcRedirect_uri_'.COOKIEHASH]){
				wp_redirect(smcClass::get_current_page_url());
				exit;
			}
			$callback_url=$_COOKIE['smcRedirect_uri_'.COOKIEHASH];
			$to->is_custom=true;
		}
		$request_link=$to->getAuthorizeURL($callback_url);
		setcookie('smcWeibo_'.COOKIEHASH,$this->weibo,0,COOKIEPATH,COOKIE_DOMAIN);
		wp_redirect($request_link);
		exit;
	}
	
	/**
	 * 获取授权的access_token
	 * @param string $code 授权后获取到的oauth_token或者code
	 * @param string $callback_url 回调地址
	 * @param string $open_id openid授权时的openid
	 * 
	 */
	function getAccessToken($code, $callback_url, $openid=''){
		global $SMC;
		$to=new $this->oauth($this->consumer_key,$this->consumer_secret,$code);
		if($SMC->is_custom_appkey($this->weibo)){
			$callback_url=$_COOKIE['smcRedirect_uri_'.COOKIEHASH];
			$to->is_custom=true;
		}
		$token=$to->getAccessToken($code, $callback_url);
		if($token['error']){
			throw new smcException($token['error']);
		}else{
			if(!empty($openid))$token['openid']=$openid;
			$this->token_array=$token;
		}
		setcookie('smcWeibo_'.COOKIEHASH,'',time()-1,COOKIEPATH,COOKIE_DOMAIN);
		setcookie('smcRedirect_uri_'.COOKIEHASH,'',time()-1,COOKIEPATH,COOKIE_DOMAIN);
	}
	
	/**
	 * 获取用户信息
	 * 
	 */
	function get_verify_credentials(){
		$to=new $this->oauth($this->consumer_key,$this->consumer_secret,$this->token_array['access_token'],$this->token_array['refresh_token'],$this->token_array['openid']);
		$r=array_merge($to->verify_credentials(),$this->token_array);
		if(empty($r)||empty($r['uid']))
			throw new smcException('身份信息获取失败！');
		global $SMC;
		$r['identity']=md5($r['email']);
		if(smcClass::is_smc_bind_page()){
			if($smcuser=smcUser::get_user_by_smcdata('smcidentity_'.$r['weibo_slug'],$r['identity'])){
				$smcuser=$smcuser[0];
				if($smcuser->user_id!=smcUser::get_current_user_id())throw new smcException('对不起，该微博已经绑定了其他帐号('.$smcuser->user_login.')');
			}
			$SMC->smcUser->set_smcdata($r);
			setcookie('smc_redirect_msg_'.COOKIEHASH, '您刚刚成功绑定了'.$SMC->get_weibo_name($r['weibo_slug']),0,COOKIEPATH,COOKIE_DOMAIN);
		}else{
			$SMC->insert_new_user($r);
		}
	}
	
	/**
	 * 用refresh_token刷新access_token
	 * @param string $weibo_slug 社交网站对应的简码
	 * 
	 */
	public static function refresh_token($weibo_slug){
		global $SMC;
		if(in_array($weibo_slug,$SMC->weibo_array)){
			$appkey=$SMC->get_key_and_secret($weibo_slug);
			$oauth_token=$SMC->smcUser->get_oauth_token($weibo_slug);
			if($oauth_token){
				$oauth=$SMC->get_weibo_OAuth_name($weibo_slug);
				if(!class_exists($oauth)){
					$oauth_file=dirname(__FILE__).'/'.$weibo_slug.'/'.$oauth.'.php';
					include $oauth_file;
				}
				$to=new $oauth($appkey[0],$appkey[1],$oauth_token[0],$oauth_token[1],$oauth_token[2]);
				if(method_exists($to,'refresh_access_token')){
					$resp=$to->refresh_access_token();
					if($resp['access_token']){
						return $SMC->smcUser->refresh_oauth_token($weibo_slug, $resp);
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * 同步内容到微博
	 * @param array $weibo_data 要同步的数据
	 * @param string $access_token access_token
	 * @param string $refresh_token null或者access_token_secret
	 * @return boolean 成功返回true，错误false
	 */
	public function publish_post($weibo_data,$access_token,$refresh_token,$openid){
		$to=new $this->oauth($this->consumer_key,$this->consumer_secret,$access_token,$refresh_token,$openid);
		if(method_exists($to,'publish_post')){
			return $to->publish_post($weibo_data);
		}
		return false;
	}
	
	public function add_follow($uid,$access_token,$refresh_token,$openid){
		$to=@new $this->oauth($this->consumer_key,$this->consumer_secret,$access_token,$refresh_token,$openid);
		if(method_exists($to,'add_follow')){
			return $to->add_follow($uid);
		}
		return false;
	}
}

/**
 * 通信类
 * 
 */
class smcHttp{
	public $json=false;
	public $ssl_verifypeer=false;
	public $useragent='social medias connect';
	public static $boundary = '';
	public static $COMMENT=false;
	
	public function oAuthRequest($url, $method, $parameters, $multi = false, $headers=array()) {
		if($this->oauth_1_header)$headers=$this->oauth_1_signature_headers($url, $method, $headers);
		elseif($this->oauth_1 && is_array($parameters))$parameters=array_merge($parameters,$this->oauth_1_signature_params($url,$method,$parameters));
		switch ($method) {
        case 'GET':
                if(is_array($parameters) || is_object($parameters)){
				    $url = $url . '?' . http_build_query($parameters);
                }
				return $this->http($url, 'GET', null, $headers);
			default:
				if(!$multi && (is_array($parameters) || is_object($parameters)) ) {
					$body = http_build_query($parameters);
				}else{
					$body = self::build_http_query_multi($parameters);
					if(empty($headers['Content-Type']))
						$headers['Content-Type'] = "multipart/form-data; boundary=" . self::$boundary;
					if(empty($headers['Expect']))
						$headers['Expect'] = "";
				}
				return $this->http($url, $method, $body, $headers);
		}
	}
	
	public function http($url, $method, $postfields = NULL, $headers = array()){
		$http=new WP_Http;
		$response=$http->request($url,array(
			"method"=>$method,
			"timeout"=>50,
			"sslverify"=>$this->ssl_verifypeer,
			"user-agent"=>$this->useragent,
			"body"=>$postfields,
			"headers"=>$headers
		));
		if(is_object($response)){
			if(smcException::$throw_exception)
				throw new smcException('<h4>通信失败</h4>原因：<ol>'.self::fetch_error_message($response).'<ol>');
			else return false;
		}
		return $this->json?json_decode(trim($response['body']),true):$response['body'];
	}
	
	public static function fetch_error_message($resp,$error_string=''){
		if(is_object($resp))
			$resp=get_object_vars($resp);
		if(is_array($resp))
			foreach($resp as $error)
				$error_string=self::fetch_error_message($error,$error_string);
		elseif(is_scalar($resp))
			$error_string.='<li>'.$resp.'</li>';
		return $error_string;
	}
	
	public static function build_http_query_multi($params) {
		if (!$params) return '';
		if (is_string($params)) return $params;
		uksort($params, 'strcmp');
		$pairs = array();
		self::$boundary = $boundary = uniqid('------------------');
		$MPboundary = '--'.$boundary;
		$endMPboundary = $MPboundary. '--';
		$multipartbody = '';

		foreach ($params as $parameter => $value) {
			if(in_array($parameter, array('pic', 'image'))) {
				$url = ltrim( $value, '@' );
				$content = file_get_contents( $url );
				$filename = reset(explode('?' , basename($url)));

				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"'. "\r\n";
				$multipartbody .= "Content-Type: image/unknown\r\n\r\n";
				$multipartbody .= $content. "\r\n";
			}else{
				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'content-disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
				$multipartbody .= $value."\r\n";
			}
		}
		$multipartbody .= "$endMPboundary\r\n";
		return $multipartbody;
	}
	
	public static function generate_nonce() { 
		$mt = microtime(); 
        $rand = mt_rand(); 
        return md5($mt . $rand); 
    }
	
	public function oauth_1_signature_params($url,$method,$params){
		if(isset($params['pic'])){
            unset($params['pic']); 
        }
        if(isset($params['image'])){ 
            unset($params['image']); 
		}
		if(isset($params['oauth_signature'])){
			unset($params['oauth_signature']);
		}
		$params['oauth_consumer_key']=$this->client_id;
		$params['oauth_signature_method']='HMAC-SHA1';
		$params['oauth_timestamp']=time();
		$params['oauth_version']='1.0';
		$params['oauth_nonce']=self::generate_nonce();
		if($this->access_token)$params['oauth_token']=$this->access_token;
		uksort($params,'strcmp'); $params=self::urlencode_rfc3986($params);
		$base_string=strtoupper($method).'&'.self::urlencode_rfc3986($url);
		$count=0;
		foreach($params as $key=>$value){
			$base_string.=(!$count++?'&':'%26').self::urlencode_rfc3986($key).'%3D'.self::urlencode_rfc3986($value);
		}
		$oauth_signature=base64_encode(hash_hmac('sha1', $base_string, self::urlencode_rfc3986($this->client_secret).'&'.self::urlencode_rfc3986(($this->refresh_token?$this->refresh_token:'')), true)); 
		$params['oauth_signature']=$oauth_signature;
		$params=self::urldecode_rfc3986($params);
		return $params;
	}
	
	public function oauth_1_signature_headers($url,$method,$headers){
		$sign_params=$this->oauth_1_signature_params($url,$method,array());
		$sigheader=array();
		foreach($sign_params as $key=>$value){
			$sigheader[]="$key=\"$value\"";
		}
		$headers['authorization']=implode(',',$sigheader);
		return $headers;
	}
	
	public static function urlencode_rfc3986($string){
		if(is_array($string)){
			return array_map(array(self,'urlencode_rfc3986'),$string);
		}elseif(is_scalar($string)){
			return str_replace( 
                '+', 
                ' ', 
                str_replace('%7E', '~', rawurlencode($string)) 
            ); 
		}else{
		 	return '';
		}
	}
	
	public static function urldecode_rfc3986($string){
		if(is_array($string)){
			return array_map(array(self,'urldecode_rfc3986'),$string);
		}elseif(is_scalar($string)){
			return rawurldecode($string);
		}else{
		 	return '';
		}
	}
	
	/**
	 * 将文章标签转为微博标签
	 * @param array $tags 标签数组
	 * @param boolean flag true表示#tag#形式，false表示#tag
	 * @return string $output 拼接好的微博标签字符串
	 */
	public static function convtags($tags,$flag=true){
		$output=''; $count=1;
		if(!is_array($tags))$output=$tags;
		foreach($tags as $tag){
			$tagname=__($tag->name);
			if($flag)$tagname=str_ireplace(' ','-',$tagname);
			$output.='#'.$tagname.($flag?'#':' ');
			if($count++==2)break;
		}
		return $output;
	}
	
	/**
	 * 格式化要同步的内容
	 * @param array $weibo_data array('url'=>,'title'=>,'tags'=>,'excerpt'=>,'comment'=>)
	 * @param int $length 返回的字符串长度
	 * @param boolean $utf 是否计算单字节字
	 * @return string
	 */
	public static function format_post_data($weibo_data,$length=138,$utf=true){
		global $SMC;
		$option=$SMC->option;
		$format=smcHttp::$COMMENT?$option['smc_comment_format']:$option['smc_post_format'];
		$format_array=array('url'=>$weibo_data['url'],'title'=>$weibo_data['title'],'tags'=>$weibo_data['tags'],'excerpt'=>$weibo_data['excerpt'],'comment'=>$weibo_data['comment']);
		$start_length=smcHttp::str_len(preg_replace('/%%title%%|%%url%%|%%tags%%|%%excerpt%%|%%comment%%/','',$format),$utf);
		foreach($format_array as $ft=>$str){
			$str_len=smcHttp::str_len($str,$utf);
			if($start_length+$str_len>$length){
				$str_len=$length-$start_length;
				if($str_len<0)$str_len=0;
				$str=smcHttp::sub_str($str,0,$str_len,$utf);
			}
			$format=str_ireplace("%%$ft%%",$str,$format);
			$start_length+=$str_len;
		}
		return smcHttp::sub_str($format,0,$length+1,$utf);
	}
	
	/**
	 * 字符串长度计算
	 * @param string $str 要统计的字符串
	 * @param boolean $utf 是否合并单字节字符（即两个英文或数字当成一个）
	 * @return int 返回字符串长度
	 */
	public static function str_len($str='',$utf=false){
		$length=strlen(preg_replace('/[\x00-\x7F]/', '', $str));
		if($utf){
			$length=$length?(strlen($str)-$length)/2+intval($length/3):round(strlen($str)/2);
		}else{
			$length=$length?strlen($str)-$length+intval($length/3):strlen($str);
		}
		return intval($length);
	}
	
	/**
	 * 字符串长度截取
	 * @param string $str 要截取的字符串
	 * @param int $from 截取开始位置
	 * @param int $length 截取长度
	 * @param string $old 旧的字符串
	 * @param boolean $utf 是否计算单字节字符
	 * @return string 返回截取的字符串
	 */
	public static function sub_str($str, $from=0, $length=0, $utf=false, $old=''){
		$pa="/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
		if(is_array($str)){
			$str_array=$str;
			$str=join('',$str_array[0]);
		}else preg_match_all($pa,$str,$str_array);
		if(smcHttp::str_len($str,$utf)>$length){
			$str_temp=join('',array_slice($str_array[0],$from,$length));
			if(($temp_length=($length-smcHttp::str_len($str_temp,$utf)))>0){
				return smcHttp::sub_str($str_array,$from+$length,$temp_length,$utf,$old.$str_temp);
			}
			return $old.$str_temp;
		}
		return $old.$str;
	}
	
	/**
	 * 获取IP
	 * @return string IP地址
	 */
	public static function get_client_ip(){
		if(getenv('HTTP_CLIENT_IP')){
			$client_ip = getenv('HTTP_CLIENT_IP');
		} elseif(getenv('HTTP_X_FORWARDED_FOR')) {
			$client_ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif(getenv('REMOTE_ADDR')) {
			$client_ip = getenv('REMOTE_ADDR');
		} else {
			$client_ip = $HTTP_SERVER_VARS['REMOTE_ADDR'];
		}
		return $client_ip;
	}
}

/**
 * 页面显示类
 * 
 */
class smcPage{
	public $title='社交媒体连接';
	function __construct($title){		
		$this->title=$title;
	}
	function _header(){
		global $SMC, $is_iphone;
		echo '<!DOCTYPE html>
   				<head>
        			<title>'.$this->title.'</title>
        			<meta charset="UTF-8" />
			';
        wp_admin_css( 'login', true );
        wp_admin_css( 'colors-fresh', true );
        if($is_iphone) echo '
					<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
					<style type="text/css" media="screen">
					form { margin-left: 0px; }
					#login { margin-top: 20px; }
					</style>
		';else echo '<script type="text/javascript" src="'.$SMC->base_dir.'/js/smc-shake_js.js"></script>';
		echo '
					<style type="text/css">
						#login{
							padding-top:40px;
							margin-top:0;
						}
						.login form .input-error{
							background:#FFEBE8;
							border-color:#C00;
						}
						.login label{
							font-size:12px;
						}
						.login form .input{
							font-size:18px;
							line-height:24px;
						}
					</style>
   		 		</head>';
	}
	function _body($body=''){
		echo '<body class="login">
				<div id="login"><h1><a target="_blank" href="http://www.qiqiboy.com/products/plugins/social-medias-connect" title="社交媒体连接">社交媒体连接</a></h1>';
		echo $body;
		echo '	</div>';
	}
	function _footer(){
		echo '	<script type="text/javascript">
					if(typeof wpOnload=="function")wpOnload();
				</script>
			</body>
		</html>';
	}
	function show($body=''){
		$this->_header();
		$this->_body($body);
		$this->_footer();
		exit;
	}
}