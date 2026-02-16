# DNSHE Lite Manager（PHP + SQLite 绿色版）— 简易说明

## 这是什么？
DNSHE Lite Manager 是一个 **解压即用** 的 DNSHE 免费域名多账户管理面板，采用 **PHP + SQLite（单文件数据库）**，无需 MySQL。

## 环境要求
- PHP 
- 扩展：SQLite3、curl、json（一般默认启用）
- 推荐：openssl（用于加密存储 API Key/Secret）

## 部署步骤（绿色版）
1. 解压到网站目录，例如：`/www/wwwroot/dnshe-lite-php/`
2. 确保目录可写：  
   - `data/`（SQLite 数据库文件）
   - `backups/`（备份文件）
3. 访问：`http(s)://你的域名/路径/index.php`

## 默认账号
- 默认管理员：`admin / 123456Aa`（首次登录强制修改密码） 

## 功能概览
### 1）账户管理
- 新增/编辑/删除 DNSHE 账户（API Key/Secret 加密存储）
- 支持“自动续期”开关
- 提供“复制续期链接”（复制为可直接用于 curl 的完整 URL）

### 2）域名管理
- 拉取子域名列表、注册新子域名、续期、删除
- **方案2显示规则（当前版本）**：  
  - 配额显示：**已注册 X 个，剩余 Y 个注册名额**（使用 quota 中的 used/available）  
  - 列名显示为 **注册/剩余**：注册时间取 `created_at`，剩余天数按 365 天倒计时计算（UI 规则）  

### 3）DNS 记录管理
- DNS 记录增删改查
- 记录类型支持常见类型（如 A/AAAA/CNAME/MX/TXT 等）  

### 4）日志
- 记录关键操作日志，支持筛选与导出 CSV

### 5）备份/恢复
- 普通用户：导出/导入自己的账户（加密 JSON） 
- 管理员：数据库一键备份/恢复

## 自动续期（定时任务）
系统提供带签名的 HTTP 触发接口 `api/renew.php`： 
- 单账户：`mode=account&id=...&ts=...&sig=...` 
- 全局：`mode=global&ts=...&sig=...`

### 宝塔/cron 示例
```bash
curl -s "<复制的完整续期URL>" > /dev/null

![ScreenShot_2026-02-17_013801_504.png](https://youke.xn--y7xa690gmna.cn/s1/2026/02/17/699357311c36e.webp)

![ScreenShot_2026-02-17_013901_744.png](https://youke.xn--y7xa690gmna.cn/s1/2026/02/17/699357314c726.webp)

![ScreenShot_2026-02-17_013935_414.png](https://youke.xn--y7xa690gmna.cn/s1/2026/02/17/69935731d9715.webp)

![ScreenShot_2026-02-17_013832_020.png](https://youke.xn--y7xa690gmna.cn/s1/2026/02/17/6993573219f3a.webp)

![ScreenShot_2026-02-17_013821_895.png](https://youke.xn--y7xa690gmna.cn/s1/2026/02/17/699357321aaac.webp)
