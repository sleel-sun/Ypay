<?php
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/curl|wget|bot/i', $_SERVER['HTTP_USER_AGENT'])) {
    exit();
}
$nosession = true;
require_once './includes/common.php';

if (function_exists("set_time_limit"))
{
	@set_time_limit(0);
}
if (function_exists("ignore_user_abort"))
{
	@ignore_user_abort(true);
}

@header('Content-Type: text/html; charset=UTF-8');

// 检查是否是自动执行模式
$is_auto = isset($_SERVER['argv']) && $_SERVER['argv'][0] == 'cron.php';

// 如果不是自动执行模式，则验证密钥
if(!$is_auto){
    if(empty($conf['cronkey']))exit("请先设置好监控密钥");
    if($conf['cronkey']!=$_GET['key'])exit("监控密钥不正确");
}

/**
 * 记录计划任务执行日志
 * @param string $task 任务名称
 * @param string $result 执行结果
 * @param int $status 状态：0失败，1成功
 * @return bool
 */
if (!function_exists('addCronLog')) {
    /**
     * 记录计划任务执行日志
     * @param string $task 任务名称
     * @param string $result 执行结果
     * @param int $status 状态：0失败，1成功
     * @return bool
     */
    function addCronLog($task, $result, $status = 1){
        global $DB, $date;
        $data = [
            'task' => $task,
            'result' => $result,
            'status' => $status,
            'addtime' => $date
        ];
        return $DB->insert('cron_log', $data);
    }
}

/**
 * 处理结算任务
 * @param bool $is_auto 是否自动执行模式
 * @return string 执行结果
 */
function handleSettle($is_auto){
    global $conf, $DB, $date, $CACHE;
    if($conf['settle_open']==1 || $conf['settle_open']==3){
		$settle_time=getSetting('settle_time', true);
		if(strtotime($settle_time)>=strtotime(date("Y-m-d").' 00:00:00')){
			$result = '自动生成结算列表今日已完成';
            addCronLog('settle', $result, 1);
			return $result;
		}
		$rs=$DB->query("SELECT * from pre_user where money>={$conf['settle_money']} and account is not null and username is not null and settle=1 and status=1");
		$i=0;
		$allmoney=0;
		while($row = $rs->fetch())
		{
			if($conf['cert_force']==1 && $row['cert']==0){
				continue;
			}
			$i++;
			$settle_rate = $conf['settle_rate'];
			$group = getGroupConfig($row['gid']);
			if(isset($group['settle_open']) && $group['settle_open'] == 2) continue;
			if(isset($group['settle_rate']) && $group['settle_rate']!=='' && $group['settle_rate']!==null) $settle_rate = $group['settle_rate'];
			if($settle_rate>0){
				$fee=round($row['money']*$settle_rate/100,2);
				if(!empty($conf['settle_fee_min']) && $fee<$conf['settle_fee_min'])$fee=$conf['settle_fee_min'];
				if(!empty($conf['settle_fee_max']) && $fee>$conf['settle_fee_max'])$fee=$conf['settle_fee_max'];
				$realmoney=$row['money']-$fee;
			}else{
				$realmoney=$row['money'];
			}
			$data = ['uid'=>$row['uid'], 'type'=>$row['settle_id'], 'account'=>$row['account'], 'username'=>$row['username'], 'money'=>$row['money'], 'realmoney'=>$realmoney, 'addtime'=>'NOW()', 'status'=>0];
			if($DB->insert('settle', $data)){
				changeUserMoney($row['uid'], $row['money'], false, '自动结算');
				$allmoney+=$realmoney;
			}
		}
		saveSetting('settle_time', $date);
		$result = '自动生成结算列表成功 allmony='.$allmoney.' num='.$i;
            addCronLog('settle', $result, 1);
		return $result;
	}else{
		$result = '自动生成结算列表未开启';
			addCronLog('settle', $result, 0);
		return $result;
	}
}

/**
 * 处理订单统计与清理任务
 * @param bool $is_auto 是否自动执行模式
 * @return string 执行结果
 */
