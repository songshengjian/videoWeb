# 影视站后端 - VideoWeb Backend

基于 Webman + Workerman 的高性能影视聚合站后端服务。

## 部署到 Render

### 一键部署

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy)

### 手动部署步骤

1. **注册 Render 账号**
   - 访问 https://render.com
   - 使用 GitHub 账号登录

2. **创建新服务**
   - 点击 "New +" → "Web Service"
   - 连接你的 GitHub 仓库

3. **配置服务**
   - **Name**: `videoweb-backend`
   - **Environment**: `PHP`
   - **Build Command**: `composer install --no-dev --optimize-autoloader`
   - **Start Command**: `php start.php start`
   - **Plan**: `Free`

4. **环境变量**（如需自定义）
   - `PHP_VERSION`: `8.2`

5. **部署**
   - 点击 "Create Web Service"
   - 等待部署完成

### 部署后

- **服务地址**: `https://videoweb-backend-xxxx.onrender.com`
- **后台登录**: `https://videoweb-backend-xxxx.onrender.com/admin/login`
- **默认账号**: `admin` / `123456`

## 本地开发

### 环境要求

- PHP >= 8.1
- Composer

### 安装

```bash
composer install --no-dev
```

### 启动

```bash
# Linux/Mac
php start.php start

# Windows
php windows.php

# 守护进程模式
php start.php start -d
```

### 访问

- 前台：http://127.0.0.1:8789
- 后台：http://127.0.0.1:8789/admin/login

## 配置

### 修改站点信息

编辑 `common/VideoUtils.php`:

```php
public static function systemName() {
    return '你的站点名';
}

public static function systemLogo() {
    return '你的 Logo URL';
}
```

### 配置视频源

编辑 `common/VideoUtils.php` 中的 `channels()` 方法。

## API 接口

| 功能 | 端点 | 方法 |
|------|------|------|
| 首页视频 | `/` | GET |
| 分类列表 | `/?ac=type` | GET |
| 分类视频 | `/?ac=detail&t={type_id}` | GET |
| 搜索视频 | `/?ac=detail&wd={keyword}` | GET |
| 视频详情 | `/?ac=detail&ids={id}` | GET |
| 播放地址 | `/index/video` | POST |
| 用户登录 | `/admin/login` | POST |

## 技术栈

- **框架**: Webman 5.x
- **运行时**: Workerman 5.x
- **PHP**: 8.1+
- **模板引擎**: Think Template
- **缓存**: Webman Cache

## 注意事项

1. **免费套餐限制**: Render 免费套餐每月 750 小时，足够单应用运行
2. **冷启动**: 免费服务在 15 分钟无访问后会休眠，首次访问需要几秒启动
3. **数据持久化**: 使用 `runtime/` 目录存储临时数据，重启后保留
4. **日志查看**: 在 Render Dashboard 查看实时日志

## 许可证

MIT License

## 免责声明

本项目仅供学习交流使用，请勿用于商业或非法用途。视频资源来源于第三方，版权归原作者所有。
