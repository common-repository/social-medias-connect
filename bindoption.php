<div class="wrap" style="-webkit-text-size-adjust:none;">
	<div id="icon-socialmedia" class="icon32"><img class="border-radius-5" src="<?php echo $this->base_dir.'/images/smc_logo.png' ?>" alt="" /><br></div>
    <h2 class="nav-tab-wrapper">
    	<a href="<?php echo $this->get_menupage_url('social-medias-connect'); ?>" class="nav-tab">账号绑定</a>
        <?php if($this->is_admin_access()): ?>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_option'); ?>" class="nav-tab nav-tab-active">插件设置</a>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_uninstall'); ?>" class="nav-tab">插件卸载</a>
        <?php endif; ?>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_help'); ?>" class="nav-tab">帮助</a>
    </h2>
    <div id="smc-admin-option">
<?php
	$option=$this->option;
	$weibo_array=$this->weibo_array;
?>	<form method="post" action="" />
		<input type="hidden" name="smcaction" value="bindoption" />
		<div class="postbox optionbox closed<?php if(!isset($_GET['box']) || $_GET['box']==1)echo ' smc-open'; ?>">
        	<div class="handlediv" title="点击以切换"><br></div>
            <h3 class="hndle"><span>网站连接注册</span></h3>
        	<div class="inside">
            	<table class="form-table">
                	<tbody>
                    	<tr valign="top">
                            <th scope="row"><label>允许连接注册的网站</label></th>
                            <td><div id="connect-list" class="overflow-hidden">
                            	<?php $connects=$option['connect'];
								foreach($weibo_array as $weibo_slug){
									$checked=in_array($weibo_slug,$connects)?'checked="checked" ':'';
									$is_checked=$checked?'checked ':'';
									echo '<span class="'.$is_checked.'weibo_item"><img src="'.$this->base_dir.'/images/icons/'.$weibo_slug.'.png" title="'.$this->get_weibo_name($weibo_slug).'" alt="'.$this->get_weibo_name($weibo_slug).'" /><input '.$checked.'name="connect[]" value="'.$weibo_slug.'" type="checkbox" /></span>';
								}
								?> <input type="hidden" name="connect[]" value="weibo" />
                                </div>
                                <span class="description">Tip: 点击图标进行选择或者取消选择</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>开启关注默认账号</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="add_follow"<?php if($option['add_follow'])echo ' checked="checked"'; ?> />开启加关注</label>
                                <br/><span class="description">Tip: 加关注是高级功能（需要付费开启，详见帮助页面），目前仅支持新浪微博和腾讯微博。请到文章设置里先设置下默认同步用户，该用户即为被关注账号。</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>登录按钮</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="auto_insert_form"<?php if($option['auto_insert_form'])echo ' checked="checked"'; ?> />自动插入</label>
                                <br/><span class="description">Tip: 选择是否自动在评论区域插入登录按钮</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>一键注册登录</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="auto_register"<?php if($option['auto_register'])echo ' checked="checked"'; ?> />自动注册</label>
                                <br/><span class="description">Tip: 如果勾选，使用第三方连接注册时，用户名和密码将会自动为用户生成，不会有用户确认完善注册信息这一步</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>自动登录</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="smc_auto_remember"<?php if($option['smc_auto_remember'])echo ' checked="checked"'; ?> />自动登录</label>
                                <br/><span class="description">Tip: 如果勾选，用户使用第三方登录后，以后再来还保持登录状态（类似wordpress登录时选择了记住密码）</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>邮件通知</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="smc_user_notice"<?php if($option['smc_user_notice'])echo ' checked="checked"'; ?> />通知注册用户</label>
                                <label><input type="checkbox" value="1" name="smc_admin_notice"<?php if($option['smc_admin_notice'])echo ' checked="checked"'; ?> />通知管理员</label>
                                <br/><span class="description">Tip: 选择是否在用户注册成功后用邮件通知网站管理员和用户</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>登录图标大小</label></th>
                            <td>
                            	<select name="icon_width">
                                <?php $min_width=12; while($min_width<=48){
										echo '<option '.($option['icon_width']==$min_width?'selected="selected" ':'').'value="'.$min_width.'">'.$min_width.'</option>';
										$min_width+=2;
									}
								 ?>
                                </select>
                                <span class="description">Tip: 选择登录按钮图标的大小</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>登陆组件放置格式</label></th>
                            <td>
                            	<textarea class="large-text code" rows="3" name="smc_login_format"><?php echo $option['smc_login_format']; ?></textarea>
                               <br/> <span class="description">Tip: %%id%% 保持组件唯一性的id，可能为空 %%smc%% 登陆组件按钮，格式为<?php echo htmlspecialchars('<a><span><img></span></a>'); ?></span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>登陆后同步组件放置格式</label></th>
                            <td>
                            	<textarea class="large-text code" rows="3" name="smc_sync_format"><?php echo $option['smc_sync_format']; ?></textarea>
                               <br/> <span class="description">Tip: %%id%% 保持组件唯一性的id，可能为空 %%smc%% 同步时的控件</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit"><input type="submit" name="submit_box1" class="button-primary" value="保存更改"></p>
            </div>
    	</div>
        
        <div class="postbox optionbox closed<?php if($_GET['box']==2)echo ' smc-open'; ?>">
        	<div class="handlediv" title="点击以切换"><br></div>
            <h3 class="hndle"><span>文章同步设置</span></h3>
        	<div class="inside">
            	<table class="form-table">
                	<tbody>
                    	<tr valign="top">
                            <th scope="row"><label>默认同步用户</label></th>
                            <td>
                            	<select name="syncaccount">
                                	<option value="0">选择默认进行同步的用户</option>
                                    <?php $users=get_users(array('role'=>'administrator'));
										foreach($users as $user){
											echo "<option ".($option['syncaccount']==$user->ID?"selected='selected' ":"")."value='$user->ID'>$user->display_name</option>";
										}
									?>
                                </select>
                                <span class="description">Tip: 默认同步用户，即发布文章时默认同步到该用户所绑定的社交网站上(只能设置管理员角色的用户)</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>支持的文章类型</label></th>
                            <td>
                            	<?php $types=smcClass::get_all_post_type(2); 
									foreach($types as $type){
										echo '<label><input type="checkbox" '.(in_array($type,$option['post_type'])?'checked="checked" ':'').'name="post_type[]" value="'.$type.'" /> '.$type.'</label> ';
									}
								?>
                                <br/><span class="description">Tip: 需要开启同步的文章类型请打上勾</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>短网址服务</label></th>
                            <td>
                            	<label><input type="checkbox" <?php if($option['smc_use_short'])echo 'checked="checked" '; ?>name="smc_use_short" value="1" /> 使用短网址</label>
                                <select name="smc_shorturl_service">
                                	<?php $short_array=array(
											'sinaurl'=>'新浪t.cn',
											'126am'=>'网易短网址126.am',
											'baidudwz'=>'百度短网址dwz.cn',
											'bitly'=>'Bit.ly',
											'wp_short'=>'Wordpress短链接'
										);
                                	foreach($short_array as $key=>$short_name){
										echo '<option '.($key==$option['smc_shorturl_service']?'selected="selected" ':'').'value="'.$key.'">'.$short_name.'</option>';
									}
									?>
                                </select>
                                <br/><span class="description">Tip: 开启短网址请勾选</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>文章同步格式</label></th>
                            <td>
                            	<textarea class="large-text code" rows="3" name="smc_post_format"><?php echo $option['smc_post_format']; ?></textarea>
                                <br/><span class="description">Tip: %%title%% 文章标题 %%url%% 文章链接 %%tags%% 文章标签 %%excerpt%%文章摘要</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>同步图片</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="smc_thumb"<?php if($option['smc_thumb'])echo ' checked="checked"'; ?> />同步图片</label>
                                <br/><span class="description">Tip: 勾选表示在同步时会将文章中的图片同步到微博</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>调试模式</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="throw_exception"<?php if($option['throw_exception'])echo ' checked="checked"'; ?> />打开调试</label>
                                <br/><span class="description">Tip: 同步文章时如果<span style="color:red;">全部</span>同步失败，可以打开调试模式查看报错信息</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit"><input type="submit" name="submit_box2" class="button-primary" value="保存更改"></p>
            </div>
    	</div>
        <div class="postbox optionbox closed<?php if($_GET['box']==3)echo ' smc-open'; ?>">
        	<div class="handlediv" title="点击以切换"><br></div>
            <h3 class="hndle"><span>评论同步设置</span></h3>
        	<div class="inside">
       			<table class="form-table">
                	<tbody>
                        <tr valign="top">
                            <th scope="row"><label>文章同步格式</label></th>
                            <td>
                            	<textarea class="large-text code" rows="3" name="smc_comment_format"><?php echo $option['smc_comment_format']; ?></textarea>
                                <br/><span class="description">Tip: %%title%% 文章标题 %%url%% 文章链接 %%tags%% 文章标签 %%comment%%评论内容</span>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label>强制同步</label></th>
                            <td>
                            	<label><input type="checkbox" value="1" name="force_sync_comment"<?php if($option['force_sync_comment'])echo ' checked="checked"'; ?> />强制同步微博</label>
                                <br/><span class="description">Tip: 勾选此项，则会忽略用户的选择，将评论同步到用户所绑定的所有微博上</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit"><input type="submit" name="submit_box3" class="button-primary" value="保存更改"></p>
            </div>
     	</div>
     </form>
	</div>
</div>
