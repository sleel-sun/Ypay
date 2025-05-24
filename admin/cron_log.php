<?php
include("../includes/common.php");
$title='计划任务日志';
include './head.php';
if($islogin==1){}
else exit("<script language='javascript'>window.location.href='./login.php';</script>");

// 生成CSRF Token
if(empty($_SESSION['admin_csrf_token']) || empty($_SESSION['admin_csrf_token_time']) || time() - $_SESSION['admin_csrf_token_time'] > 1800){
    $_SESSION['admin_csrf_token'] = md5(uniqid(mt_rand(), true));
    $_SESSION['admin_csrf_token_time'] = time();
}

// 清理日志操作
if(isset($_POST['clean']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] == $_SESSION['admin_csrf_token']){
    $days = intval($_POST['days']);
    if($days > 0){
        $sql = "DELETE FROM pre_cron_log WHERE addtime < DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        if($DB->exec($sql)){
            showmsg('清理成功！', 1);
        }else{
            showmsg('清理失败！'.$DB->error(), 4);
        }
    }else{
        showmsg('请输入有效的天数', 4);
    }
}

// 分页
$pagesize = 30;
$pages = intval($_GET['page']);
if ($pages <= 0) $pages = 1;
$offset = $pagesize * ($pages - 1);

// 查询条件
$where = '1=1';
if(isset($_GET['task']) && !empty($_GET['task'])){
    $task = $DB->escape($_GET['task']);
    $where .= " AND task='{$task}'";
}
if(isset($_GET['status']) && $_GET['status'] !== ''){
    $status = intval($_GET['status']);
    $where .= " AND status='{$status}'";
}
if(isset($_GET['date']) && !empty($_GET['date'])){
    $date = $DB->escape($_GET['date']);
    $where .= " AND addtime LIKE '{$date}%'";
}

// 获取总数
$numrows = $DB->getColumn("SELECT count(*) from pre_cron_log WHERE {$where}");
$pages_total = ceil($numrows / $pagesize);
if ($pages > $pages_total) $pages = $pages_total;

// 获取日志列表
$logs = $DB->getAll("SELECT * FROM pre_cron_log WHERE {$where} ORDER BY id DESC LIMIT $offset,$pagesize");

// 获取任务列表（用于筛选）
$tasks = $DB->getAll("SELECT DISTINCT task FROM pre_cron_log ORDER BY task ASC");

// 获取日期列表（用于筛选）
$dates = $DB->getAll("SELECT DISTINCT DATE(addtime) as date FROM pre_cron_log ORDER BY date DESC LIMIT 30");
?>

<div class="container" style="padding-top:70px;">
    <div class="col-md-12 center-block" style="float: none;">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">计划任务日志</h3>
            </div>
            <div class="panel-body">
                <!-- 筛选表单 -->
                <form method="GET" action="cron_log.php" class="form-inline">
                    <div class="form-group">
                        <label>任务：</label>
                        <select name="task" class="form-control">
                            <option value="">全部</option>
                            <?php foreach($tasks as $row){ ?>
                            <option value="<?php echo $row['task']; ?>" <?php echo isset($_GET['task']) && $_GET['task'] == $row['task'] ? 'selected' : ''; ?>><?php echo $row['task']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>状态：</label>
                        <select name="status" class="form-control">
                            <option value="">全部</option>
                            <option value="1" <?php echo isset($_GET['status']) && $_GET['status'] == '1' ? 'selected' : ''; ?>>成功</option>
                            <option value="0" <?php echo isset($_GET['status']) && $_GET['status'] == '0' ? 'selected' : ''; ?>>失败</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>日期：</label>
                        <select name="date" class="form-control">
                            <option value="">全部</option>
                            <?php foreach($dates as $row){ ?>
                            <option value="<?php echo $row['date']; ?>" <?php echo isset($_GET['date']) && $_GET['date'] == $row['date'] ? 'selected' : ''; ?>><?php echo $row['date']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">查询</button>
                </form>
                <br>
                
                <!-- 清理日志表单 -->
                <form method="POST" action="cron_log.php" class="form-inline pull-right" onsubmit="return confirm('确定要清理指定天数前的日志吗？');">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['admin_csrf_token']; ?>">
                    <div class="form-group">
                        <label>清理</label>
                        <input type="number" name="days" value="30" class="form-control" min="1" max="365" required>
                        <label>天前的日志</label>
                    </div>
                    <button type="submit" name="clean" value="clean" class="btn btn-danger">清理</button>
                </form>
                <br><br>
                
                <!-- 日志列表 -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>任务名称</th>
                                <th>执行结果</th>
                                <th>状态</th>
                                <th>执行时间</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        if($numrows > 0){
                            foreach($logs as $row){
                                echo '<tr>';
                                echo '<td>'.$row['id'].'</td>';
                                echo '<td>'.$row['task'].'</td>';
                                echo '<td>'.htmlspecialchars($row['result']).'</td>';
                                echo '<td>'.($row['status'] ? '<span class="label label-success">成功</span>' : '<span class="label label-danger">失败</span>').'</td>';
                                echo '<td>'.$row['addtime'].'</td>';
                                echo '</tr>';
                            }
                        }else{
                            echo '<tr><td colspan="5" class="text-center">暂无记录</td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 分页 -->
                <ul class="pagination">
                <?php 
                $first=1;
                $prev=$pages-1;
                $next=$pages+1;
                $last=$pages_total;
                if ($pages > 1) {
                    echo '<li><a href="cron_log.php?page='.$first.$link.'">&laquo;</a></li>';
                    echo '<li><a href="cron_log.php?page='.$prev.$link.'">&lsaquo;</a></li>';
                } else {
                    echo '<li class="disabled"><a>&laquo;</a></li>';
                    echo '<li class="disabled"><a>&lsaquo;</a></li>';
                }
                for ($i = 1; $i < $pages; $i++)
                    echo '<li><a href="cron_log.php?page='.$i.$link.'">'.$i.'</a></li>';
                echo '<li class="active"><a>'.$pages.'</a></li>';
                for ($i = $pages + 1; $i <= $pages_total; $i++)
                    echo '<li><a href="cron_log.php?page='.$i.$link.'">'.$i.'</a></li>';
                if ($pages < $pages_total) {
                    echo '<li><a href="cron_log.php?page='.$next.$link.'">&rsaquo;</a></li>';
                    echo '<li><a href="cron_log.php?page='.$last.$link.'">&raquo;</a></li>';
                } else {
                    echo '<li class="disabled"><a>&rsaquo;</a></li>';
                    echo '<li class="disabled"><a>&raquo;</a></li>';
                }
                ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php include './foot.php'; ?>