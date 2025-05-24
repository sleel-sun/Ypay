<?php
include("../includes/common.php");

function showerror($msg){
	global $cdnpublic;
	include ROOT.'paypage/error.php';
	exit;
}

function showerrorjson($msg){
	$result = ['code'=>-1, 'msg'=>$msg];
	exit(json_encode($result));
}

function check_paytype(){
	$type=null;
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger/')!==false){
		$type='wxpay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient/')!==false){
		$type='alipay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
		$type='qqpay';
	}
	return $type;
}

function alipayOpenId($channel){
	global $DB,$siteurl;
	$channel = \lib\Channel::get($channel);
	if(!$channel)showerror('支付通道不存在');
	$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
	try{
		$oauth = new \Alipay\AlipayOauthService($alipay_config);
		if(isset($_GET['auth_code'])){
			$result = $oauth->getToken($_GET['auth_code']);
			if(!empty($result['user_id'])){
				$openid = $result['user_id'];
			}else{
				$openid = $result['open_id'];
			}
			return $openid;
		}else{
			$redirect_uri = $siteurl.'paypage/';
			$oauth->oauth($redirect_uri);
		}
	}catch(Exception $e){
		showerror('支付宝快捷登录失败！'.$e->getMessage());
	}
}

function weixinOpenId($channel){
	global $DB;
	$channel = \lib\Channel::get($channel);
	if(!$channel)showerror('支付通道不存在');
	
	$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
	if(!$wxinfo)showerror('支付通道绑定的微信公众号不存在');

	try{
		$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
		$openId = $tools->GetOpenid();
	}catch(Exception $e){
		showerror($e->getMessage());
	}
	return $openId;
}

// 添加HTTPS强制跳转
if($_SERVER['HTTPS'] != 'on') {
    header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    exit();
}

/**
 * 验证请求签名
 * 使用 HMAC-SHA256 算法提高安全性
 * @param array $params 请求参数数组 (推荐使用 $_POST 或 $_GET)
 * @param string $secretKey 用于签名的密钥
 * @return bool 签名是否有效
 * @author Trae AI
 */
function verifySign($params, $secretKey) {
    if (!isset($params['sign'])) {
        return false; // 缺少签名参数
    }
    $sign = $params['sign'];
    unset($params['sign']);
    ksort($params); // 参数名ASCII码从小到大排序（字典序）
    $stringToSign = '';
    foreach ($params as $k => $v) {
        // 过滤掉值为空的参数不参与签名
        if ($v !== '' && $v !== null) {
            $stringToSign .= $k . '=' . $v . '&';
        }
    }
    $stringToSign = rtrim($stringToSign, '&');
    // 使用 HMAC-SHA256 算法计算签名
    $calculatedSign = hash_hmac('sha256', $stringToSign, $secretKey);
    // 使用 hash_equals 进行安全的字符串比较，防止时序攻击
    return hash_equals($calculatedSign, $sign);
}

// 在支付请求处理前验证签名
// 注意：应根据实际情况选择 $_POST 或 $_GET。对于敏感操作，通常推荐使用 $_POST。
// 此处假设支付请求主要通过 POST 发送。
if (!verifySign($_POST, $epay_config['key'])) { // 改为使用 $_POST
    // 考虑返回JSON错误，而不是直接die，以便前端更好地处理
    // showerrorjson('签名验证失败');
    die('签名验证失败');
}
