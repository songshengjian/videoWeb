#!/bin/bash
cd /workspace/videoWeb

echo "正在重启 Webman..."

if [ -f runtime/webman.pid ]; then
    PID=$(cat runtime/webman.pid)
    echo "停止旧进程 PID: $PID"
    kill -9 $PID 2>/dev/null
    sleep 1
fi

rm -f runtime/webman.pid
rm -f runtime/webman.status

echo "启动 Webman..."
php start.php start -d

echo "Webman 已重启"
echo "日志路径: runtime/logs/"
