<?php
include("../includes/common.php");
$title='支付管理中心';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
if($conf['admin_pwd']==='123456'){
	$msg[]='<div class="alert alert-danger"><i class="fa fa-info-circle"></i> 提示：请及时修改网站默认管理员密码！</div>';
}elseif(strlen($conf['admin_pwd'])<6 || is_numeric($conf['admin_pwd']) && strlen($conf['admin_pwd'])<=10 || $conf['admin_pwd']===$conf['kfqq'] || $conf['admin_user']===$conf['admin_pwd']){
	$msg[]='<div class="alert alert-danger"><i class="fa fa-info-circle"></i> 提示：网站管理员密码过于简单，请及时修改密码！</div>';
}
?>

<style>
/* Custom styles for admin dashboard */
.panel {
    margin-bottom: 25px; /* Increased bottom margin for better separation */
}

#payment-legend .list-group-item {
    border: none;
    padding: 8px 12px; /* Adjusted padding */
    font-size: 13px;
    color: white; /* Ensure text is white on colored background */
}
#payment-legend .list-group-item .badge {
    font-size: 12px;
    font-weight: bold;
    background-color: white; /* Ensure badge background is white */
    color: #333; /* Ensure badge text is dark for contrast */
}
#payment-legend .fa-circle {
    margin-right: 5px;
}
.panel-heading .btn-xs { /* Style for refresh buttons in panel headings */
    margin-top: -3px; /* Align refresh button better with title */
    margin-left: 5px;
}
</style>

<div class="container" style="padding-top:70px;">
<div class="col-xs-12 col-lg-10 center-block" style="float: none;">
<div id="browser-notice"></div>

<?php if($msg){foreach($msg as $x){echo $x;}}?>

