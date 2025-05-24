<?php
$nosession = true;
include("./includes/common.php");

if (function_exists("set_time_limit"))
{
	@set_time_limit(0);
}
if (function_exists("ignore_user_abort"))
{
	@ignore_user_abort(true);
}

// 获取交易号
$trade_no = isset($_GET['trade_no']) ? daddslashes($_GET['trade_no']) : exit('交易号不存在');
$sign = isset($_GET['sign']) ? $_GET['sign'] : null;

// 验证签名
if($sign !== null){
    $row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no=:trade_no LIMIT 1", [':trade_no'=>$trade_no]);
    if(!$row) exit('订单号不存在');
    if($sign != md5($trade_no.SYS_KEY.$row['uid'])) exit('签名校验失败');
}

// 重定向到支付回调处理
header("Location: ./pay.php?s=return/{$trade_no}/");