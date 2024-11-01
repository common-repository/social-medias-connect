<div class="wrap" style="-webkit-text-size-adjust:none;">
	<div id="icon-socialmedia" class="icon32"><img class="border-radius-5" src="<?php echo $this->base_dir.'/images/smc_logo.png' ?>" alt="" /><br></div>
    <h2 class="nav-tab-wrapper">
    	<a href="<?php echo $this->get_menupage_url('social-medias-connect'); ?>" class="nav-tab">账号绑定</a>
        <?php if($this->is_admin_access()): ?>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_option'); ?>" class="nav-tab">插件设置</a>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_uninstall'); ?>" class="nav-tab">插件卸载</a>
        <?php endif; ?>
        <a href="<?php echo $this->get_menupage_url('smc_bind_weibo_help'); ?>" class="nav-tab nav-tab-active">帮助</a>
    </h2>
    <?php if($this->is_admin_access()): ?>
    <h2>开通自定义功能</h2>
	<form action="" method="post" id="order-smc">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">请选择需要开启自定义API功能的微博<br/></th>
				<td id="allservice">
					<?php $echostr='';foreach($this->weibo_array as $weibo_slug){
							if(!in_array('customappkey',$this->get_supports($weibo_slug))){
								$echostr.='<label><input name="order[]" type="checkbox" value="'.$weibo_slug.'" />'.$this->get_weibo_name($weibo_slug).'</label> &#160;&#160;';
							}
						}
						if($echostr)echo $echostr;
						else echo '你已经开通了所有微博的自定义功能！';
					?>
                    <br/><br/>
                    <span class="description">每个开通一个微博的自定义API功能需要<big>￥20</big>，所以如果你不需要全部自定义API，请去掉勾选的相应微博。<br/><sup style="color:red;">NEW</sup>打包开通所有微博的自定义功能仅需<big>￥120</big>。选择六个或以上，都只需￥120</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"></th>
				<td>
					<p class="submit">
						<input type="submit" id="submit" name="donate" class="button-primary" value="确认购买" />
                        <img style="display:none;" src="<?php echo $this->base_dir ?>/images/loading-publish.gif" align="middle" id="smcload" alt="loading" />
                        <input type="hidden" name="smcaction" value="smcorder" />
					</p>
				</td>
			</tr>
		</table>
	</form>
    <h2>网站支持</h2>
    <table class="form-table">
			<tr valign="top">
				<th scope="row">CURL</th>
				<td>
                	<?php $is_curl=function_exists( 'curl_init' ) || function_exists( 'curl_exec' ); if($is_curl)echo 'CURL支持'; else echo 'CURL不支持'; ?><br/>
                    <?php if($is_curl){$curl_version = curl_version(); if ( ! (CURL_VERSION_SSL & $curl_version['features']) ) echo '<span style="color:red;">SSL不支持</span>'; else echo 'SSL支持';} ?>
				</td>
			</tr>
            <tr valign="top">
				<th scope="row">Fsockopen</th>
				<td>
                	<?php if (!function_exists( 'fsockopen' ))echo 'fsockopen不支持';else echo 'fsockopen支持'; ?><br/>
                    <?php if (!extension_loaded( 'openssl' )) echo '<span style="color:red;">SSL不支持</span>'; else echo 'SSL支持'; ?>
				</td>
			</tr>
            <tr valign="top">
				<th scope="row">Streams</th>
				<td>
                	<?php if (!function_exists( 'fopen' ) || !function_exists( 'ini_get' ) || true!=ini_get( 'allow_url_fopen' ))echo 'Streams不支持';else echo 'Streams支持'; ?><br/>
                    <?php if ( ! extension_loaded( 'openssl' ) ) echo '<span style="color:red;">SSL不支持</span>'; else echo 'SSL支持'; ?>
				</td>
			</tr>
	</table>
    <br/>
    <span class="description">注意： CURL、Fsockopen、Streams这三种方式必须有一种主机是支持的，也要同时支持SSL加密传输（如果不支持ssl，则新浪微博、腾讯微博等使用oauth2认证的网站无法进行授权）</span><br/><br/>
    <?php endif; ?>
    <h2>帮助 & FAQ</h2>
    <ol class="faqlist">
    	<li>
        	<h3>授权过期是什么意思？</h3>
            <div>由于oauth2.0的accessToken是有过期时间的，时长从一天到一个月不等。所以在您同步文章前，先到微博绑定页面看一下是否有已经过期的授权。如果授权已过期，你需要刷新授权或者重新绑定账号。
            </div>
        </li>
    	<li>
        	<h3>什么是自定义功能？</h3>
            <div>即自定义应用的appkey，这样可以在发布微博时或者授权时显示你自己的来源名字。<br/>
            	比如通过自定义appkey功能，您可以在同步文章到新浪微博后，该微博的微博小尾巴显示“<span style="color:red;">来自<?php bloginfo('name') ?></span>”<br/>
                另外，某些微博也支持加关注功能。比如新浪微博，用户在使用其新浪微博连接注册您的网站时，会在完善用户信息页面有个加关注选项，当其勾选后，将会自动对您的官网账号进行关注。
            </div>
        </li>
        <li>
        	<h3>如何购买自定义appkey功能呢？</h3>
            <div>您在这个页面上边的“开通自定义功能”下边，勾选您要开通的微博账号，然后点击“确认购买”后，系统即会自动下单，订单成功后，您将被引导到付款页面。<br/>
            您付款成功后自定义功能即会自动开通。<br/><br/>
            注意，购买一个微博账号的自定义功能需支付￥20，但是购买六个以上则只需￥120。您在下单后，如果还未付款，并且想修改自己的购买内容，只需再次下单即可，最后一次的订单将会覆盖前面未付款的订单。<br/>
            如果您已付过款，想要更改购买的服务，请最好与我联系帮您更改。（我的邮箱：<?php echo PLUGIN_AUTHOR_EMAIL; ?>）<br/>
            当然您也可以再次下单，但是款项会重新计算。为了避免您的损失，最好与我联系。
            </div>
        </li>
        <li>
        	<h3>我付过款了，为什么“设置appkey”那里还是不可以点击？（付款了，但自定义功能未开启）</h3>
            <div>请到“插件卸载”页面，点击“更新插件配置”按钮。如果自定义功能还是不能用，请与我联系。（我的邮箱：<?php echo PLUGIN_AUTHOR_EMAIL; ?>）</div>
        </li>
        <li>
        	<h3>如何对自己的订单进行查询？</h3>
            <div>您只需访问<a target="_blank" href="http://socialmedias.sinaapp.com/">http://socialmedias.sinaapp.com/</a>，就可以对您的订单进行查询。<br/>
            只需输入您的网站的名称或者地址就可以查询您的所有订单（未付款或者已经付款的）。
            </div>
        </li>
        <li>
        	<h3>觉得插件不错，赞助杯咖啡？？</h3>
            <div>俺的支付宝账号：imqiqiboy@gmail.com，欢迎赞助从速溶到星巴克各种类型咖啡^_^
            </div>
        </li>
        <li>
        	<h3><a href="http://www.qiqiboy.com/products/plugins/social-medias-connect" target="_blank">更多帮助&FAQ请访问社交媒体连接专题站点</a></h3>
        </li>
    </ol>
    <h2>号外号外</h2>
    <p>本人近期新做了小项目，卧槽！顾名思义，专门用来清理吐槽的，也就是专门用来清理新浪微博的工具，它具有自动化、智能、批量处理微博的能力。即使你有数万条微博，也可以一天之内自动清理完毕，无人值守。</p>
    <p>有兴趣欢迎来体验一下！ - <a href="http://wocao123.sinaapp.com/" target="_blank">http://wocao123.sinaapp.com/</a></p>
</div>
