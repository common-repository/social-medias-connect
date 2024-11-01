<?php global $SMC;
require dirname(__FILE__).'/SMConnect.php';
require dirname(__FILE__).'/widgets.php';
if(!isset($SMC)){
	$SMC=new smcClass;
}
function smc__install(){
	global $SMC,$wp_version;
	if(version_compare($wp_version,'3.1')<0){
		echo 'social medias connect V'.SMC_VERSION.' 需要wordpress 3.1或以上版本才能运行！<br/>如果需要早期版本，可以到此下载http://wordpress.org/extend/plugins/social-medias-connect/developers/';
		exit;
	}
	if(!get_option('smc_vesion_compatible')){
		$SMC->vesion_compatible();
		delete_option('smc_global_option');
		delete_option('smc_weibo_options');
	}
	$SMC->get_global_option();
	$SMC->initialize_option();
}

function smc_connect($args=''){
	global $SMC;
	$SMC->smc_print_weibo($args);
}