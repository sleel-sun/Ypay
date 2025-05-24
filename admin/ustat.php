<?php
/**
 * 商户支付统计
**/
include("../includes/common.php");
$title='商户支付统计';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

?>
<style>
/* 统一的卡片样式 */
.card {
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  background-color: #fff;
  margin-bottom: 20px;
  overflow: hidden;
}
.card-header {
  padding: 15px 20px;
  border-bottom: 1px solid #f0f0f0;
  font-weight: 600;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card-body {
  padding: 20px;
}
/* 表单样式优化 */
.form-control {
  border-radius: 4px;
  border: 1px solid #ddd;
  box-shadow: none;
  transition: border-color 0.15s ease-in-out;
}
.form-control:focus {
  border-color: #3498db;
  box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}
.btn {
  border-radius: 4px;
  font-weight: 500;
  padding: 6px 15px;
  transition: all 0.2s ease;
}
.btn-primary {
  background-color: #3498db;
  border-color: #3498db;
}
.btn-primary:hover {
  background-color: #2980b9;
  border-color: #2980b9;
}
.btn-success {
  background-color: #2ecc71;
  border-color: #2ecc71;
}
.btn-success:hover {
  background-color: #27ae60;
  border-color: #27ae60;
}
/* 图表容器 */
.chart-container {
  height: 350px;
  width: 100%;
}
/* 响应式调整 */
.dates {
  max-width: 140px;
}
@media (max-width: 768px) {
  .dates {
    max-width: 100%;
  }
  .form-inline .form-group {
    margin-bottom: 10px;
    display: block;
    width: 100%;
  }
}
/* 标签页样式 */
.nav-tabs {
  border-bottom: 1px solid #ddd;
  margin-bottom: 20px;
}
.nav-tabs > li > a {
  border-radius: 4px 4px 0 0;
  color: #555;
}
.nav-tabs > li.active > a, 
.nav-tabs > li.active > a:focus, 
.nav-tabs > li.active > a:hover {
  border: 1px solid #ddd;
  border-bottom-color: transparent;
  color: #3498db;
}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
<!-- 引入ECharts -->
<script src="<?php echo $cdnpublic?>echarts/5.4.0/echarts.min.js"></script>

<div class="container" style="padding-top:70px;">
  <div class="col-md-12 center-block" style="float: none;">
    <!-- 查询表单卡片 -->
    <div class="card">
      <div class="card-header">
        <span><i class="fa fa-search"></i> 查询条件</span>
      </div>
      <div class="card-body">
        <form method="GET" class="form-inline" id="searchToolbar">
          <div class="form-group input-daterange">
            <label>查询日期:</label>
            <input type="text" id="day" name="day" class="form-control dates" placeholder="查询日期" autocomplete="off" value="<?php echo date("Y-m-d")?>" required>
          </div>
          <div class="form-group">
            <label>查看方式:</label>
            <select name="method" class="form-control">
              <option value="type">以支付方式查看</option>
              <option value="channel">以支付通道查看</option>
            </select>
          </div>
          <button type="button" class="btn btn-primary" onclick="loadTable()">
            <i class="fa fa-search"></i> 立即查询
          </button>
          &nbsp;
          <a href="javascript:exportTable()" class="btn btn-success" id="exportbtn" style="display:none">
            <i class="fa fa-download"></i> 导出数据
          </a>
        </form>
      </div>
    </div>
    
    <!-- 标签页导航 -->
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation" class="active"><a href="#table-tab" aria-controls="table-tab" role="tab" data-toggle="tab">数据表格</a></li>
      <li role="presentation"><a href="#chart-tab" aria-controls="chart-tab" role="tab" data-toggle="tab">图表展示</a></li>
    </ul>
    
    <!-- 标签页内容 -->
    <div class="tab-content">
      <!-- 表格标签页 -->
      <div role="tabpanel" class="tab-pane active" id="table-tab">
        <div class="card">
          <div class="card-body">
            <table id="listTable" class="table table-striped"></table>
          </div>
        </div>
      </div>
      
      <!-- 图表标签页 -->
      <div role="tabpanel" class="tab-pane" id="chart-tab">
        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <span>支付方式分布</span>
              </div>
              <div class="card-body">
                <div id="pieChart" class="chart-container"></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">
                <span>商户交易排行</span>
              </div>
              <div class="card-body">
                <div id="barChart" class="chart-container"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/bootstrap-table.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
/**
 * 全局变量存储当前查询的数据
 */
var currentData = null;

/**
 * 加载支付统计数据
 * 从服务器获取指定日期和方法的支付统计数据
 */
function loadTable(){
	var day = $("input[name='day']").val();
	var method = $("select[name='method']").val();
	
	if(day == ''){
		layer.msg('查询日期不能为空', {icon: 2, time: 2000});
		return false;
	}
	
	// 显示加载动画
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	
	$.ajax({
		type : "POST",
		url : "ajax_user.php?act=userPayStat",
		data : {day:day, method:method},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				// 保存当前数据用于图表展示
				currentData = data;
				
				// 显示表格数据
				showTable(data);
				
				// 初始化图表
				initCharts(data, method);
				
				// 显示导出按钮
				$("#exportbtn").show();
				
				// 显示成功提示
				layer.msg('数据加载成功', {icon: 1, time: 1000});
			}else{
				layer.alert(data.msg || '数据加载失败', {icon: 2});
			}
		},
		error: function() {
			layer.close(ii);
			layer.alert('网络错误，请稍后重试', {icon: 2});
		}
	});
}

