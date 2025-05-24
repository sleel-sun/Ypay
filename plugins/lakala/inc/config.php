<?php
$lakala_config = [
	//商户编码
	'partner_code' => $channel['appid'],

	//商户密钥
	'key' => $channel['appkey'],

	//支付网关
	'gateway_url' => "https://pay.lakala-japan.com/api/v1.0/gateway/partners/",

	//日志记录位置
	'log_path' => dirname(__FILE__).'/log/',
];

return $lakala_config;