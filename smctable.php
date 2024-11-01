<?php
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class WP_SMC_List_Table extends WP_List_Table{
	public $all_num;
	public $bind_num;
	public $bind_array;
	
	function __construct() {
		parent::__construct( array(
			'plural' => 'smclists',
		) );
	}

	function prepare_items(){
		global $SMC; $items=array();
		$smcdata=$SMC->smcUser->get_smcdata();
		$this->bind_array=array_keys($smcdata['socialmedia']);
		$this->all_num=count($SMC->weibo_array);
		$this->bind_num=count($this->bind_array);
		if($_GET['filter']=='binded'){
			$arrays=$this->bind_array;
		}elseif($_GET['filter']=='unbind'){
			$arrays=array_diff($SMC->weibo_array,$this->bind_array);
		}else{
			$arrays=$SMC->weibo_array;
		}
		$array_by_name=array();
		$array_by_access_time=array();
		$array_by_expires_in=array();
		foreach($arrays as $weibo_slug){
			if($d=$smcdata['socialmedia'][$weibo_slug]){
				$array_by_access_time[$weibo_slug]=$d['access_time'];
				$array_by_expires_in[$weibo_slug]=intval($d['expires_in'])?intval($d['expires_in'])+$d['access_time']:9999999999;
			}else{
				$array_by_access_time[$weibo_slug]=0;
				$array_by_expires_in[$weibo_slug]=99999999999;
			}
			$array_by_name[$weibo_slug]=$SMC->get_pinyin_of_weibo($weibo_slug);
		}
		$order=$_GET['orderby'];
		$orderby=$_GET['order'];
		if($order=='e'){
			$arrays=$array_by_expires_in;
			$orderby=='desc'?asort($arrays):arsort($arrays);
			$arrays=array_keys($arrays);
		}elseif($order=='t'){
			$arrays=$array_by_access_time;
			$orderby=='asc'?asort($arrays):arsort($arrays);
			$arrays=array_keys($arrays);
		}elseif($order=='n'){
			$arrays=$array_by_name;
			$orderby=='asc'?arsort($arrays):asort($arrays);
			$arrays=array_keys($arrays);
		}
		foreach($arrays as $weibo_slug){
			$items[$weibo_slug]=array('name'=>$SMC->get_weibo_name($weibo_slug));
		}
		
		$this->_column_headers = array( 
			 $this->get_columns(),
			 array(),
			 $this->get_sortable_columns()
		);
		$this->items=$items;
	}

	function no_items() {
		if(isset($_GET['filter']))
			echo '没有项目';
		else echo '对不起，配置错误！';
	}

	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = '解除绑定';

		return $actions;
	}

	function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'icon'       => '',
			'name'       => '社交网站',
			'time'       => '绑定时间',
			'expires' 	 => '授权过期',
			'mk'         => '操作',
			'support'	 => '支持功能'
		);
	}

	function get_sortable_columns(){
		return array(
			'name'    => 'name',
			'time'    => 'time',
			'expires' => 'expires',
			'least'   => 'least'
		);
	}
	
	function get_views(){
		global $SMC;
		$total=$this->all_num;
		$bind=$this->bind_num;
		return array(
			'all'        => '<a '.($_GET['filter']==''?'class="current" ':'').'href="'.$SMC->get_menupage_url($_GET['page']).'">全部('.$total.')</a>',
			'binded'     => '<a '.($_GET['filter']=='binded'?'class="current" ':'').'href="'.$SMC->get_menupage_url($_GET['page']).'&filter=binded">已绑定('.$bind.')</a>',
			'unbind'     => '<a '.($_GET['filter']=='unbind'?'class="current" ':'').'href="'.$SMC->get_menupage_url($_GET['page']).'&filter=unbind">未绑定</a>'
		);
	}
	
	function display_rows(){
		global $SMC;
		$alt = 0; $curent_url=$SMC->get_menupage_url('social-medias-connect');
		$smcdata=$SMC->smcUser->get_smcdata();
		$sms=$smcdata['socialmedia']; $default_weibo=$smcdata['default'];
		$gmt_offset = get_option('gmt_offset') * 3600;
		$super_adminer=$SMC->is_admin_access();
		$appopt=get_option('smc_weibo_appkey');
		if(!$appopt)$appopt=array();
		foreach($this->items as $weibo_slug=>$weibo){
			$style=($alt++%2)?'':' class="alternate"';
			$cfg=$sms[$weibo_slug]?$sms[$weibo_slug]:array();
			$access_time=empty($cfg)?'--':smcClass::weibo_time_since($cfg['access_time']);
			$expires_in=intval($cfg['expires_in'])?smcClass::weibo_time_since(intval($cfg['expires_in'])+$cfg['access_time']):(empty($cfg)?'--':'不会过期');
			$bind_link=$curent_url.'&socialmedia='.$weibo_slug.'&callback_url='.$curent_url;
			$pass=$cfg['expires_in']&&(intval($cfg['expires_in'])+$cfg['access_time'])<time();
			$custom_appkey=in_array('customappkey',$SMC->get_supports($weibo_slug))?"<a class='smc-start-bind' title='".($appopt[$weibo_slug]?"修改APPKEY":"设置APPKEY")."' href='".admin_url("?smc_request=smcsetappkey&weibo_slug=$weibo_slug")."'><input class='button-secondary set_appkey' type='button' value='".($appopt[$weibo_slug]?"修改APPKEY":"设置APPKEY")."' class='button-secondary' /></a>":
							"<input disabled class='button-secondary set_appkey' type='button' value='设置appkey' class='button-secondary' />";
?>
		<tr id="<?php echo $weibo_slug; ?>" valign="middle" <?php echo $style; ?>>
<?php	
			list( $columns, $hidden )=$this->get_column_info();
			foreach($columns as $column_name=>$column_display_name){
				$class="class='column-$column_name'";
				$style='';
				if(in_array($column_name, $hidden))
					$style=' style="display:none;"';
				$attributes=$class.$style;
				
				switch($column_name){
					case 'cb':
						echo '<th scope="row" class="check-column"><input type="checkbox" name="smccheck[]" value="'. $weibo_slug .'" /></th>';
						break;
					case 'icon':
						echo "<td $attributes><img class='border-radius-5' src='$SMC->base_dir/images/icons/$weibo_slug.png' alt='{$weibo['name']}' /></td>";
						break;
					case 'name':
						$avatar=$cfg&&$cfg['avatar']?'<img class="border-radius-5" src="'.$cfg['avatar'].'" width="16" height="16" />':'';
						echo "<td $attributes><strong><a class='row-title smc-start-bind' href='$bind_link' title='立即点击绑定" .$weibo['name']. "'>{$weibo['name']} $avatar</a></strong>";
						if($default_weibo==$weibo_slug)echo "(默认帐号)";
						echo "<br/>";
						$actions = array();
						if(in_array($weibo_slug,$this->bind_array))
							 $actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url( "?smcaction=bindpage&action=delete&amp;weibo_slug=$weibo_slug", 'delete-weibo_' . $weibo_slug ) . "' onclick=\"if ( confirm( '" . esc_js( sprintf( __( "确定解除 '%s' 的绑定？" ), $weibo['name'] ) ) . "' ) ) { return true;}return false;\">" ."解除绑定" . "</a>";
						else $actions['nobind'] = "未绑定";
						echo $this->row_actions( $actions );
						echo "</td>";
						break;
					case 'time':
						echo "<td $attributes>$access_time</td>";
						break;
					case 'expires':
						$passtyle=$pass?' style="color:#BC0B0B;"':'';
						echo "<td $attributes><span$passtyle>$expires_in</span><br/>";
						$actions = array();
						if($cfg['expires_in'])
							 $actions['refresh'] = "<a class='submitdelete' href='" . wp_nonce_url( "?smcaction=bindpage&action=refresh&amp;weibo_slug=$weibo_slug", 'refresh-weibo_' . $weibo_slug ) . "'>" ."刷新授权" . "</a>";;
						echo $this->row_actions( $actions );
						echo "</td>";
						break;
					case 'mk':
						$text=empty($cfg)?'立即绑定':'重新绑定';
						echo "<td $attributes><a class='row-title smc-start-bind' href='$bind_link' title='立即点击绑定" .$weibo['name']. "'><input type='button' value='$text' class='button-secondary' /></a>";
						if($super_adminer)echo ' '.$custom_appkey;
						echo "</td>";
						break;
					case 'support':
						echo "<td $attributes>";
						$supports=$SMC->get_supports($weibo_slug);
						foreach($supports as $support){
							echo "<img src='{$SMC->base_dir}/images/$support.png' /> ";
						}
						echo "</td>";
						break;
					default:
						echo "<td $attributes></td>";
						break;
			}
		}
?>
		</tr>
<?php
		}
	}
}

?>
