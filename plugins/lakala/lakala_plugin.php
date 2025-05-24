<?php

class lakala_plugin
{
	static public $info = [
		'name'        => 'lakala', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '拉卡拉支付', //支付插件显示名称
		'author'      => '拉卡拉', //支付插件作者
		'link'        => 'https://payjp.lakala.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => [], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户编码(partner_code)',
				'type' => 'input',
				'note' => '由4-6位大写字母或数字构成',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => '用于签名验证',
			],
		],
		'select' => [ //选择已开启的支付方式
			'1' => 'Alipay支付',
			'2' => 'Alipay+支付',
			'3' => 'Wechat支付',
		],
		'note' => '<p>拉卡拉支付接口配置教程：</p><p>1、<a href="https://payjp.lakala.com/" target="_blank">点击注册拉卡拉支付账号</a></p><p>2、登录管理后台，获取商户编码(partner_code)和商户密钥</p><p>3、选择可用的接口，只能选择已经签约的产品，否则会无法支付！</p>', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	/**
	 * 提交支付处理
	 */
	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf, $clientip;

		$isMobile = checkmobile();
		
		// 支付网关地址
		$apiurl = 'https://pay.lakala-japan.com/api/v1.0/gateway/partners/'.$channel['appid'].'/orders/'.TRADE_NO;
		
		// 获取支付渠道
		$payChannel = '';
		if(in_array('1', $channel['apptype'])) {
			$payChannel = 'Alipay';
		} elseif(in_array('2', $channel['apptype'])) {
			$payChannel = 'Alipay+';
		} elseif(in_array('3', $channel['apptype'])) {
			$payChannel = 'Wechat';
		}
		
		// 构建请求参数
		$lakala_config = require(PAY_ROOT.'inc/config.php');
		$time = time() * 1000; // 毫秒时间戳
		$nonce_str = random(16); // 随机字符串
		
		// 请求数据
		$data = [
			'description' => $ordername, // 订单标题
			'price' => intval($order['realmoney'] * 100), // 金额，单位为分
			'currency' => 'CNY', // 币种
			'channel' => $payChannel, // 支付渠道
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/', // 回调地址
			'operator' => 'system', // 操作人员标识
		];
		
		// 生成签名
		$sign = self::generateSign($data, $time, $nonce_str, $channel['appkey']);
		
		// 构建请求URL
		$requestUrl = $apiurl . '?time=' . $time . '&nonce_str=' . $nonce_str . '&sign=' . $sign;
		
		// 发起请求
		try {
			$response = get_curl($requestUrl, json_encode($data), [
				'Content-Type: application/json',
				'Accept: application/json'
			], 'POST');
			$result = json_decode($response, true);
			
			if(isset($result['return_code']) && $result['return_code'] === 'SUCCESS') {
				if($result['result_code'] === 'SUCCESS' || $result['result_code'] === 'EXISTS') {
					// 保存二维码链接到数据库
					if(isset($result['code_url'])) {
						$DB = \lib\Database::getInstance();
						$DB->exec("UPDATE `pre_order` SET `qr_url`=:qr_url WHERE `trade_no`=:trade_no", [':qr_url'=>$result['code_url'], ':trade_no'=>TRADE_NO]);
					}
					
					// 根据设备类型返回不同的支付方式
					if($isMobile) {
						// 移动端直接跳转支付
						if(isset($result['pay_url'])) {
							return ['type'=>'jump','url'=>$result['pay_url']];
						} else {
							return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
						}
					} else {
						// PC端显示二维码
						return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
					}
				} else {
					return ['type'=>'error','msg'=>'下单失败！错误信息：'.(isset($result['err_msg'])?$result['err_msg']:'未知错误')];
				}
			} else {
				return ['type'=>'error','msg'=>'下单失败！错误信息：'.(isset($result['err_msg'])?$result['err_msg']:'未知错误')];
			}
		} catch(Exception $e) {
			return ['type'=>'error','msg'=>'下单失败！'.$e->getMessage()];
		}
	}

	/**
	 * 页面回调
	 */
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	/**
	 * 服务器回调
	 */
	static public function notify(){
		global $channel, $order;
		
		// 获取回调数据
		$json = file_get_contents('php://input');
		$data = json_decode($json, true);
		
		// 验证签名
		$time = isset($_GET['time']) ? $_GET['time'] : '';
		$nonce_str = isset($_GET['nonce_str']) ? $_GET['nonce_str'] : '';
		$sign = isset($_GET['sign']) ? $_GET['sign'] : '';
		
		if(empty($time) || empty($nonce_str) || empty($sign)) {
			return ['type'=>'html','data'=>'参数不完整'];
		}
		
		// 验证签名
		$mysign = self::generateSign($data, $time, $nonce_str, $channel['appkey']);
		if($sign !== $mysign) {
			return ['type'=>'html','data'=>'签名验证失败'];
		}
		
		// 验证订单状态
		if($data['return_code'] !== 'SUCCESS' || $data['result_code'] !== 'SUCCESS') {
			return ['type'=>'html','data'=>'订单未支付成功'];
		}
		
		// 验证订单号
		if($data['partner_code'] !== $channel['appid'] || $data['order_id'] !== $order['trade_no']) {
			return ['type'=>'html','data'=>'订单号不匹配'];
		}
		
		// 验证金额
		if(intval($data['price']) !== intval($order['realmoney'] * 100)) {
			return ['type'=>'html','data'=>'订单金额不匹配'];
		}
		
		// 验证支付状态
		if($data['status'] !== 'PAID') {
			return ['type'=>'html','data'=>'订单未支付'];
		}
		
		// 处理订单
		$out_trade_no = $data['order_id'];
		
		return ['type'=>'trade_no','trade_no'=>$out_trade_no];
	}

	/**
	 * 退款操作
	 */
	static public function refund($order){
		return ['code'=>-1, 'msg'=>'该支付通道不支持API退款'];
	}

	/**
	 * 生成签名
	 */
	static public function generateSign($data, $time, $nonce_str, $key) {
		// 按字典序排序参数
		ksort($data);
		
		// 构建签名字符串
		$stringA = '';
		foreach($data as $k => $v) {
			if($v !== '' && $v !== null && !is_array($v)) {
				$stringA .= $k . '=' . $v . '&';
			}
		}
		
		// 添加时间戳和随机字符串
		$stringA .= 'time=' . $time . '&nonce_str=' . $nonce_str;
		
		// 添加密钥
		$stringSignTemp = $stringA . '&key=' . $key;
		
		// MD5加密并转为大写
		$sign = strtoupper(md5($stringSignTemp));
		
		return $sign;
	}

	/**
	 * 生成随机字符串
	 */
	static private function random($length = 16) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$str = '';
		for($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}
}