<!-- 数据统计卡片 -->
<div class="row">
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-shopping-cart fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge" id="count1">0</div>
                        <div>订单总数</div>
                    </div>
                </div>
            </div>
            <a href="./order.php">
                <div class="panel-footer">
                    <span class="pull-left">查看详情</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-green" style="border-color: #5cb85c;">
            <div class="panel-heading" style="background-color: #5cb85c; border-color: #5cb85c;">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-users fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge" id="count2">0</div>
                        <div>商户数量</div>
                    </div>
                </div>
            </div>
            <a href="./ulist.php">
                <div class="panel-footer">
                    <span class="pull-left">查看详情</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-yellow" style="border-color: #f0ad4e;">
            <div class="panel-heading" style="background-color: #f0ad4e; border-color: #f0ad4e;">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-rmb fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge" id="usermoney">0</div>
                        <div>总计余额</div>
                    </div>
                </div>
            </div>
            <a href="./record.php">
                <div class="panel-footer">
                    <span class="pull-left">查看详情</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-red" style="border-color: #d9534f;">
            <div class="panel-heading" style="background-color: #d9534f; border-color: #d9534f;">
                <div class="row">
                    <div class="col-xs-3">
                        <i class="fa fa-credit-card fa-5x"></i>
                    </div>
                    <div class="col-xs-9 text-right">
                        <div class="huge" id="settlemoney">0</div>
                        <div>结算总额</div>
                    </div>
                </div>
            </div>
            <a href="./slist.php">
                <div class="panel-footer">
                    <span class="pull-left">查看详情</span>
                    <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                    <div class="clearfix"></div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- 管理员信息 -->
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-user"></i> 管理员信息</h3>
            </div>
            <div class="panel-body">
                <div class="text-center">
                    <img src="<?php echo ($conf['kfqq'])?'//q2.qlogo.cn/headimg_dl?bs=qq&dst_uin='.htmlspecialchars($conf['kfqq']).'&src_uin='.htmlspecialchars($conf['kfqq']).'&fid='.htmlspecialchars($conf['kfqq']).'&spec=100&url_enc=0&referer=bu_interface&term_type=PC':'../assets/img/user.png'?>" alt="avatar" class="img-circle img-thumbnail" style="width:100px;height:100px;">
                </div>
                <div class="text-center" style="margin-top:15px;">
                    <p><strong>用户名：</strong><span class="text-primary"><?php echo htmlspecialchars($conf['admin_user'])?></span></p>
                    <p><strong>用户权限：</strong><span class="text-warning">管理员</span></p>
                    <p><strong>当前时间：</strong><span><?=htmlspecialchars($date)?></span></p>
                </div>
                <div class="text-center" style="margin-top:15px;">
                    <a href="../" class="btn btn-default"><i class="fa fa-home"></i> 返回首页</a>
                    <a href="./set.php?mod=account" class="btn btn-info"><i class="fa fa-key"></i> 修改密码</a>
                    <a href="./login.php?logout" class="btn btn-danger"><i class="fa fa-sign-out"></i> 退出登录</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 支付方式统计 -->
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pie-chart"></i> 支付方式统计</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-8">
                        <div id="payment-chart" style="height: 250px;"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group" id="payment-legend">
                            <div class="list-group-item" style="background-color: #36a2eb;">
                                <span class="badge" id="legend-alipay">0%</span>
                                <i class="fa fa-circle"></i> 支付宝
                            </div>
                            <div class="list-group-item" style="background-color: #ff6384;">
                                <span class="badge" id="legend-wxpay">0%</span>
                                <i class="fa fa-circle"></i> 微信支付
                            </div>
                            <div class="list-group-item" style="background-color: #ffce56;">
                                <span class="badge" id="legend-qqpay">0%</span>
                                <i class="fa fa-circle"></i> QQ钱包
                            </div>
                            <div class="list-group-item" style="background-color: #4bc0c0;">
                                <span class="badge" id="legend-other">0%</span>
                                <i class="fa fa-circle"></i> 其他方式
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
	  <div class="panel panel-success">
	    <div class="panel-heading"><h3 class="panel-title">支付方式收入统计（1小时更新一次）<span class="pull-right"><a href="javascript:getData(true)" class="btn btn-default btn-xs"><i class="fa fa-refresh"></i></a></span></h3></div>
          <table class="table table-bordered table-striped">
		    <thead><tr id="paytype_head"><th>日期</th></thead>
            <tbody id="paytype_list">
			</tbody>
          </table>
      </div>
	  <div class="panel panel-warning">
	    <div class="panel-heading"><h3 class="panel-title">支付通道收入统计（1小时更新一次）<span class="pull-right"><a href="javascript:getData(true)" class="btn btn-default btn-xs"><i class="fa fa-refresh"></i></a></span></h3></div>
		<div class="table-responsive">
          <table class="table table-bordered table-striped">
		    <thead><tr id="channel_head"><th>日期</th></thead>
            <tbody id="channel_list">
			</tbody>
          </table>
		</div>
      </div>

      <div class="panel panel-info"> <!-- Recent Orders Panel -->
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-list-alt"></i> 最近订单</h3>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>商户ID</th>
                            <th>支付方式</th>
                            <th>金额 (元)</th>
                            <th>状态</th>
                            <th>创建时间</th>
                        </tr>
                    </thead>
                    <tbody id="recent_orders_list">
                        <tr><td colspan="6" class="text-center">正在加载最近订单...</td></tr><!-- 此处使用静态HTML是安全的，因为内容是固定的，不包含动态数据 -->
                    </tbody>
                </table>
            </div>
            <div class="text-center" style="margin-top: 10px;">
                <a href="./order.php" class="btn btn-default btn-sm"><i class="fa fa-search"></i> 查看全部订单</a>
            </div>
        </div>
    </div> <!-- End of Recent Orders Panel -->

    </div>
  </div>
<script>
$(document).ready(function(){
	getData();
	loadRecentOrders(); // Added call to load recent orders
});
/**
 * 从服务器获取统计数据并更新页面。
 * @function getData
 * @param {boolean} getnew - 是否强制获取最新数据，忽略缓存。
 */
function getData(getnew) {
    getnew = getnew || false;
    $('#title').html('正在加载数据中...');
    $.ajax({
        type: "GET",
        url: "ajax.php?act=getcount" + (getnew ? '&getnew=1' : ''),
        dataType: 'json',
        async: true,
        success: function(data) {
            if(data.code === 0){
                updateDashboard(data);
                // 确保 paytype 和 channel 在全局作用域或通过参数传递
                // 这里假设它们是从 data 对象中获取或已在全局定义
                updatePaymentTypeStats(data.paytype || {}, data); 
                updateChannelStats(data.channel || {}, data);
            } else {
                alert('加载统计数据失败: ' + (data.msg || '未知错误'));
            }
        },
        error: function() {
            alert('加载统计数据时发生网络错误，请稍后再试！');
        }
    });
}

