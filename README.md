# DNSHE Lite Manager（PHP + SQLite 绿色版）

本包在原完整版基础上实现了以下需求：

## 1. 域名管理
- 配额显示为：**已注册 X 个，剩余 Y 个注册名额**（来自 quota.used / quota.available）。
- 列名改为 **注册/剩余**：
  - 注册时间取 `subdomains list` 返回的 `created_at`
  - 剩余天数按 365 天倒计时：`max(0, 365 - floor((now-created_at)/86400))`

## 2. DNS记录（新增记录类型下拉）
- 新增记录时 Type 改为下拉选择（A/AAAA/CNAME/MX/TXT/NS/SRV/CAA）。

## 3. 账户管理（续期链接）
- 列表不显示长链接明文，只保留 **复制链接** 一个按钮。
- 复制内容为 **带域名的完整 URL**（可直接用于宝塔计划任务/cron 的 curl）。

## 4. 用户管理（新增编辑/删除）
- 支持对非 admin 用户：
  - 删除用户
  - 编辑用户角色（user/admin）
  - 可选重置密码（重置后强制首次登录改密）

## 部署
1. 解压到站点目录（PHP 8.0+）
2. 目录可写：data/、backups/
3. 默认管理员：admin / 123456Aa（首次登录强制修改密码）

## 计划任务（示例）
在【账户管理】点“复制链接”，得到完整 URL：
```bash
curl -s "<复制的完整URL>" > /dev/null
```