function handleOrder($is_auto){
    global $conf, $DB, $date, $CACHE;
	$order_time=getSetting('order_time', true);
	if(strtotime($order_time)>=strtotime(date("Y-m-d").' 00:00:00')){
		$result = '订单统计与清理任务今日已完成';
	        addCronLog('order', $result, 1);
		return $result;
	}

	$thtime=date("Y-m-d H:i:s",time()-3600*24);

	$CACHE->clean();
	$DB->exec("delete from pre_order where status=0 and addtime<'{$thtime}'");
	$DB->exec("delete from pre_regcode where `time`<'" . (time()-3600*24) . "'");
	$DB->exec("delete from pre_blacklist where endtime is not null and endtime<NOW()");
	$DB->exec("delete from pay_wxkflog where addtime<'" . date("Y-m-d H:i:s", strtotime('-48 hours')) . "'");

	$day = date("Ymd", strtotime("-1 day"));

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

	$lastday=date("Y-m-d",strtotime("-1 day"));
	$today=date("Y-m-d");

	$rs=$DB->query("SELECT type,channel,money from pre_order where status=1 and addtime>='$lastday' and addtime<'$today'");
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
	foreach($order_paytype as $money){
		$allmoney+=$money;
	}

	$order_lastday['all']=round($allmoney,2);
	$order_lastday['paytype']=$order_paytype;
	$order_lastday['channel']=$order_channel;

	$CACHE->save('order_'.$day, serialize($order_lastday), 604830);

	saveSetting('order_time', $date);

	$DB->exec("update pre_channel set daystatus=0");
	$result = $day.'订单统计与清理任务执行成功';
	        addCronLog('order', $result, 1);
	return $result;
}

/**
 * 执行订单通知任务 (notify>0)
 * 该任务负责处理需要重新通知的订单，并根据通知次数调整下次通知时间。
 * 如果通知次数达到上限，则将订单标记为不再通知。
 * @param bool $is_auto 是否自动执行模式
 * @return string 执行结果
 */
function handleNotify($is_auto){
    global $conf, $DB, $date, $CACHE;
	$limit = 20; //每次重试的订单数量
	$success_count = 0;
	$fail_count = 0;
	$log_details = '';
	
	for($i=0;$i<$limit;$i++){
		$srow=$DB->getRow("SELECT * FROM pre_order WHERE (TO_DAYS(NOW()) - TO_DAYS(endtime) <= 1) AND notify>0 AND notifytime<NOW() LIMIT 1");
		if(!$srow)break;

		//通知时间：1分钟，3分钟，20分钟，1小时，2小时
		$notify = $srow['notify'] + 1;
		if($notify == 2){
			$interval = '2 minute';
		}elseif($notify == 3){
			$interval = '16 minute';
		}elseif($notify == 4){
			$interval = '36 minute';
		}elseif($notify == 5){
			$interval = '1 hour';
		}else{
			$DB->exec("UPDATE pre_order SET notify=-1,notifytime=NULL WHERE trade_no='{$srow['trade_no']}'");
			continue;
		}
		$DB->exec("UPDATE pre_order SET notify={$notify},notifytime=date_add(now(), interval {$interval}) WHERE trade_no='{$srow['trade_no']}'");

		$url=creat_callback($srow);
		if(do_notify($url['notify'])){
			$DB->exec("UPDATE pre_order SET notify=0,notifytime=NULL WHERE trade_no='{$srow['trade_no']}'");
			addCronLog('notify', $srow['trade_no'].' 重新通知成功', 1);
			echo $srow['trade_no'].' 重新通知成功<br/>';
			$success_count++;
		}else{
			addCronLog('notify', $srow['trade_no'].' 重新通知失败（第'.$notify.'次）', 0);
			echo $srow['trade_no'].' 重新通知失败（第'.$notify.'次）<br/>';
			// 释放内存
			unset($srow);
			unset($url);
		}
	}
	
	$result = "处理完成：成功{$success_count}个，失败{$fail_count}个";
		addCronLog('notify', $result, $success_count > 0 ? 1 : ($fail_count > 0 ? 0 : 1));
	return 'ok!';
}