var paymentChartInstance;

/**
 * 更新仪表盘上的统计数据。
 * @function updateDashboard
 * @param {object} data - 包含统计数据的对象。
 */
function updateDashboard(data) {
    $('#title').html('后台管理首页');
    $('#count1').text(data.count1);
    $('#count2').text(data.count2);
    $('#usermoney').text(data.usermoney);
    $('#settlemoney').text(data.settlemoney);
}

/**
 * 更新支付方式统计图表和表格。
 * @function updatePaymentTypeStats
 * @param {object} paytype - 支付方式名称映射。
 * @param {object} data - 包含订单数据的对象。
 */
function updatePaymentTypeStats(paytype, data) {
    // 更新表格
    var paytypeTableHead = '<th>日期</th>';
    var paytypeNames = [];
    var todayPaytypeData = [];
    var totalToday = 0;

    $.each(paytype, function(id, name) {
        // 使用jQuery的text方法创建安全的文本节点，然后包装在th标签中
        var $th = $('<th></th>').text(name);
        paytypeTableHead += $th.prop('outerHTML');
        paytypeNames.push(name);
        var amount = (data.order_today.paytype && typeof data.order_today.paytype[id] !== "undefined") ? parseFloat(data.order_today.paytype[id]) : 0;
        todayPaytypeData.push(amount);
        totalToday += amount;
    });
    paytypeTableHead += '<th>总计</th>';
    $("#paytype_head").html(paytypeTableHead);

    var tableRows = '';
    var todayRow = '<td>今日</td>';
    $.each(paytype, function(id, name) {
        var value = ((data.order_today.paytype && typeof data.order_today.paytype[id] !== "undefined") ? data.order_today.paytype[id] : '0.00');
        var $td = $('<td></td>').text(value);
        todayRow += $td.prop('outerHTML');
    });
    var $tdTotal = $('<td></td>').text(data.order_today.all || '0.00');
    todayRow += $tdTotal.prop('outerHTML');
    tableRows += '<tr>' + todayRow + '</tr>';

    $.each(data.order, function(date, dailyData) {
        var $tdDate = $('<td></td>').text(date);
        var historicalRow = $tdDate.prop('outerHTML');
        $.each(paytype, function(id, name) {
            var value = ((dailyData.paytype && typeof dailyData.paytype[id] !== "undefined") ? dailyData.paytype[id] : '0.00');
            var $td = $('<td></td>').text(value);
            historicalRow += $td.prop('outerHTML');
        });
        var $tdTotal = $('<td></td>').text(dailyData.all || '0.00');
        historicalRow += $tdTotal.prop('outerHTML');
        tableRows += '<tr>' + historicalRow + '</tr>';
    });
    $("#paytype_list").html(tableRows);

    // 更新图表
    var chartData = {
        labels: paytypeNames,
        datasets: [{
            label: '支付方式统计',
            data: todayPaytypeData,
            backgroundColor: [
                '#36a2eb', // 支付宝
                '#ff6384', // 微信
                '#ffce56', // QQ钱包
                '#4bc0c0', // 其他
                '#9966ff',
                '#ff9f40'
            ],
            hoverOffset: 4
        }]
    };

    var paymentLegend = $('#payment-legend');
    paymentLegend.empty(); // 清空旧的图例

    if (paymentChartInstance) {
        paymentChartInstance.destroy();
    }

    if(totalToday > 0){
        var ctx = document.getElementById('payment-chart').getContext('2d');
        paymentChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false // 我们将使用自定义图例
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var dataset = data.datasets[tooltipItem.datasetIndex];
                            var currentValue = dataset.data[tooltipItem.index];
                            var percentage = parseFloat((currentValue / totalToday * 100).toFixed(2));
                            return data.labels[tooltipItem.index] + ': ' + currentValue + '元 (' + percentage + '%)';
                        }
                    }
                }
            }
        });

        // 更新自定义图例
        paytypeNames.forEach(function(name, index) {
            var percentage = totalToday > 0 ? (todayPaytypeData[index] / totalToday * 100).toFixed(2) : 0;
            var color = chartData.datasets[0].backgroundColor[index % chartData.datasets[0].backgroundColor.length];
            
            // 使用jQuery创建DOM元素而不是直接拼接HTML字符串
            var $listItem = $('<div class="list-group-item"></div>')
                .css({
                    'background-color': color,
                    'color': 'white'
                });
                
            var $badge = $('<span class="badge"></span>')
                .css({
                    'background-color': 'white',
                    'color': 'black'
                })
                .text(percentage + '%');
                
            var $icon = $('<i class="fa fa-circle"></i>');
            
            // 安全地添加文本内容
            $listItem.append($badge)
                     .append($icon)
                     .append(' ' + name);
                     
            paymentLegend.append($listItem);
        });
    } else {
        $('#payment-chart').html('<p class="text-center" style="line-height: 250px;">今日暂无支付数据</p>');
         paytypeNames.forEach(function(name, index) {
            var color = chartData.datasets[0].backgroundColor[index % chartData.datasets[0].backgroundColor.length];
            
            // 使用jQuery创建DOM元素而不是直接拼接HTML字符串
            var $listItem = $('<div class="list-group-item"></div>')
                .css({
                    'background-color': color,
                    'color': 'white'
                });
                
            var $badge = $('<span class="badge"></span>')
                .css({
                    'background-color': 'white',
                    'color': 'black'
                })
                .text('0%');
                
            var $icon = $('<i class="fa fa-circle"></i>');
            
            // 安全地添加文本内容
            $listItem.append($badge)
                     .append($icon)
                     .append(' ' + name);
                     
            paymentLegend.append($listItem);
        });
    }
}

