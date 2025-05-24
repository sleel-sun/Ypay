<?php
$is_defend = true;
include("../includes/common.php");

if (!$conf['test_open']) sysmsg("未开启测试支付");

if (isset($_GET['ok']) && isset($_GET['trade_no'])) {
    $trade_no = daddslashes($_GET['trade_no']);
    $row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no=:trade_no AND uid=:uid limit 1", [
        ':trade_no' => $trade_no,
        ':uid' => $conf['test_pay_uid']
    ]);
    if (!$row) sysmsg('订单号不存在');
    if ($row['status'] != 1) {
        $DB->exec("UPDATE pre_order SET status='1' WHERE trade_no=:trade_no AND uid=:uid", [
            ':trade_no' => $trade_no,
            ':uid' => $conf['test_pay_uid']
        ]);
        $row['status'] = 1;
    }
    $money = $row['money'];
} else {
    $trade_no = date("YmdHis") . rand(111, 999);
    $gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid=:uid limit 1", [':uid' => $conf['test_pay_uid']]);
    $paytype = \lib\Channel::getTypes($gid);
    $csrf_token = generate_csrf_token('user');
    $money = 1;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $conf['sitename']?> - 测试支付</title>
    <link href="https://cdn.staticfile.org/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.staticfile.org/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.1);
            border-radius: 1rem;
        }
        .form-floating>.form-control:disabled {
            background-color: #e9ecef;
        }
        .btn-pay img {
            height: 20px;
            margin-right: 6px;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card">
                <div class="card-header text-white bg-primary text-center">
                    <h5 class="mb-0"><?php echo $conf['sitename']?> - 测试支付</h5>
                </div>
                <div class="card-body">
                    <form name="alipayment" id="testpay_form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?>">

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="trade_no" value="<?php echo $trade_no ?>" disabled>
                            <label for="trade_no">订单号</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="name" value="支付测试" disabled>
                            <label for="name">商品名称</label>
                        </div>

                        <div class="form-floating mb-4">
                            <input type="number" class="form-control" id="money" name="money" value="<?php echo $money ?>" <?php echo isset($_GET['ok']) ? 'disabled' : 'required' ?>>
                            <label for="money">付款金额 (元)</label>
                        </div>

                        <?php if (isset($_GET['ok'])) { ?>
                            <div class="alert alert-success text-center">
                                <i class="fa-solid fa-circle-check"></i> 订单已支付成功！
                            </div>
                        <?php } else { ?>
                            <?php if ($conf['captcha_open_test'] == 1) { ?>
                                <div class="mb-3" id="captcha">
                                    <div id="captcha_text" class="text-muted">正在加载验证码</div>
                                    <div id="captcha_wait" class="text-center py-2">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </div>
                                    <div id="captchaform"></div>
                                </div>
                            <?php } ?>

                            <div class="d-grid gap-2">
                                <?php foreach ($paytype as $rows) { ?>
                                    <button type="button" name="type" value="<?php echo $rows['id'] ?>" class="btn btn-outline-primary btn-pay" onclick="submitPay(this)">
                                        <img src="/assets/icon/<?php echo $rows['name'] ?>.ico" alt=""> <?php echo $rows['showname'] ?>
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </form>
                </div>
                <div class="card-footer text-center text-muted small">
                    <?php echo $conf['sitename']?> © <?php echo date("Y")?> All Rights Reserved.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.staticfile.org/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.staticfile.org/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.staticfile.org/layer/3.1.1/layer.min.js"></script>
<script src="//static.geetest.com/static/tools/gt.js"></script>
<script>
    var captcha_open = 0;
    var handlerEmbed = function (captchaObj) {
        captchaObj.appendTo('#captcha');
        captchaObj.onReady(function () {
            $("#captcha_wait").hide();
        }).onSuccess(function () {
            var result = captchaObj.getValidate();
            if (!result) {
                return alert('请完成验证');
            }
            $("#captchaform").html(
                '<input type="hidden" name="geetest_challenge" value="'+result.geetest_challenge+'" />'+
                '<input type="hidden" name="geetest_validate" value="'+result.geetest_validate+'" />'+
                '<input type="hidden" name="geetest_seccode" value="'+result.geetest_seccode+'" />'
            );
            $.captchaObj = captchaObj;
        });
    };

    function submitPay(obj) {
        var csrf_token = $("input[name='csrf_token']").val();
        var money = $("input[name='money']").val();
        var typeid = $(obj).val();

        if (!money) {
            layer.alert("金额不能为空");
            return false;
        }

        var data = {money: money, typeid: typeid, csrf_token: csrf_token};

        if (captcha_open == 1) {
            var geetest_challenge = $("input[name='geetest_challenge']").val();
            var geetest_validate = $("input[name='geetest_validate']").val();
            var geetest_seccode = $("input[name='geetest_seccode']").val();
            if (!geetest_challenge) {
                layer.alert('请先完成滑动验证！');
                return false;
            }
            data = Object.assign(data, {
                geetest_challenge: geetest_challenge,
                geetest_validate: geetest_validate,
                geetest_seccode: geetest_seccode
            });
        }

        var ii = layer.load(2);
        $.ajax({
            type: "POST",
            dataType: "json",
            data: data,
            url: "ajax.php?act=testpay",
            success: function (data) {
                layer.close(ii);
                if (data.code == 0) {
                    window.location.href = data.url;
                } else {
                    layer.alert(data.msg, {icon: 2});
                    if ($.captchaObj) $.captchaObj.reset();
                }
            },
            error: function () {
                layer.msg('服务器错误', {icon: 2});
            }
        });
    }

    $(function () {
        if ($("#captcha").length > 0) captcha_open = 1;
        if (captcha_open == 1) {
            $.ajax({
                url: "./ajax.php?act=captcha&t=" + (new Date()).getTime(),
                type: "get",
                dataType: "json",
                success: function (data) {
                    $('#captcha_text').hide();
                    $('#captcha_wait').show();
                    initGeetest({
                        gt: data.gt,
                        challenge: data.challenge,
                        new_captcha: data.new_captcha,
                        product: "popup",
                        width: "100%",
                        offline: !data.success
                    }, handlerEmbed);
                }
            });
        }
    });
</script>
</body>
</html>