/**
 * 执行订单通知任务 (notify=-1)
 * 该任务负责处理 notify 字段为 -1 的订单，进行一次最终的通知尝试。
 * @param bool $is_auto 是否自动执行模式
 * @return string 执行结果
 */
function handleNotify2($is_auto){
    global $conf, $DB, $date, $CACHE;
	$limit = 20; //每次重试的订单数量
	$success_count = 0;
	$fail_count = 0;
	$log_details = '';
	
	for($i=0;$i<$limit;$i++){
		$srow=$DB->getRow("SELECT * FROM pre_order WHERE (TO_DAYS(NOW()) - TO_DAYS(endtime) <= 1) AND notify=-1 LIMIT 1");
		if(!$srow)break;

		$url=creat_callback($srow);
		if(do_notify($url['notify'])){
			$DB->exec("UPDATE pre_order SET notify=0,notifytime=NULL WHERE trade_no='{$srow['trade_no']}'");
			addCronLog('notify2', $srow['trade_no'].' 重新通知成功', 1);
			echo $srow['trade_no'].' 重新通知成功<br/>';
			$success_count++;
		}else{
			addCronLog('notify2', $srow['trade_no'].' 重新通知失败', 0);
			echo $srow['trade_no'].' 重新通知失败<br/>';
			$fail_count++;
		}
	}
	
	$result = "处理完成：成功{$success_count}个，失败{$fail_count}个";
		addCronLog('notify2', $result, $success_count > 0 ? 1 : ($fail_count > 0 ? 0 : 1));
	return 'ok!';
}

/**
 * 执行分账任务
 * @param bool $is_auto 是否自动执行模式
 * @return string 执行结果
 */
function handleProfitsharing($is_auto){
    global $conf, $DB, $date, $CACHE;
	try {
		\lib\ProfitSharing\CommUtil::task();
		$result = '分账任务执行成功';
		addCronLog('profitsharing', $result, 1);
		echo 'ok!';
		return 'ok!';
	} catch (Exception $e) {
		$result = '分账任务执行失败：'.$e->getMessage();
		addCronLog('profitsharing', $result, 0);
		echo $result;
		return $result;
	}
}

/**
 * 检查支付通道状态和商户订单成功率任务
 * @param bool $is_auto 是否自动执行模式
 * @return string 执行结果
 */
