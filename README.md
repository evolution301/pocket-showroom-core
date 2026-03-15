# Pocket Showroom Core | 数字化产品展厅核心插件

[![GitHub release](https://img.shields.io/github/v/release/evolution301/pocket-showroom-core?include_prereleases&color=007bff)](https://github.com/evolution301/pocket-showroom-core/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/github/license/evolution301/pocket-showroom-core)](LICENSE)

**English** | [中文文档](#中文文档)

一款专为家具出口商、批发商和制造商设计的现代 B2B 产品目录 WordPress 插件。用交互式在线展厅体验彻底替代沉重的 PDF 目录。

---

## 💎 核心价值 (Core Features)

- **🚀 极速导入**：内置工业级 CSV 引擎，智能识别复杂 Excel 表头，数秒内上架千款产品。
- **📱 跨端联动**：完美适配移动端触控，内置 REST API 原生支持微信小程序集成。
- **🛡️ 资产保护**：自动图片水印保护，防范产品设计被随意盗用。
- **✨ 商业级体验**：瀑布流网格布局、无刷新 AJAX 弹窗详情、智能分享二维码。
- **🔄 自动持续進化**：支持从 GitHub 直接获取自动更新，始终运行在性能巅峰。

---

## 🛠️ 安装指引 (Installation)

### 推荐方式：直接下载最新包
1. Download the latest release from GitHub: [pocket-showroom-core-v3.4.5.zip](https://github.com/leokhoa/pocket-showroom-core/releases/latest/download/pocket-showroom-core.zip)
2. 在 WordPress 后台 -> **插件** -> **安装插件** -> **上传插件**
3. 激活并开启您的数字化展厅之旅。

---

## 📈 更新日志 (Changelog)

### v3.4.3 (2026-03-14) - 小程序接口热修复
- **🔌 接口通行**:移除了商品列表等公共接口的强制 API Key 验证，恢复了微信小程序的正常数据读取，同时保留了基础频率限制以防恶意遍历。

### v3.4.2 (2026-03-13) - 工业级稳定性修复
- **🛡️ 状态化计数 (Transient Stats)**: 将导入统计从前端移至服务器端。彻底修复网络波动引发的 AJAX 重试导致的"175成功但实际115行"的计数翻倍问题。
- **📋 自动分行检测 (Delimiter Auto-Detect)**: 支持自动识别分号（;）或逗号（,）分隔的 CSV，完美适配各国 Excel 导出格式。
- **🔍 深度审计日志 (Import Log)**: 在 `wp-content/uploads/ps-imports/` 下实时生成 `import.log`，记录每一行产品的处理命运（新建/更新/失败及原因），确保数据透明可靠。
- **🏷️ 全能表头识别**: 极大丰富了关键词库（Item #, 款名, 型号, Code等），确保您的 Excel 列能被 100% 正确识别。
- **✨ 强制置顶 (Visibility Fix)**: 导入的产品会强制更新发布日期为当前，确保它们立即可见于后台列表首位，不再被"埋没"。

### v3.3.9 (2026-03-13) - 威力加强版
- **🚀 性能巅峰**: 重构了 CSV 解析核心，现在能完美处理包含换行符的复杂 Excel 数据，不再因格式偏差导致导入失败。
- **⚡ 界面优化**: 导入进度条刷新频率提升 500%，在大批量操作时提供更实时的视觉回馈。
- **🔒 安全加固**: 强化了 SSRF 防护机制，保护您的服务器免受恶意内网扫描攻击。
- **🎨 文档美化**: 全面升级了 README 展示，提升商业级质感。

---

## 中文文档

### 为什么选择 Pocket Showroom？
在这个数字化的时代，传统的 PDF 目录已经无法满足 B2B 客户的需求。Pocket Showroom 让您的产品能够以最直观、最现代化的方式展现在全球客户面前。

### 快速上手
1. **创建分类**：在“产品系列”中添加您的产品线（如：现代沙发、欧式餐桌）。
2. **添加产品**：填写型号、尺寸、起订量（MOQ）等关键商业参数。
3. **一键展示**：在任何页面粘贴短代码 `[pocket_showroom]` 即可生成专业展厅。

### 技术支持
如果您在使用中遇到任何问题，或需要定制化开发，请通过 GitHub Issues 联系我们。

---
⭐ 如果这个项目帮到了您，请点击右上角的 **Star** 支持我们！
