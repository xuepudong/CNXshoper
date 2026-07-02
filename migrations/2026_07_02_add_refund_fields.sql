-- ============================================================
-- 退款功能：为 orders 表增加退款相关字段
-- 支持部分退款（累计已退金额），支付宝/微信真退款
--
-- 执行方式（宝塔 phpMyAdmin 或命令行）：
--   mysql -u<user> -p<pass> <dbname> < 2026_07_02_add_refund_fields.sql
-- 可重复执行安全：字段/索引已存在时请忽略 1060/1061 报错。
-- ============================================================

ALTER TABLE `orders`
  ADD COLUMN `refunded_amount` decimal(10,2) NOT NULL DEFAULT 0.00
      COMMENT '累计已退款金额（元），支持部分退款累加' AFTER `notify_data`,
  ADD COLUMN `refund_no`   varchar(64)  DEFAULT NULL COMMENT '最近一次商户退款单号' AFTER `refunded_amount`,
  ADD COLUMN `refund_time` datetime     DEFAULT NULL COMMENT '最近一次退款时间'      AFTER `refund_no`,
  ADD COLUMN `refund_data` text         DEFAULT NULL COMMENT '最近一次退款接口返回原文' AFTER `refund_time`;

-- 微信退款需双向证书，config 里补一个私钥文件路径（cert_path 已存在，用于证书 cert；key_path 存 key）
-- 注意：payment_channels.config 是 JSON 字段，无需 ALTER，配置项在后台保存时自动写入。
