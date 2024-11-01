<?php 
class smc_sidebar_login_widget extends WP_Widget{
	function smc_sidebar_login_widget(){
		$widget_des = array('classname'=>'smc-login-widget','description'=>'显示微博连接登陆按钮');
		$this->WP_Widget(false,'社交媒体网站连接登陆',$widget_des);
	}
	function form($instance){
		$instance = wp_parse_args((array)$instance,array(
			'title'=>'社交网站连接登陆',
			'icon_size'=>24,
			'desc'=>''
		));
		echo '<p><label for="'.$this->get_field_name('title').'">标题: <br/><input class="widefat" name="'.$this->get_field_name('title').'" type="text" value="'.htmlspecialchars($instance['title']).'" /></label></p>';
		echo '<p><label for="'.$this->get_field_name('icon_size').'">图标大小: <br/><input class="widefat" name="'.$this->get_field_name('icon_size').'" type="text" value="'.htmlspecialchars($instance['icon_size']).'" /></label></p>';
		echo '<p><label for="'.$this->get_field_name('desc').'">描述信息(支持html标签): <textarea rows="5" cols="20" name="'.$this->get_field_name('desc').'" class="widefat">'.htmlspecialchars($instance['desc']).'</textarea></label></p>';
	}
	function update($new_instance,$old_instance){
		$instance = $old_instance;
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		$instance['icon_size'] = stripslashes($new_instance['icon_size']);
		$instance['desc'] = stripslashes($new_instance['desc']);
		return $instance;
	}
	function widget($args,$instance){
		global $SMC;
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$icon_size = (int)$instance['icon_size'];
		$desc = $instance['desc'];
		echo $before_widget;
		if($title)echo $before_title . $title . $after_title;
		echo $desc;
		$SMC->smc_print_weibo(array('size'=>22,
			'login_format'=>$desc.'<p class="smc-login-area" id="smc-login-area%%id%%">%%smc%%</p>',
			'sync_format'=>'<p class="smc-login-area" id="smc-login-area%%id%%">您已经登陆</p>'
		));
		echo $after_widget;
	}
}

function smc_sidebar_widget_init(){
	register_widget('smc_sidebar_login_widget');
}

add_action('widgets_init','smc_sidebar_widget_init');

?>