<?php
include("./inc.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

// 获取并校验用户ID
$uid=isset($_POST['uid']) ? intval($_POST['uid']) : 0;
// 获取并初步处理金额，后续会有更严格的校验
$money=isset($_POST['money']) ? trim($_POST['money']) : '';
// 获取付款人信息
// 移除 daddslashes。开发者应根据实际使用场景进行处理：
// 1. 如果用于数据库查询的 WHERE 子句，应使用参数化查询 (PDO prepared statements)。
// 2. 如果用于在 HTML 中显示，应使用 htmlspecialchars() 进行转义以防止 XSS。
// 3. 如果仅用于内部逻辑判断或传递，确保后续处理是安全的。
$payer=isset($_POST['payer']) ? trim($_POST['payer']) : '';
// 获取支付类型
$paytype=isset($_POST['paytype']) ? trim($_POST['paytype']) : '';
// 获取直接支付标志
$direct=isset($_POST['direct']) ? intval($_POST['direct']) : 0;

// CSRF Token 校验
if(!isset($_POST['token']) || !verify_csrf_token($_POST['token'], 'paypage')){
    showerrorjson('CSRF TOKEN ERROR或已过期');
}

// 校验收款方信息
if(!$uid || $uid!=$_SESSION['paypage_uid'])showerrorjson('收款方信息无效');

// 金额合法性校验
if (!is_numeric($money) || $money <= 0 || !preg_match('/^(0|[1-9]\d{0,9})(\.\d{1,2})?$/', $money)) {
    // 允许整数或最多两位小数的正数，根据实际业务调整精度
    showerrorjson('金额不合法');
}
// 转换为浮点数进行比较
$money_float = floatval($money);
if(isset($conf['pay_maxmoney']) && $conf['pay_maxmoney']>0 && $money_float>$conf['pay_maxmoney'])showerrorjson('最大支付金额是'.$conf['pay_maxmoney'].'元');
if(isset($conf['pay_minmoney']) && $conf['pay_minmoney']>0 && $money_float<$conf['pay_minmoney'])showerrorjson('最小支付金额是'.$conf['pay_minmoney'].'元');

// IP黑名单检查
$blackip = $DB->find('blacklist', '*', ['type'=>1, 'content'=>$clientip], null, 1);
if($blackip)showerrorjson('系统异常无法完成付款（IP）');

// 付款人黑名单检查 (如果payer存在)
if(!empty($payer)){
	$black = $DB->find('blacklist', '*', ['type'=>0, 'content'=>$payer], null, 1);
	if($black)showerrorjson('系统异常无法完成付款（付款人）');
}

if(!empty($paytype) && isset($_SESSION['paypage_typeid']) && isset($_SESSION['paypage_paymax']) && isset($_SESSION['paypage_paymin'])){
	if(!empty($_SESSION['paypage_paymin']) && $_SESSION['paypage_paymin']>0 && $money<$_SESSION['paypage_paymin']){
		showerrorjson('当前支付通道最大支付金额是'.$_SESSION['paypage_paymin'].'元');
	}
	if(!empty($_SESSION['paypage_paymax']) && $_SESSION['paypage_paymax']>0 && $money>$_SESSION['paypage_paymax']){
		showerrorjson('当前支付通道最小支付金额是'.$_SESSION['paypage_paymax'].'元');
	}
}

// 查询用户信息，使用预处理语句
$stmt_user = $DB->prepare("SELECT `mode`, `ordername`, `channelinfo` FROM `pre_user` WHERE `uid` = :uid LIMIT 1");
$stmt_user->bindParam(':uid', $uid, PDO::PARAM_INT);
$stmt_user->execute();
$userrow = $stmt_user->fetch(PDO::FETCH_ASSOC);
if(!$userrow){
    showerrorjson('收款用户信息不存在');
}

// 生成唯一订单号
$trade_no = date("YmdHis") . random_int(10000, 99999); // 使用 random_int 替代 rand

// 构建返回URL
$return_url_path = 'paypage/success.php';
$return_url = $siteurl . $return_url_path . '?trade_no=' . rawurlencode($trade_no); // 对参数进行URL编码

// 安全验证：检查生成的URL是否合法，并确保域名与站点URL一致
if (!filter_var($return_url, FILTER_VALIDATE_URL) || parse_url($return_url, PHP_URL_HOST) !== parse_url($siteurl, PHP_URL_HOST)) {
    // 增加域名校验，防止开放重定向漏洞
    showerrorjson('返回URL不合法或存在安全风险');
}
$domain = getdomain($return_url); // getdomain 函数内部也应该有安全处理

// 创建订单，使用预处理语句
$stmt_insert_order = $DB->prepare("INSERT INTO `pre_order` (`trade_no`, `out_trade_no`, `uid`, `tid`, `addtime`, `name`, `money`, `notify_url`, `return_url`, `domain`, `ip`, `buyer`, `status`) VALUES (:trade_no, :out_trade_no, :uid, 3, NOW(), :name, :money, :notify_url, :return_url, :domain, :ip, :buyer, 0)");
$params_insert_order = [
    ':trade_no' => $trade_no,
    ':out_trade_no' => $trade_no, // 通常 out_trade_no 由外部传入，此处保持一致
    ':uid' => $uid,
    ':name' => '在线收款',
    ':money' => $money, // money 已经过is_numeric校验
    ':notify_url' => $return_url, // 示例中notify_url与return_url相同，实际应不同
    ':return_url' => $return_url,
    ':domain' => $domain,
    ':ip' => $clientip,
    ':buyer' => $payer // payer 已经过 daddslashes 处理
];
if(!$stmt_insert_order->execute($params_insert_order)){
    // 记录详细错误日志，而不是直接暴露给用户
    // error_log('创建订单失败: ' . print_r($stmt_insert_order->errorInfo(), true));
    showerrorjson('创建订单失败，请返回重试！');
}

$_SESSION['paypage_trade_no'] = $trade_no;
$_SESSION['paypage_trade_time'] = time();

$result=[];
$result['code']=0;
$result['msg']='succ';
$result['trade_no']=$trade_no;
$result['direct']=$direct;

if(!empty($paytype) && isset($_SESSION['paypage_typeid']) && isset($_SESSION['paypage_channel']) && isset($_SESSION['paypage_rate'])){
	$typeid = intval($_SESSION['paypage_typeid']);
	$channelid = intval($_SESSION['paypage_channel']);
	$subchannelid = intval($_SESSION['paypage_subchannel']);
	if($direct==1){
		if($userrow['mode']==1){
			$realmoney = round($money*(100+100-$_SESSION['paypage_rate'])/100,2);
			$getmoney = $money;
		}else{
			$realmoney = $money;
			$getmoney = round($money*$_SESSION['paypage_rate']/100,2);
		}

		if(!empty($conf['pay_payaddstart'])&&$conf['pay_payaddstart']!=0&&!empty($conf['pay_payaddmin'])&&$conf['pay_payaddmin']!=0&&!empty($conf['pay_payaddmax'])&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart']){
            // randomFloat 函数需要确保其安全性，例如参数的类型和范围
            $add_amount = randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2));
            if ($add_amount === false) { // 假设 randomFloat 失败返回 false
                showerrorjson('生成随机附加金额失败');
            }
            $realmoney = round($realmoney + $add_amount, 2);
        }

        // 更新订单信息，使用预处理语句
        $stmt_update_order = $DB->prepare("UPDATE pre_order SET type=:type, channel=:channel, realmoney=:realmoney, getmoney=:getmoney WHERE trade_no=:trade_no");
        $params_update_order = [
            ':type' => $typeid,
            ':channel' => $channelid,
            ':realmoney' => $realmoney,
            ':getmoney' => $getmoney,
            ':trade_no' => $trade_no
        ];
		if(!$stmt_update_order->execute($params_update_order)){
            // error_log('更新订单(direct)失败: ' . print_r($stmt_update_order->errorInfo(), true));
            showerrorjson('更新订单信息失败');
        }

		$ordername = 'onlinepay'.time();
		if(!empty($userrow['ordername']))$conf['ordername']=$userrow['ordername'];
		$ordername = !empty($conf['ordername'])?ordername_replace($conf['ordername'],$ordername,$uid,$trade_no,$trade_no):$ordername;
		$channel = $subchannelid > 0 ? \lib\Channel::getSub($subchannelid) : \lib\Channel::get($channelid, $userrow['channelinfo']);
		if(!$channel)showerrorjson('支付通道不存在');

		$order['trade_no'] = $trade_no;
		$order['out_trade_no'] = $trade_no;
		$order['uid'] = $uid;
		$order['addtime'] = $date;
		$order['name'] = '在线收款';
		$order['realmoney'] = $realmoney;
		$order['type'] = $typeid;
		$order['channel'] = $channelid;
		$order['typename'] = $paytype;
		$order['profits'] = \lib\Payment::updateOrderProfits($order, $channel['plugin']);
		$order['profits2'] = \lib\Payment::updateOrderProfits2($order, $channel['plugin']);
		
		$paydata = \lib\Plugin::loadForJsapi($trade_no,$paytype,$realmoney,$ordername,$payer);

		$result['paydata'] = $paydata;
	}else{
		$result['url'] = '/submit2.php?typeid='.$typeid.'&trade_no='.$trade_no;
	}
}else{
	$result['url'] = '/cashier.php?trade_no='.$trade_no;
}

exit(json_encode($result));