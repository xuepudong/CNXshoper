-- =====================================================
-- 增量迁移：orders 表新增到账异常标记
-- 适用对象：v1.0.0 时已用 install.sql 建过库的老站点
-- 全新安装无需执行（install.sql 已含该字段）
--
-- 用法：宝塔 → 数据库 → 管理(phpMyAdmin) → SQL，粘贴执行；
--       或命令行 mysql -u USER -p DBNAME < migrations/2026_07_01_add_is_anomalous.sql
-- =====================================================

ALTER TABLE `orders`
  ADD COLUMN `is_anomalous` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1到账异常：付款到账但订单非待支付态，需人工核对'
    AFTER `status`,
  ADD KEY `idx_anomalous` (`is_anomalous`);