/**
 * 更新支付通道收入统计表格。
 * @function updateChannelStats
 * @param {object} channel - 支付通道名称映射。
 * @param {object} data - 包含订单数据的对象。
 */
function updateChannelStats(channel, data) {
    var channelTableHead = '<th>日期</th>';
    $.each(channel, function(id, name) {
        var $th = $('<th></th>').text(name);
        channelTableHead += $th.prop('outerHTML');
    });
    channelTableHead += '<th>总计</th>';
    $("#channel_head").html(channelTableHead);

    var tableRows = '';
    var todayRow = '<td>今日</td>';
    $.each(channel, function(id, name) {
        var value = ((data.order_today.channel && typeof data.order_today.channel[id] !== "undefined") ? data.order_today.channel[id] : '0.00');
        var $td = $('<td></td>').text(value);
        todayRow += $td.prop('outerHTML');
    });
    var $tdTotal = $('<td></td>').text(data.order_today.all || '0.00');
    todayRow += $tdTotal.prop('outerHTML');
    tableRows += '<tr>' + todayRow + '</tr>';

    $.each(data.order, function(date, dailyData) {
        var $tdDate = $('<td></td>').text(date);
        var historicalRow = $tdDate.prop('outerHTML');
        $.each(channel, function(id, name) {
            var value = ((dailyData.channel && typeof dailyData.channel[id] !== "undefined") ? dailyData.channel[id] : '0.00');
            var $td = $('<td></td>').text(value);
            historicalRow += $td.prop('outerHTML');
        });
        var $tdTotal = $('<td></td>').text(dailyData.all || '0.00');
        historicalRow += $tdTotal.prop('outerHTML');
        tableRows += '<tr>' + historicalRow + '</tr>';
    });
    $("#channel_list").html(tableRows);
}

/**
 * 加载并显示最近的订单列表。
 * 此函数会向后端发起一个AJAX请求获取最近订单数据，
 * 然后将这些数据显示在ID为 recent_orders_list 的表格中。
 * @function loadRecentOrders
 */