/**
 * 显示数据表格
 * @param {Object} data - 包含表格数据和列定义的对象
 */
function showTable(data){
	// 清除现有表格
	$('#listTable').bootstrapTable('destroy');
	
	// 构建表格列
	var columns = [];
	$.each(data.columns, function(index, item){
		if(index == 'uid'){
			columns.push({
				field: index,
				title: item,
				sortable: true,
				formatter: function(value, row, index) {
					return '<a href="./ulist.php?my=search&column=uid&value='+value+'" target="_blank">'+value+'</a>';
				}
			});
		}else{
			columns.push({
				field: index,
				title: item,
				sortable: true,
				formatter: function(value, row, index) {
					if(value == null) return '0.00';
					else return value.toFixed(2);
				}
			});
		}
	});
	
	// 初始化表格
	$("#listTable").bootstrapTable({
		data: data.data,
		pageSize: 20,
		sidePagination: 'client',
		classes: 'table table-striped table-hover',
		columns: columns,
		search: true,
		showRefresh: true,
		showToggle: true,
		showColumns: true,
		pagination: true,
		pageList: [10, 20, 50, 100],
		responseHandler: function(res) {
			return res;
		}
	});
	
	return false;
}

/**
 * 初始化图表
 * @param {Object} data - 包含统计数据的对象
 * @param {String} method - 查询方法（type或channel）
 */
function initCharts(data, method) {
	// 准备饼图数据
	var pieData = preparePieChartData(data, method);
	// 准备柱状图数据
	var barData = prepareBarChartData(data);
	
	// 初始化饼图
	initPieChart(pieData, method);
	// 初始化柱状图
	initBarChart(barData);
}

/**
 * 准备饼图数据
 * @param {Object} data - 原始数据
 * @param {String} method - 查询方法
 * @return {Array} 处理后的饼图数据
 */
function preparePieChartData(data, method) {
	var pieData = [];
	var total = 0;
	var otherColumns = [];
	
	// 计算总金额并收集列名
	$.each(data.columns, function(index, item) {
		if(index !== 'uid' && index !== 'total') {
			otherColumns.push(index);
		}
	});
	
	// 计算每种支付方式的总金额
	var methodTotals = {};
	$.each(otherColumns, function(i, column) {
		methodTotals[column] = 0;
		$.each(data.data, function(j, row) {
			if(row[column]) {
				methodTotals[column] += parseFloat(row[column]);
			}
		});
	});
	
	// 转换为饼图数据格式
	$.each(methodTotals, function(key, value) {
		if(value > 0) {
			pieData.push({
				name: data.columns[key],
				value: value
			});
		}
	});
	
	return pieData;
}

/**
 * 准备柱状图数据
 * @param {Object} data - 原始数据
 * @return {Object} 处理后的柱状图数据
 */
