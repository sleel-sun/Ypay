<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_csrf_token_time']) || !isset($_SESSION['admin_csrf_token']) || !isset($_POST['csrf_token']) || (time() - $_SESSION['admin_csrf_token_time'] > 1800) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        @header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode(['code' => 403, 'msg' => 'CSRF TOKEN ERROR 或已过期']));
    }
    // Optionally, regenerate token after use or simply update time for this session
    $_SESSION['admin_csrf_token_time'] = time(); 
}

// 对 $act 进行白名单验证，替代 daddslashes
$allowed_actions_admin = ['getcount', 'set', 'setGonggao', 'delGonggao', 'iptype', 'getrecentorders'];
$act_input = isset($_GET['act']) ? trim($_GET['act']) : null;
if ($act_input !== null && in_array($act_input, $allowed_actions_admin, true)) {
    $act = $act_input;
} else if ($act_input === 'getCsrfToken') {
    // Generate and return a new CSRF token
    if (empty($_SESSION['admin_csrf_token']) || empty($_SESSION['admin_csrf_token_time']) || time() - $_SESSION['admin_csrf_token_time'] > 1800) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['admin_csrf_token_time'] = time();
    }
    @header('Content-Type: application/json; charset=UTF-8');
    exit(json_encode(['code' => 0, 'token' => $_SESSION['admin_csrf_token']]));
} else {
    exit(json_encode(['code' => 400, 'msg' => '非法操作']));
}

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'getcount':
	$thtime=date("Y-m-d").' 00:00:00';
	$count1=$DB->getColumn("SELECT count(*) from pre_order");
	$count2=$DB->getColumn("SELECT count(*) from pre_user");
	$plugincount=$DB->getColumn("SELECT count(*) from pre_plugin");
	if($plugincount<1){
		\lib\Plugin::updateAll();
	}

	$paytype = [];
	$rs = $DB->getAll("SELECT id,name,showname FROM pre_type WHERE status=1");
	foreach($rs as $row){
		$paytype[$row['id']] = $row['showname'];
	}
	unset($rs);

	$channel = [];
	$rs = $DB->getAll("SELECT id,name FROM pre_channel WHERE status=1");
	foreach($rs as $row){
		$channel[$row['id']] = $row['name'];
	}
	unset($rs);

	$tongji_cachetime=getSetting('tongji_cachetime', true);
	$tongji_cache = $CACHE->read('tongji');
	if($tongji_cachetime+3600>=time() && $tongji_cache && !isset($_GET['getnew'])){
		$array = unserialize($tongji_cache);
		$result=["code"=>0,"type"=>"cache","paytype"=>$paytype,"channel"=>$channel,"count1"=>$count1,"count2"=>$count2,"usermoney"=>round($array['usermoney'],2),"settlemoney"=>round($array['settlemoney'],2),"order_today"=>$array['order_today'],"order"=>[]];
	}else{
		$usermoney=$DB->getColumn("SELECT SUM(money) FROM pre_user WHERE money!='0.00'");
		$settlemoney=$DB->getColumn("SELECT SUM(money) FROM pre_settle");

		$today=date("Y-m-d");
		$rs=$DB->query("SELECT type,channel,money from pre_order where status=1 and date>='$today'");
		foreach($paytype as $id=>$type){
			$order_paytype[$id]=0;
		}
		foreach($channel as $id=>$type){
			$order_channel[$id]=0;
		}
		while($row = $rs->fetch())
		{
			$order_paytype[$row['type']]+=$row['money'];
			$order_channel[$row['channel']]+=$row['money'];
		}
		foreach($order_paytype as $k=>$v){
			$order_paytype[$k] = round($v,2);
		}
		foreach($order_channel as $k=>$v){
			$order_channel[$k] = round($v,2);
		}
		$allmoney=0;
		foreach($order_paytype as $order){
			$allmoney+=$order;
		}
		$order_today['all']=round($allmoney,2);
		$order_today['paytype']=$order_paytype;
		$order_today['channel']=$order_channel;

		saveSetting('tongji_cachetime',time());
		$CACHE->save('tongji',serialize(["usermoney"=>$usermoney,"settlemoney"=>$settlemoney,"order_today"=>$order_today]));

		$result=["code"=>0,"type"=>"online","paytype"=>$paytype,"channel"=>$channel,"count1"=>$count1,"count2"=>$count2,"usermoney"=>round($usermoney,2),"settlemoney"=>round($settlemoney,2),"order_today"=>$order_today,"order"=>[]];
	}
	for($i=1;$i<7;$i++){
		$day = date("Ymd", strtotime("-{$i} day"));
		if($order_tongji = $CACHE->read('order_'.$day)){
			$result["order"][$day] = unserialize($order_tongji);
		}else{
			break;
		}
	}
	exit(json_encode($result));
