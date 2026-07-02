-- =====================================================
-- 收款网站数据库初始化脚本
-- 适用: PHP 8.2+ / MySQL 5.7+
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 站点配置
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`) VALUES
('site_name', '慧学教育'),
('site_subtitle', '专业·务实·成就未来'),
('site_logo', ''),
('site_icp', ''),
('site_phone', '400-000-0000'),
('site_email', 'service@example.com'),
('site_address', ''),
('site_notice', '报名成功后工作人员将在1个工作日内与您联系，请保持手机畅通。'),
('order_prefix', 'HX'),
('order_expire', '30'),
('admin_username', 'admin'),
('admin_password', '$2y$12$placeholder_will_be_set_by_install');

-- 商品分类（可选扩展）
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `sort_order`) VALUES
('考公培训', 1),
('职业技能', 2),
('学历提升', 3),
('企业内训', 4);

-- 商品表
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `original_price` decimal(10,2) DEFAULT NULL COMMENT '划线价',
  `image` varchar(500) DEFAULT NULL,
  `features` text DEFAULT NULL COMMENT 'JSON格式特色卖点',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `sales` int(11) NOT NULL DEFAULT 0 COMMENT '虚拟销量',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`category_id`, `name`, `subtitle`, `description`, `price`, `original_price`, `features`, `status`, `sort_order`, `sales`) VALUES
(1, '国考笔试全程班', '行测+申论双科强化，名师押题', '<p>本课程针对国家公务员考试笔试阶段精心设计，涵盖<strong>行政职业能力测验</strong>和<strong>申论</strong>全部考查内容。</p><p>课程特色：</p><ul><li>30+专职讲师，平均从业8年以上</li><li>系统化题库，每日定时更新</li><li>直播+录播双模式，随时随地学习</li><li>协议班：未通过笔试可申请退费</li></ul><p>报名后即可加入专属学习群，享受一对一答疑服务。</p>', 2980.00, 4200.00, '["行测专项突破", "申论高分技巧", "历年真题精讲", "协议保障退费"]', 1, 1, 328),
(1, '省考面试特训营', '7天集训，模拟考场真实演练', '<p>省公务员考试面试特训营，采用小班制教学（每班不超过15人），模拟真实考场环境，帮助学员快速掌握面试技巧。</p><p>课程亮点：</p><ul><li>全真模拟考场，一对一点评</li><li>历年真题重现，针对性练习</li><li>心理减压辅导，上场不怯场</li></ul>', 1580.00, 2000.00, '["小班教学15人", "全真模拟考场", "1v1精准点评", "结构化思维"]', 1, 2, 195),
(2, 'Python数据分析就业班', '0基础入门，90天学会数据分析', '<p>专为零基础学员设计的Python数据分析就业课程，从Python基础语法到Pandas、NumPy、Matplotlib全栈掌握，课程结束后可独立完成数据清洗、可视化分析项目。</p><p>就业保障：</p><ul><li>完成课程推荐至合作企业实习</li><li>简历指导+面试模拟</li><li>终身技术社群答疑</li></ul>', 3680.00, 5000.00, '["零基础入门", "项目驱动学习", "就业推荐服务", "终身技术答疑"]', 1, 3, 412),
(3, '自考本科助学服务（专升本）', '专科升本科，学信网可查', '<p>面向大专学历人员，提供全国高等教育自学考试助学服务。帮助学员系统规划备考路径，提供全套复习资料及定期辅导课，助力顺利获取本科学历。</p><p>服务内容：</p><ul><li>一对一学习规划</li><li>全套纸质+电子复习资料</li><li>每月集中辅导4次</li><li>考前押题冲刺</li></ul>', 4800.00, 6500.00, '["正规学历认证", "学信网可查", "考前押题冲刺", "专属学习顾问"]', 1, 4, 267),
(4, '企业管理者沟通表达提升课', '领导力·汇报力·演讲力三合一', '<p>专为企业中层及以上管理人员打造的综合表达力提升课程，涵盖向上汇报、跨部门沟通、公众演讲三大核心能力，课程采用工作坊+实战演练形式，学以致用。</p>', 1280.00, 1600.00, '["工作坊实战形式", "企业定制方案", "小班精英教学", "课后跟踪辅导"]', 1, 5, 89);

-- 支付渠道
CREATE TABLE IF NOT EXISTS `payment_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `config` text DEFAULT NULL COMMENT 'JSON配置',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1启用 0关闭',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payment_channels` (`name`, `code`, `icon`, `config`, `status`, `sort_order`) VALUES
('支付宝', 'alipay', 'alipay', '{"app_id":"","private_key":"","alipay_public_key":"","sandbox":0,"notify_url":"","return_url":""}', 0, 1),
('微信支付', 'wechat', 'wechat', '{"mch_id":"","api_key":"","app_id":"","api_v3_key":"","cert_path":"","notify_url":""}', 0, 2),
('财付通', 'tenpay', 'tenpay', '{"partner":"","key":"","notify_url":"","return_url":""}', 0, 3);

-- 订单表
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(64) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `channel` varchar(50) DEFAULT NULL,
  `buyer_name` varchar(100) DEFAULT NULL,
  `buyer_phone` varchar(20) DEFAULT NULL,
  `buyer_email` varchar(100) DEFAULT NULL,
  `buyer_remark` varchar(500) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待支付 1已支付 2已取消 3已退款',
  `is_anomalous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1到账异常：付款到账但订单非待支付态，需人工核对',
  `trade_no` varchar(200) DEFAULT NULL COMMENT '第三方交易流水号',
  `pay_time` datetime DEFAULT NULL,
  `notify_data` text DEFAULT NULL COMMENT '回调原始数据',
  `refunded_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '累计已退款金额（元），支持部分退款累加',
  `refund_no` varchar(64) DEFAULT NULL COMMENT '最近一次商户退款单号',
  `refund_time` datetime DEFAULT NULL COMMENT '最近一次退款时间',
  `refund_data` text DEFAULT NULL COMMENT '最近一次退款接口返回原文',
  `ip` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_status` (`status`),
  KEY `idx_anomalous` (`is_anomalous`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
