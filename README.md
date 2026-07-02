# 收款网站 · 部署说明

教育公司在线报名收款系统，PHP 8.2 + MySQL，适配宝塔面板。

## 功能一览

**前台（含手机版自适应）**
- 首页课程列表（按分类展示）
- 课程详情页
- 报名信息填写 + 支付方式选择
- 支付页 / 支付结果页

**管理后台** `/admin`
- 控制台：收款数据概览、最近订单
- 订单管理：筛选、搜索、手动标记已付/退款
- 商品管理：课程上架/下架、增删改、封面上传、特色标签
- 支付渠道：支付宝/微信/财付通开关 + 参数配置
- 站点设置：网站名称、副标题、联系方式、ICP、公告等自定义；修改管理员账号密码

## 宝塔部署步骤

### 1. 上传代码
将整个目录上传到宝塔站点根目录（如 `/www/wwwroot/yourdomain.com`）。

### 2. 创建数据库
宝塔 → 数据库 → 添加数据库，记下库名、用户名、密码。

### 3. 修改配置
编辑 `includes/config.php`，填写：
- `DB_NAME` / `DB_USER` / `DB_PASS` — 第 2 步的数据库信息
- `APP_URL` — 你的域名（如 `https://pay.yourdomain.com`，末尾不加斜杠）
- `SECRET_KEY` — 改成一段随机字符串

### 4. 设置 PHP 版本与权限
- 站点设置 → PHP 版本选择 **8.2**
- `uploads/` 和 `includes/` 目录需有写权限（宝塔默认 www 用户即可）

### 5. 配置伪静态
- **Nginx**：站点设置 → 伪静态，粘贴 `nginx-rewrite.conf` 内容
- **Apache**：`.htaccess` 已包含规则，自动生效

### 6. 运行安装向导
浏览器访问 `https://yourdomain.com/install.php`：
- 自动检查环境
- 自动建表、导入测试课程
- 设置管理员用户名和密码

### 7. 删除安装文件（重要）
安装成功后**立即删除**：
- `install.php`
- `install.sql`

### 8. 登录后台
访问 `https://yourdomain.com/admin`，用第 6 步设置的账号登录。

## 接入真实支付

系统已搭好支付框架和回调接收，但**真实扣款需接入官方 SDK**：

1. 在后台「支付渠道」填写各平台的 AppID、密钥等参数，并开启渠道。
2. 安装 SDK（在项目根目录）：
   ```bash
   # 支付宝
   composer require alipaysdk/easysdk
   # 微信支付 V3
   composer require wechatpay/wechatpay
   ```
3. 在 `pay.php` 中取消对应渠道的 `TODO` 注释，调用 SDK 生成支付链接/二维码。
4. 回调验签逻辑在 `notify/alipay.php`、`notify/wechat.php`，按 SDK 文档补全验签即可。

**在接入 SDK 之前**，支付页会提示「渠道待接入」，并展示订单号引导用户联系客服——
你也可以在后台订单管理里**手动标记已付**完成对账，系统当前即可正常收集报名信息。

## 安全提示
- 务必删除 `install.php` 和 `install.sql`
- `includes/` 目录已通过伪静态禁止外部访问
- `uploads/` 目录已禁止执行 PHP
- 管理后台有 CSRF 防护、会话超时、密码 bcrypt 加密
- 建议站点开启 HTTPS（宝塔可一键申请 Let's Encrypt 证书）

## 目录结构
```
├── index.php           前台首页
├── product.php         课程详情
├── checkout.php        报名结账
├── pay.php             支付页
├── result.php          支付结果
├── install.php         安装向导（装完删除）
├── install.sql         建表脚本（装完删除）
├── includes/           核心代码（配置/数据库/函数/鉴权）
├── notify/             支付异步回调
├── admin/              管理后台
├── assets/             CSS / JS
└── uploads/products/   商品图片
```

## 默认测试课程
安装后自带 5 个培训类测试商品（考公、Python、自考、企业内训等），
可在后台「商品管理」中编辑或删除。

## 本地已验证
已通过 PHP 8.2 + MySQL 8.0 完整端到端测试：建表、安装、登录鉴权、
下单、订单写库、支付页渲染、后台各页面均正常。
