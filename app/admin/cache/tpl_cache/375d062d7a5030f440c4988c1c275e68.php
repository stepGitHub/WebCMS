<?php if (!defined('BASE_PATH')) exit;?><!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<title>后台管理系统</title>
	<meta name="renderer" content="webkit">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="format-detection" content="telephone=no">
	<link rel="stylesheet" href="/public/admin/layui/css/layui.css" media="all">
	<link rel="stylesheet" href="/public/admin/css/font-awesome.min.css">
	<!--JS引用-->
	<script src="/public/admin/js/jquery.min.js"></script>
	<script src="/public/admin/layui/layui.js"></script>
	<script type="text/javascript" charset="utf-8" src="/public/admin/js/highcharts.js"></script>
	<script type="text/javascript" charset="utf-8" src="/public/admin/js/highcharts-3d.js"></script>
	<script type="text/javascript" charset="utf-8" src="/public/admin/js/exporting.js"></script>
	<!--CSS引用-->
	<link rel="stylesheet" href="/public/admin/css/admin.css">
	<!--[if lt IE 9]>
	<script src="/public/admin/js/html5shiv.min.js"></script>
	<script src="/public/admin/js/respond.min.js"></script>
	<![endif]-->
</head>
<body>
<div class="layui-layout layui-layout-admin">
	<!--头部-->
	<div class="layui-header header">
		<a href="" class="logo">网站后台管理系统</a>
		<div class="user-action">
			<a href="javascript:;"><i class="fa fa-user"></i> <?php echo $admin_name; ?></a>
			<a href="/" target="_blank"><i class="fa fa-home"></i> 网站首页</a>
			<a href="/<?php echo APP_NAME;?>"><i class="fa fa-paper-plane"></i> 后台概览</a>
			<a href="#" data-url="<?php echo $this->url('system/clear');?>" class="ajax-link"><i class="fa fa-trash-o"></i> 清除缓存</a>
			<!-- <a href="#" id="help" class="ajax-link"><i class="fa fa-trash-o"></i>帮助</a> -->
			<a href="#" data-url="<?php echo $this->url('login/logout');?>" class="ajax-link" data-confirm='确实要退出系统？'><i class="fa fa-power-off"></i> 退出</a>
		</div>
	</div>

<!-- <style type="text/css">

</style>
<script src="/public/admin/js/carousel-help.js"></script>
<div id="addcate" class="hid_form" style="display: none;">

</div> -->

<!-- <script type="text/javascript">
$(function(){
	$('body').on('click','#help',function(){//批量增加分类
		var layer = layui.layer;
		layer.open({
			type:1,
			title:'帮助',
			resize:false,
			area:['66%','450px'],
			content:$('#addcate')
		});
	})
});
</script> -->