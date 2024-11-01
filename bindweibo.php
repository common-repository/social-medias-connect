<div class="wrap" style="-webkit-text-size-adjust:none;">
	<div id="icon-socialmedia" class="icon32"><img class="border-radius-5" src="<?php echo $this->base_dir.'/images/smc_logo.png' ?>" alt="" /><br></div>
    <h2 class="nav-tab-wrapper">
    	<a href="<?php echo $this->get_menupage_url('social-medias-connect'); ?>" class="nav-tab nav-tab-active">账号绑定</a>
        <?php if($this->is_admin_access()): ?>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_option'); ?>" class="nav-tab">插件设置</a>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_uninstall'); ?>" class="nav-tab">插件卸载</a>
        <?php endif; ?>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_help'); ?>" class="nav-tab">帮助</a>
    </h2>
    <div id="smc-admin-bind">
<?php
	$smctable=new WP_SMC_List_Table;
	$smctable->prepare_items();
	$smctable->views();
	$smcdata=$this->smcUser->get_smcdata();
	$bindarray=array_keys($smcdata['socialmedia']);
	$default_weibo=$smcdata['default'];
?>
	<form id="posts-filter" action="" method="get">
    <p class="search-box">
	<label class="screen-reader-text" for="media-search-input">默认微博:</label>
	<select name="default_weibo">
    	<option value="">设置默认显示微博</option>
    <?php foreach($bindarray as $weibo_slug): ?>
    	<option <?php if($default_weibo==$weibo_slug)echo 'selected="selected" ';; ?>value="<?php echo $weibo_slug; ?>"><?php echo $this->get_weibo_name($weibo_slug); ?></option>
    <?php endforeach; ?>
    </select>
	<input type="submit" class="button" value="设置">
    </p>
<?php $smctable->display(); ?>
	<div id="ajax-response"></div>
    <input type="hidden" name="smcaction" value="bindpage" />
	</form>
    <div class="smc-footer">
    	<h3>[支持功能]说明</h3>
        <ul>
        	<li><img align="top" src="<?php echo $this->base_dir.'/images/connect.png' ; ?>" /> 支持登录注册</li>
            <li><img src="<?php echo $this->base_dir.'/images/syncpost.png' ; ?>" /> 支持文章和评论同步</li>
            <li><img src="<?php echo $this->base_dir.'/images/timeline.png' ; ?>" /> 支持显示我的微博</li>
            <li><img src="<?php echo $this->base_dir.'/images/customappkey.png' ; ?>" /> 支持自定义appkey(显示来源字段)</li>
            <li><img src="<?php echo $this->base_dir.'/images/addfollow.png' ; ?>" /> 支持注册时对网站官方账号加关注</li>
        </ul>
    </div>
	</div>
</div>