function handleCheck($is_auto){
    global $conf, $DB, $date, $CACHE;
	$check_result = '';

	// 检查支付通道状态
	if($conf['auto_check_channel'] == 1){
		$second = intval($conf['check_channel_second']);
		$failcount = intval($conf['check_channel_failcount']);
		if($second==0 || $failcount==0){
			$result = '未开启支付通道检查功能';
			addCronLog('check_channel', $result, 0);
			echo $result;
		} else {
			$channels = $DB->getAll("SELECT * FROM pre_channel WHERE status=1 ORDER BY id ASC");
			$closed_channels = 0;
			foreach($channels as $channel){
				$channelid = $channel['id'];
				$orders=$DB->getAll("SELECT trade_no,status FROM pre_order WHERE addtime>=DATE_SUB(NOW(), INTERVAL {$second} SECOND) AND channel='$channelid' order by trade_no desc limit {$failcount}");
				if(count($orders)<$failcount)continue;
				$succount = 0;
				foreach($orders as $order){
					if($order['status']>0) $succount++;
				}
				if($succount == 0){
					$DB->exec("UPDATE pre_channel SET status=0 WHERE id='$channelid'");
					$check_result .= '已关闭通道:'.$channel['name'].'<br/>';
					echo '已关闭通道:'.$channel['name'].'<br/>';
					$closed_channels++;
					if($conf['check_channel_notice'] == 1){
						$mail_name = $conf['mail_recv']?$conf['mail_recv']:$conf['mail_name'];
						send_mail($mail_name,$conf['sitename'].' - 支付通道自动关闭提醒','尊敬的管理员：支付通道"'.$channel['name'].'"因在'.$second.'秒内连续出现'.$failcount.'个未支付订单，已被系统自动关闭！<br/>----------<br/>'.$conf['sitename'].'<br/>'.date('Y-m-d H:i:s'));
					}
				}
			}
			$check_result .= '支付通道检查任务已完成，关闭了'.$closed_channels.'个通道<br/>';
			echo '支付通道检查任务已完成<br/>';
			addCronLog('check_channel', $check_result, 1);
		}
	}

	// 检查商户订单成功率并关闭支付权限
	if($conf['auto_check_sucrate'] == 1){
		$second = intval($conf['check_sucrate_second']);
		$count = intval($conf['check_sucrate_count']);
		$sucrate = floatval($conf['check_sucrate_value']);
		if($second==0 || $count==0 || $sucrate==0){
			$result = '未开启商户订单成功率检查功能';
			addCronLog('check_sucrate', $result, 0);
			echo $result;
		} else {
			// 获取在指定时间内有订单的用户及其订单统计数据
			$sql_user_stats = "SELECT uid, COUNT(*) AS total_ordernum, SUM(CASE WHEN status > 0 THEN 1 ELSE 0 END) AS succ_ordernum FROM pre_order WHERE addtime>=DATE_SUB(NOW(), INTERVAL {$second} SECOND) GROUP BY uid";
			$rs_user_stats = $DB->query($sql_user_stats);

			$closed_users = 0;
			$sucrate_log = '';

			// 逐个用户检查订单成功率
			while($user_stats_row = $rs_user_stats->fetch()){
				$uid = $user_stats_row['uid'];
				$total_num = intval($user_stats_row['total_ordernum']);
				$succ_num = intval($user_stats_row['succ_ordernum']);
				$user_rate = ($total_num > 0) ? round($succ_num * 100 / $total_num, 2) : 0; // Avoid division by zero

				if($total_num >= $count && $user_rate < $sucrate){
					$userrow = $DB->find('user', 'uid,email,pay', ['uid'=>$uid]);
					if($userrow && $userrow['pay'] == 1){ // Add check for $userrow existence
						$DB->exec("UPDATE pre_user SET pay=0 WHERE uid='{$uid}'");
						$log_message = 'UID:'.$uid.' 订单成功率'.$user_rate.'%（'.$succ_num.'/'.$total_num.'），已关闭支付权限';
						addCronLog('check_sucrate', $log_message, 1);
						echo $log_message.'<br/>';
						$closed_users++;
						$DB->exec("INSERT INTO `pre_risk` (`uid`, `type`, `content`, `date`) VALUES (:uid, 1, :content, NOW())", [':uid'=>$uid,':content'=>$user_rate.'%（'.$succ_num.'/'.$total_num.'）']);
						if($conf['check_sucrate_notice'] == 1){
							send_mail($userrow['email'],$conf['sitename'].' - 商户支付权限关闭提醒','尊敬的用户：你的商户ID '.$uid.' 因在'.$second.'秒内订单支付成功率低于'.$sucrate.'%，已被系统自动关闭支付权限！如有疑问请联系网站客服。<br/>当前订单支付成功率：'.$user_rate.'%（总订单数：'.$succ_num.'，成功订单数：'.$total_num.'）<br/>----------<br/>'.$conf['sitename'].'<br/>'.date('Y-m-d H:i:s'));
						}
					}
				}
			}
			echo '商户订单成功率检查任务已完成<br/>';
		}
	}
}

// 根据参数执行相应的计划任务
$do = $_GET['do'] ?? 'all';

if ($do === 'all') {
    echo handleSettle($is_auto) . '<br/>';
    echo handleOrder($is_auto) . '<br/>';
    echo handleNotify($is_auto) . '<br/>';
    echo handleNotify2($is_auto) . '<br/>';
    echo handleProfitsharing($is_auto) . '<br/>';
    echo handleCheck($is_auto) . '<br/>';
} else if ($do === 'settle') {
    echo handleSettle($is_auto);
} else if ($do === 'order') {
    echo handleOrder($is_auto);
} else if ($do === 'notify') {
    echo handleNotify($is_auto);
} else if ($do === 'notify2') {
    echo handleNotify2($is_auto);
} else if ($do === 'profitsharing') {
    echo handleProfitsharing($is_auto);
} else if ($do === 'check') {
    echo handleCheck($is_auto);
} else {
    exit('参数错误');
}

?>