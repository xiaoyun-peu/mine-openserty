# Mineopenserty — Minecraft Server Website

开箱即用的 Minecraft 服务器官网系统。PHP + MySQL 原生实现。

## 特点
利用**Mine**craft**Open****Ser**\verCommuni**ty**

## 功能

- **前台**：首页 / 服务器信息 / 公告系统（Markdown） / 资源下载（含 VirusTotal 安全校验） / 联系我们 / 入服申请 / 注册登录 / 用户面板
- **后台**：仪表盘 / 入服申请审批 / 工单系统 / 公告管理 / 用户管理 / 网站设置 / 资源管理 / Cloudflare Turnstile 人机验证
- **安全**：CSRF 防护 / XSS 过滤 / SQL 注入防护 / 文件上传黑名单 / Session 安全标记

## 快速开始

1. 把 `dist/` 目录部署到 PHP 7.4+ 环境
2. 准备 MySQL 5.7+ 数据库
3. 访问 `setup.php` 填写数据库信息，自动建库建表
4. 默认管理员：`admin` / `123456`

## 配置

所有网站内容（名称、IP、公告、规则、资源等）均在后台管理面板中修改，无需改代码。

## 附加署名条款

除遵守 MIT 许可证外，**任何使用本软件或其衍生作品的行为，均不得以任何形式移除、隐藏或更改软件用户界面及其他本项目署名信息**。

## 声明
Minecraft 是 Mojang Studios 的商标，本项目与 Mojang Studios 及 Microsoft 无关联。
