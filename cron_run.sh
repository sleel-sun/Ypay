#!/bin/bash

# 计划任务自动执行脚本
# 用于系统级定时任务调用，自动执行所有计划任务

# 获取脚本所在目录的绝对路径
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)

# 切换到项目目录
cd "$SCRIPT_DIR"

# 执行所有计划任务
php cron.php all

# 退出并返回状态码
exit $?