break;

case 'set':
	if(isset($_POST['localurl'])){
		if(!empty($_POST['localurl']) && (substr($_POST['localurl'],0,4)!='http' || substr($_POST['localurl'],-1)!='/'))exit('{"code":-1,"msg":"回调专用网址格式错误"}');
	}
	if(isset($_POST['apiurl'])){
		if(!empty($_POST['apiurl']) && (substr($_POST['apiurl'],0,4)!='http' || substr($_POST['apiurl'],-1)!='/'))exit('{"code":-1,"msg":"用户对接网址格式错误"}');
	}
	if(isset($_POST['login_apiurl'])){
		if(!empty($_POST['login_apiurl']) && (substr($_POST['login_apiurl'],0,4)!='http' || substr($_POST['login_apiurl'],-1)!='/'))exit('{"code":-1,"msg":"聚合登录API接口地址格式错误"}');
	}
	    // CSRF token is already validated for POST requests, no need to check $k === 'csrf_token'
    foreach($_POST as $k=>$v){
        if ($k === 'csrf_token') continue; // Don't save the CSRF token itself as a setting
		saveSetting($k, $v);
	}
	$ad=$CACHE->clear();
	if($ad)exit('{"code":0,"msg":"succ"}');
	else exit('{"code":-1,"msg":"修改设置失败['.$DB->error().']"}');
break;
case 'setGonggao':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	// 使用参数化查询防止SQL注入
	if($DB->exec("UPDATE pre_anounce SET status=:status WHERE id=:id", [':status' => $status, ':id' => $id]))exit('{"code":0,"msg":"修改状态成功！"}');
	else exit('{"code":-1,"msg":"修改状态失败['.$DB->error().']"}');
break;
case 'delGonggao':
	$id=intval($_GET['id']);
	// 使用参数化查询防止SQL注入
	if($DB->exec("DELETE FROM pre_anounce WHERE id=:id", [':id' => $id]))exit('{"code":0,"msg":"删除公告成功！"}');
	else exit('{"code":-1,"msg":"删除公告失败['.$DB->error().']"}');
break;
case 'iptype':
	$result = [
	['name'=>'0_X_FORWARDED_FOR', 'ip'=>real_ip(0), 'city'=>get_ip_city(real_ip(0))],
	['name'=>'1_X_REAL_IP', 'ip'=>real_ip(1), 'city'=>get_ip_city(real_ip(1))],
	['name'=>'2_REMOTE_ADDR', 'ip'=>real_ip(2), 'city'=>get_ip_city(real_ip(2))]
	];
	exit(json_encode($result));
break;
case 'getrecentorders':
	// 获取最近10条订单记录
	try {
		$stmt = $DB->prepare("SELECT o.trade_no, o.uid as pid, o.money, o.status, o.addtime, t.showname as type_name 
			FROM pre_order o 
			LEFT JOIN pre_type t ON o.type=t.id 
			ORDER BY o.addtime DESC LIMIT 10");
		$stmt->execute();
		$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		// 格式化返回数据
		exit(json_encode(['status' => 'success', 'orders' => $orders]));
	} catch (Exception $e) {
		// 记录错误日志
		error_log('获取最近订单失败: ' . $e->getMessage());
		exit(json_encode(['status' => 'error', 'message' => '获取订单数据失败']));
	}
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}