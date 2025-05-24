<?php
// 支付成功返回页面

// 获取订单信息
$trade_no = daddslashes($_GET['trade_no']);
$row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no='$trade_no' limit 1");
if(!$row)
    exit('订单号不存在');

// 如果订单已完成支付则跳转到支付成功页面
if($row['status'] == 1){
    exit("<script>window.location.href='../return.php?trade_no={$trade_no}&sign=".md5($trade_no.SYS_KEY.$row['uid'])."';</script>");
}

// 否则显示等待支付界面
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="Content-Language" content="zh-cn">
    <meta name="renderer" content="webkit">
    <title>拉卡拉支付 - 订单处理中</title>
    <style type="text/css">
        body{margin:0;padding:0;}
        p{position:absolute;left:50%;top:50%;height:35px;margin:-35px 0 0 -160px;padding:20px;font:bold 16px/30px "宋体",Arial;background:#f9fafc;border:1px solid #c5d0dc;color:#1c1c1c;border-radius:8px;}
        span{color:#f00;}
        .warming{color: red; font-weight: bold;}
    </style>
    <script src="../assets/js/jquery.min.js"></script>
</head>
<body>
    <p>正在处理订单，请稍候...</p>
    <script>
    // 检查订单状态
    function loadmsg() {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "../api.php?act=orderinfo&trade_no=<?php echo $trade_no?>",
            success: function(data){
                if(data.code === 1){
                    if(data.status === 1){
                        window.location.href='../return.php?trade_no=<?php echo $trade_no?>&sign=<?php echo md5($trade_no.SYS_KEY.$row['uid'])?>';
                    }else{
                        setTimeout(loadmsg, 2000);
                    }
                }else{
                    alert(data.msg);
                }
            },
            error: function(){
                setTimeout(loadmsg, 2000);
            }
        });
    }
    window.onload = function(){
        setTimeout(loadmsg, 2000);
    }
    </script>
</body>
</html>