function loadRecentOrders() {
    var tbody = $("#recent_orders_list");
    tbody.html('<tr><td colspan="6" class="text-center">正在加载最近订单...</td></tr>'); // Initial loading message

    // AJAX call to fetch recent orders
    // The endpoint ajax.php?act=getrecentorders needs to be implemented on the server-side.
    // It should return JSON like: {status: "success", orders: [{trade_no: "...", pid: "...", type_name: "...", money: "...", status: 1, addtime: "..."}, ...]}
    $.ajax({
        type: "GET",
        url: "ajax.php?act=getrecentorders", // Ensure this endpoint exists and returns data in the expected format
        dataType: 'json',
        async: true,
        success: function(response) {
            tbody.empty(); // Clear loading message
            if (response && response.status === 'success' && response.orders && response.orders.length > 0) {
                $.each(response.orders, function(index, order) {
                    var $statusSpan;
                    if (order.status == 1) { // 1: 已完成
                        $statusSpan = $('<span class="label label-success"></span>').text('已完成');
                    } else if (order.status == 0) { // 0: 待支付
                        $statusSpan = $('<span class="label label-warning"></span>').text('待支付');
                    } else { // Other statuses
                        $statusSpan = $('<span class="label label-default"></span>').text('未知 (' + order.status + ')');
                    }
                    
                    // 使用jQuery创建DOM元素而不是直接拼接HTML字符串
                    var $row = $('<tr></tr>');
                    $row.append($('<td></td>').text(order.trade_no || 'N/A'));
                    $row.append($('<td></td>').text(order.pid || 'N/A'));
                    $row.append($('<td></td>').text(order.type_name || 'N/A'));
                    $row.append($('<td></td>').text(order.money || '0.00'));
                    
                    var $statusTd = $('<td></td>');
                    $statusTd.append($statusSpan);
                    $row.append($statusTd);
                    
                    $row.append($('<td></td>').text(order.addtime || 'N/A'));
                    tbody.append($row);
                });
            } else if (response && response.orders && response.orders.length === 0) {
                tbody.empty();
                var $emptyRow = $('<tr></tr>');
                var $emptyCell = $('<td colspan="6" class="text-center"></td>').text('暂无最近订单记录');
                $emptyRow.append($emptyCell);
                tbody.append($emptyRow);
            } else {
                tbody.empty();
                var $errorRow = $('<tr></tr>');
                var $errorCell = $('<td colspan="6" class="text-center"></td>').text('加载订单数据失败或格式不正确');
                $errorRow.append($errorCell);
                tbody.append($errorRow);
            }
        },
        error: function() {
            tbody.empty(); // Clear loading message
            var $errorRow = $('<tr></tr>');
            var $errorCell = $('<td colspan="6" class="text-center"></td>').text('加载最近订单失败，请检查网络或联系管理员。');
            $errorRow.append($errorCell);
            tbody.append($errorRow);
        }
    });
}
</script>
<script>
function speedModeNotice(){
	var ua = window.navigator.userAgent;
	if(ua.indexOf('Windows NT')>-1 && ua.indexOf('Trident/')>-1){
		// 使用jQuery创建DOM元素而不是直接拼接HTML字符串
		var $panel = $('<div class="panel panel-default"></div>');
		var $panelBody = $('<div class="panel-body"></div>');
		
		// 添加文本和样式化元素
		$panelBody.text('当前浏览器是兼容模式，为确保后台功能正常使用，请切换到');
		
		var $speedMode = $('<b></b>').text('极速模式').css('color', '#51b72f');
		$panelBody.append($speedMode);
		$panelBody.append('！');
		$panelBody.append($('<br>'));
		$panelBody.append('操作方法：点击浏览器地址栏右侧的IE符号');
		
		var $ieIcon = $('<b></b>').css('color', '#51b72f');
		$ieIcon.append($('<i class="fa fa-internet-explorer fa-fw"></i>'));
		$panelBody.append($ieIcon);
		
		$panelBody.append('→选择"');
		
		var $flashIcon = $('<b></b>').css('color', '#51b72f');
		$flashIcon.append($('<i class="fa fa-flash fa-fw"></i>'));
		$panelBody.append($flashIcon);
		
		var $speedModeText = $('<b></b>').text('极速模式').css('color', '#51b72f');
		$panelBody.append($speedModeText);
		$panelBody.append('"');
		
		$panel.append($panelBody);
		
		// 清空并添加新创建的元素
		$("#browser-notice").empty().append($panel);
	}
}
speedModeNotice();
</script>