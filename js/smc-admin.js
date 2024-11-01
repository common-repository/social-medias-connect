/*
 * date:2012/05/30
 * Social Medias Connect Plug-in
 */
(function($){
	$(document).ready(function(){
		$('.smc-start-bind').click(function(){
			window.open($(this).attr('href'),'smcWindow','width=800,height=600,left=150,top=100,scrollbar=no,resize=no');
			return false;
		});
		$('#smc-admin-option .optionbox .handlediv,#smc-admin-option .optionbox .hndle').click(function(){
			$(this).nextAll('.inside').slideToggle('400');
		});
		$('#smc-admin-option .smc-open .handlediv').click();
		$('#connect-list .weibo_item').click(function(){
			var checkbox=$('input[type=checkbox]',this);
			if(checkbox.attr('checked')){
				checkbox.removeAttr('checked');
				$(this).removeClass('checked');
			}else{
				checkbox.attr('checked','checked');
				$(this).addClass('checked');
			}
		});
		var get_bind=function(){
			var user_id=$('#smc-user-change').val();
			if(user_id){
				$.ajax({
					url:$('#smc-user-change').attr('data-url')+'?smc-post-id='+$('#post_ID').val()+'&smc-get-post-bind='+user_id,
					type:'GET',
					dataType:'json',
					beforeSend:function(){
						$('#smc-loading-img').fadeIn();
						$('#publish').attr('disabled','disabled');
					},
					error:function(){
						alert('获取失败，请重试！')
					},
					success:function(resp){
						var str='';
						for(var slug in resp){
							str+='<label class="smc-bind-label"><input '+(resp[slug].timeout?'disabled title="授权过期" ':'')+'type="checkbox" '+(resp[slug].timeout||resp[slug].sync?'':'checked="checked" ')+'value="'+slug+'" name="sycnsocialmedia[]" />'+resp[slug].name+(resp[slug].timeout?'(<span style="font-weight:bold;color:#BC0B0B;">!</span>)':'')+'</label> ';
						}
						if(!str)str='该账号没有绑定任何微博';
						$('#smc-weibo-bind').html(str).fadeIn();
					},
					complete:function(){
						$('#smc-loading-img').fadeOut();
						$('#publish').removeAttr('disabled');
					}
				});
			}else $('#smc-weibo-bind').fadeOut();
		}
		get_bind();
		$('#smc-user-change').change(get_bind);

		$('#order-smc').submit(function(){
			var inputs=$('#allservice input:checked');
			var price=inputs.length<6?inputs.length*20:120;
			if(!inputs.length){
				alert('请至少选择一个微博！');
			}else if(confirm('支付'+price+'元开通相关功能？')){
				$.ajax({
					url:'',
					type:'POST',
					data:$('#order-smc').serialize(),
					dataType:'json',
					beforeSend: function(){
						$('#submit').attr('disabled','disabled');
						$('#smcload').fadeIn();
					},
					error: function(){
						alert('发生了一些错误！')
					},
					success: function(data){
						if(data.status=='success'){
							alert('订购成功，浏览器将跳转到支付页面！');
							window.location.href=data.linkto;
						}else{
							alert(data.message);
						}
					},
					complete: function(){
						$('#submit').removeAttr('disabled');
						$('#smcload').fadeOut();
					}
				});
			}
			return false;
		});
	});
})(jQuery);

window.smcAction=function(url){
	$url=url||window.location.href;
	window.location.href=$url.replace(/&smc.*/i,'');
	setTimeout(function(){
		window.location.reload();
	},1000);
	return true;
}