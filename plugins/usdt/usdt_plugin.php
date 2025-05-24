<?php

class usdt_plugin
{
	static public $info = [
		'name'        => 'usdt', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => 'USDT支付', //支付插件显示名称
		'author'      => 'Epusdt', //支付插件作者
		'link'        => 'https://github.com/assimon/epusdt', //支付插件作者链接
		'types'       => ['usdt'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => 'API接口地址',
				'type' => 'input',
				'note' => '必须以http://或https://开头，以/结尾',
			],
			'appid' => [
				'name' => '商户ID',
				'type' => 'input',
				'note' => 'Epusdt平台的商户ID',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => 'Epusdt平台的商户密钥',
			],
			'appswitch' => [
				'name' => '支付网络',
				'type' => 'select',
				'options' => [0=>'TRC20',1=>'ERC20'],
			],
		],
		'select' => null,
		'note' => '请在Epusdt平台注册并获取商户ID和密钥，接口地址填写Epusdt平台API地址', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	/**
	 * 发起支付请求
	 */
	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf, $DB;

		require_once(PAY_ROOT."inc/EpusdtClient.php");

		try {
			$payUrl = \lib\Payment::lockPayData(TRADE_NO, function() use($channel, $order, $conf, $siteurl) {
				$client = new EpusdtClient($channel['appid'], $channel['appkey'], $channel['appurl'], $channel['appswitch']);
				$result = $client->createOrder(
					$order['realmoney'], 
					TRADE_NO, 
					$conf['localurl'].'pay/notify/'.TRADE_NO.'/', 
					$siteurl.'pay/return/'.TRADE_NO.'/'
				);

				if(isset($result['status']) && $result['status'] == 'success') {
					$payUrl = $result['data']['payment_url'];
					return $payUrl;
				}else{
					throw new Exception(isset($result['message']) ? $result['message'] : '获取支付链接失败');
				}
			});

			return ['type'=>'jump','url'=>$payUrl];
		} catch (Exception $e) {
			sysmsg('USDT支付下单失败：'.$e->getMessage());
		}
	}

	/**
	 * 生成签名
	 */
	static private function generateSignature($merchant_id, $amount, $order_id, $key) {
		$data = $merchant_id . $amount . $order_id . $key;
		return md5($data);
	}

	/**
	 * 同步回调
	 */
	static public function return(){
		global $channel, $order;

		return ['type'=>'page','page'=>'return'];
	}

	/**
	 * 异步回调
	 */
	static public function notify(){
		global $channel, $order, $DB;

		require_once(PAY_ROOT."inc/EpusdtClient.php");

		$json = file_get_contents('php://input');
		$data = json_decode($json, true);

		if(!$data || empty($data['merchant_id']) || empty($data['order_id']) || empty($data['amount']) || empty($data['signature'])) {
			exit('参数不完整');
		}

		// 验证商户ID
		if($data['merchant_id'] != $channel['appid']) {
			exit('商户ID不匹配');
		}

		// 验证签名
		$client = new EpusdtClient($channel['appid'], $channel['appkey'], $channel['appurl'], $channel['appswitch']);
		if(!$client->verifyNotify($data)) {
			exit('签名验证失败');
		}

		// 查询订单
		$trade_no = $data['order_id'];
		$srow = $DB->getRow("SELECT * FROM pre_order WHERE trade_no='$trade_no' LIMIT 1");
		if(!$srow) {
			exit('订单号不存在');
		}

		// 验证金额
		if(round($srow['realmoney'], 2) != round($data['amount'], 2)) {
			exit('金额不匹配');
		}

		// 更新订单
		$DB->update('order', ['status'=>1,'endtime'=>'NOW()'], ['trade_no'=>$trade_no]);

		// 处理通知
		processNotify($srow, $trade_no);

		exit('success');
	}

	/**
	 * 检查支付状态
	 */
	static public function query($trade_no){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf;

		require_once(PAY_ROOT."inc/EpusdtClient.php");

		try {
			$client = new EpusdtClient($channel['appid'], $channel['appkey'], $channel['appurl'], $channel['appswitch']);
			$result = $client->queryOrder($trade_no);

			if(isset($result['status']) && $result['status'] == 'success' && isset($result['data']['status']) && $result['data']['status'] == 'completed') {
				return ['status'=>1];
			}else{
				return ['status'=>0];
			}
		} catch (Exception $e) {
			return ['status'=>0, 'msg'=>$e->getMessage()];
		}
	}
}