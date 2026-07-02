<?php
/**
 * 站点配置文件
 * 修改 DB_* 常量以匹配你的宝塔数据库设置
 */

// ── 数据库 ──────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_NAME',     'huixue_pay');   // 数据库名，请先在宝塔中创建
define('DB_USER',     'huixue_user');  // 数据库用户名
define('DB_PASS',     'your_db_password_here');  // 数据库密码
define('DB_CHARSET',  'utf8mb4');

// ── 应用 ──────────────────────────────────────────
define('APP_VERSION', '1.0.0');
define('APP_ROOT',    dirname(__DIR__));
define('APP_URL',     'https://yourdomain.com');   // 部署后修改为实际域名，末尾不加斜杠

// ── 安全 ──────────────────────────────────────────
define('SECRET_KEY',  'change_this_to_a_long_random_string_32chars');  // 请修改为随机字符串
define('SESSION_LIFETIME', 7200);   // 管理员会话有效期（秒）

// ── 上传 ──────────────────────────────────────────
define('UPLOAD_DIR',  APP_ROOT . '/uploads/products/');
define('UPLOAD_URL',  APP_URL  . '/uploads/products/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024);  // 2MB

// ── 时区 ──────────────────────────────────────────
date_default_timezone_set('Asia/Shanghai');
