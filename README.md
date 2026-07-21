# Mineopenserty — Minecraft Server Website

开箱即用的 Minecraft 服务器官网系统。PHP + MySQL 原生实现，深色扁平风格。

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

## 许可证

MIT License

Copyright (c) 2026 Mineopenserty

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

### 附加署名条款

除遵守上述 MIT 许可证外，**任何使用本软件或其衍生作品的行为，均不得以任何形式移除、隐藏或更改软件用户界面（包括但不限于网站页脚）中显示的 "Powered by Mineopenserty" 及其他本项目署名信息**。