function prepareBarChartData(data) {
	// 按总金额排序
	var sortedData = data.data.slice().sort(function(a, b) {
		return b.total - a.total;
	});
	
	// 只取前10名
	var top10 = sortedData.slice(0, 10);
	
	// 准备X轴和Y轴数据
	var xAxisData = [];
	var seriesData = [];
	
	$.each(top10, function(index, item) {
		xAxisData.push('商户' + item.uid);
		seriesData.push(item.total);
	});
	
	return {
		xAxis: xAxisData,
		series: seriesData
	};
}

/**
 * 初始化饼图
 * @param {Array} pieData - 饼图数据
 * @param {String} method - 查询方法
 */
function initPieChart(pieData, method) {
	var pieChart = echarts.init(document.getElementById('pieChart'));
	
	var title = method === 'type' ? '支付方式分布' : '支付通道分布';
	
	var option = {
		title: {
			text: title,
			left: 'center'
		},
		tooltip: {
			trigger: 'item',
			formatter: '{a} <br/>{b}: {c} ({d}%)'
		},
		legend: {
			orient: 'vertical',
			left: 'left',
			type: 'scroll'
		},
		series: [
			{
				name: '交易金额',
				type: 'pie',
				radius: ['40%', '70%'],
				avoidLabelOverlap: false,
				itemStyle: {
					borderRadius: 10,
					borderColor: '#fff',
					borderWidth: 2
				},
				label: {
					show: false,
					position: 'center'
				},
				emphasis: {
					label: {
						show: true,
						fontSize: '18',
						fontWeight: 'bold'
					}
				},
				labelLine: {
					show: true
				},
				data: pieData
			}
		]
	};
	
	pieChart.setOption(option);
	
	// 响应窗口大小变化
	window.addEventListener('resize', function() {
		pieChart.resize();
	});
}

/**
 * 初始化柱状图
 * @param {Object} barData - 柱状图数据
 */
function initBarChart(barData) {
	var barChart = echarts.init(document.getElementById('barChart'));
	
	var option = {
		title: {
			text: '商户交易排行(Top10)',
			left: 'center'
		},
		tooltip: {
			trigger: 'axis',
			axisPointer: {
				type: 'shadow'
			}
		},
		grid: {
			left: '3%',
			right: '4%',
			bottom: '3%',
			containLabel: true
		},
		xAxis: {
			type: 'category',
			data: barData.xAxis,
			axisLabel: {
				interval: 0,
				rotate: 30
			}
		},
		yAxis: {
			type: 'value'
		},
		series: [
			{
				name: '交易金额',
				type: 'bar',
				data: barData.series,
				itemStyle: {
					color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
						{offset: 0, color: '#83bff6'},
						{offset: 0.5, color: '#188df0'},
						{offset: 1, color: '#188df0'}
					])
				},
				emphasis: {
					itemStyle: {
						color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
							{offset: 0, color: '#2378f7'},
							{offset: 0.7, color: '#2378f7'},
							{offset: 1, color: '#83bff6'}
						])
					}
				}
			}
		]
	};
	
	barChart.setOption(option);
	
	// 响应窗口大小变化
	window.addEventListener('resize', function() {
		barChart.resize();
	});
}

/**
 * 导出表格数据
 */
function exportTable(){
	var day = $("input[name='day']").val();
	var method = $("select[name='method']").val();
	
	if(day == ''){
		layer.msg('查询日期不能为空', {icon: 2, time: 2000});
		return false;
	}
	
	// 显示导出中提示
	layer.msg('正在准备导出数据...', {icon: 16, time: 1000});
	
	// 跳转到下载页面
	window.location.href='./download.php?act=ustat&day='+day+'&method='+method;
}

// 页面加载完成后初始化
$(document).ready(function(){
	// 初始化日期选择器
	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
    
    // 标签页切换事件
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        // 如果切换到图表标签页且有数据，则重新渲染图表以适应容器大小
        if($(e.target).attr('href') === '#chart-tab' && currentData) {
            var method = $("select[name='method']").val();
            setTimeout(function() {
                initCharts(currentData, method);
            }, 50);
        }
    });
    
    // 初始加载数据
    loadTable();
});
</script>