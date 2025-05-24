<?php

/**
 * 新支付方式插件
 * 实现支付接口、订单创建和回调处理等核心功能
 */
class newpay_plugin
{
	static public $info = [
		'name'        => 'newpay', //支付插件英文名称，需和目录名称一致
		'showname'    => '新支付方式', //支付插件显示名称
		'author'      => '开发者名称', //支付插件作者
		'link'        => 'https://example.com', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支持的支付方式
		'inputs' => [ //支付插件要求传入的参数
			'appid' => [
				'name' => '应用ID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => 'API密钥',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => [ //支持的支付方式
			'1' => '扫码支付',
			'2' => 'APP支付',
		],
	];

	// 支付接口实现
	public static function submit($order)
	{
		// 实现支付提交逻辑
	}

	// 回调处理
	public static function callback()
	{
		// 实现回调处理逻辑
	}
}