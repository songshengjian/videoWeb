#!/usr/bin/env php
<?php
chdir(__DIR__);

// 设置上传限制（Render 环境不依赖 php.ini）
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

require_once __DIR__ . '/vendor/autoload.php';
support\App::run();
