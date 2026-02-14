# Pocket Showroom Core | Pocket Showroom 核心插件

[![GitHub release](https://img.shields.io/github/v/release/evolution301/pocket-showroom-core?include_prereleases)](https://github.com/evolution301/pocket-showroom-core/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net/)
[![License](https://img.shields.io/github/license/evolution301/pocket-showroom-core)](LICENSE)

**English** | [中文](#中文文档)

A modern B2B product catalog WordPress plugin designed for furniture exporters, wholesalers, and manufacturers. Replace traditional PDF catalogs with an interactive online showroom experience.

---

## Features

- **Interactive Product Gallery** - Modern masonry grid layout with smooth animations, category filtering, and real-time search
- **CSV Bulk Import/Export** - Import hundreds of products in seconds with the built-in CSV importer
- **Multi-Image Gallery** - Drag-and-drop image upload with reordering support
- **Social Sharing** - Beautiful Open Graph cards optimized for WhatsApp/WeChat sharing with QR code generation
- **Image Watermarking** - Automatic watermark protection on uploaded product images
- **AJAX-powered Details** - View product details in a sleek modal without page refresh
- **Fully Responsive** - Perfect display on mobile, tablet, and desktop devices
- **Auto-Update Support** - Receive automatic updates directly from GitHub Releases
- **WeChat Mini Program Support** - Built-in REST API optimized for WeChat Mini Program integration, allowing you to build a native mobile showroom experience
- **REST API** - Complete API endpoints for mobile app and third-party integration
- **SEO Optimized** - Clean code structure and Open Graph meta tags

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Installation

### Method 1: Download from GitHub (Recommended)

1. Download the latest release: [pocket-showroom-core-v1.1.2.zip](https://github.com/evolution301/pocket-showroom-core/releases/download/1.1.2/pocket-showroom-core.zip)
2. Go to **WordPress Admin** → **Plugins** → **Add New** → **Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin
5. Configure settings under **Pocket Showroom** → **Settings**

### Method 2: Manual Installation

1. Clone or download this repository
2. Upload the `pocket-showroom-core` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Configure settings under **Pocket Showroom** → **Settings**

## Quick Start

### Step 1: Create Categories

1. Go to **Pocket Showroom** → **Collections**
2. Add your product categories (e.g., Living Room, Bedroom, Outdoor)

### Step 2: Add Products

1. Go to **Pocket Showroom** → **Add New**
2. Fill in product details:
   - **Title** - Product name
   - **Model** - Product model/SKU number
   - **Material** - Material description
   - **Size Variants** - Multiple sizes with prices
   - **MOQ** - Minimum Order Quantity
   - **Lead Time** - Production/delivery time
   - **Gallery Images** - Upload multiple product images
3. Select a category
4. Publish

### Step 3: Display the Showroom

Add the shortcode to any page or post:

```
[pocket_showroom]
```

Or use in PHP template:

```php
<?php echo do_shortcode('[pocket_showroom]'); ?>
```

## CSV Import/Export

### Export Template

1. Go to **Pocket Showroom** → **Import/Export**
2. Click **Download CSV Template** to get the template file

### Import Products

1. Fill the CSV file with your product data
2. Go to **Pocket Showroom** → **Import/Export**
3. Upload the CSV file
4. Click **Import**

### CSV Format

| Column | Description |
|--------|-------------|
| `post_title` | Product name |
| `_ps_model` | Model/SKU number |
| `_ps_material` | Material |
| `_ps_moq` | Minimum Order Quantity |
| `_ps_lead_time` | Lead time |
| `_ps_list_price` | Price |
| `ps_category` | Category slug |
| `post_content` | Product description |

## REST API & WeChat Mini Program

The plugin provides REST API endpoints for **WeChat Mini Program** and mobile app integration:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/ps/v1/products` | GET | Get product list (paginated, filterable) |
| `/wp-json/ps/v1/products/{id}` | GET | Get single product detail |
| `/wp-json/ps/v1/categories` | GET | Get all categories |
| `/wp-json/ps/v1/banner` | GET | Get banner configuration |
| `/wp-json/ps/v1/ping` | GET | Health check |

### WeChat Mini Program Integration

This plugin is designed to work seamlessly with WeChat Mini Programs. The REST API provides:

- **Product Data** - All product fields including model, material, size variants, MOQ, lead time
- **Image Gallery** - Multiple product images with URLs
- **Category Navigation** - Category hierarchy for filtering
- **Banner Configuration** - Dynamic banner settings from WordPress admin
- **Rate Limiting** - Built-in protection (120 requests/minute per IP)

Example API response for a product:

```json
{
  "id": 123,
  "title": "Modern Sofa",
  "model": "SF-2024-001",
  "price": "$599",
  "material": "Leather",
  "moq": "10 pcs",
  "leadTime": "30 days",
  "gallery": ["https://example.com/img1.jpg", "https://example.com/img2.jpg"],
  "sizeVariants": [
    {"size": "2-seater", "price": "$599"},
    {"size": "3-seater", "price": "$799"}
  ],
  "categories": [{"id": 1, "name": "Living Room", "slug": "living-room"}]
}
```

## Auto-Updates

This plugin supports automatic updates from GitHub Releases:

1. When a new version is released, you'll see an update notification in **WordPress Admin** → **Plugins**
2. Click **Update Now** to automatically download and install the update
3. You can also manually check for updates using the **Check for Updates** link

## Customization

### Colors

Go to **Pocket Showroom** → **Settings** to customize:
- Primary color
- Button text color
- Banner overlay color

### Banner

Customize the homepage banner:
- Banner image
- Title and description
- Button text and URL

### Watermark

Configure image watermark:
- Watermark text
- Opacity
- Font size
- Position

## Changelog

### v1.1.2 (2026-02-14)
- Fix: Critical error due to missing file dependencies
- Improvement: Enhanced error handling for cloud connectivity

### v1.1.1 (2026-02-14)
- Initial public release
- GitHub auto-update support
- Bug fixes and performance improvements

### v1.1.0
- Added CSV import/export functionality
- Multi-image gallery support
- Social sharing with Open Graph tags

### v1.0.0
- Initial release

## Support

- **Issues**: [GitHub Issues](https://github.com/evolution301/pocket-showroom-core/issues)
- **Discussions**: [GitHub Discussions](https://github.com/evolution301/pocket-showroom-core/discussions)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

## Author

**Evolution301**
- GitHub: [@evolution301](https://github.com/evolution301)

---

# 中文文档

一款现代化的 B2B 产品目录 WordPress 插件，专为家具出口商、批发商和制造商设计。用交互式在线展厅体验替代传统的 PDF 目录。

---

## 功能特点

- **交互式产品画廊** - 现代瀑布流网格布局，流畅动画，分类筛选和实时搜索
- **CSV 批量导入/导出** - 使用内置 CSV 导入器秒速导入数百个产品
- **多图画廊** - 拖拽上传图片，支持排序
- **社交分享** - 为 WhatsApp/微信分享优化的精美 Open Graph 卡片，支持二维码生成
- **图片水印** - 上传的产品图片自动添加水印保护
- **AJAX 详情展示** - 在精美弹窗中查看产品详情，无需刷新页面
- **完全响应式** - 在手机、平板和桌面设备上完美显示
- **自动更新支持** - 直接从 GitHub Releases 接收自动更新
- **微信小程序支持** - 内置 REST API，专为微信小程序优化，可构建原生移动展厅体验
- **REST API** - 完整的 API 接口，支持移动应用和第三方集成
- **SEO 优化** - 清晰的代码结构和 Open Graph 元标签

## 系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本

## 安装方法

### 方法一：从 GitHub 下载（推荐）

1. 下载最新版本：[pocket-showroom-core-v1.1.2.zip](https://github.com/evolution301/pocket-showroom-core/releases/download/1.1.2/pocket-showroom-core.zip)
2. 进入 **WordPress 后台** → **插件** → **安装插件** → **上传插件**
3. 上传 ZIP 文件，点击 **现在安装**
4. 激活插件
5. 在 **Pocket Showroom** → **设置** 中配置

### 方法二：手动安装

1. 克隆或下载本仓库
2. 将 `pocket-showroom-core` 文件夹上传到 `/wp-content/plugins/`
3. 在 WordPress **插件** 菜单中激活插件
4. 在 **Pocket Showroom** → **设置** 中配置

## 快速开始

### 第一步：创建分类

1. 进入 **Pocket Showroom** → **产品系列**
2. 添加产品分类（如：客厅、卧室、户外）

### 第二步：添加产品

1. 进入 **Pocket Showroom** → **添加新产品**
2. 填写产品信息：
   - **标题** - 产品名称
   - **型号** - 产品型号/SKU 编号
   - **材质** - 材质描述
   - **尺寸变体** - 多个尺寸及价格
   - **起订量** - 最小订购数量
   - **交货期** - 生产/交货时间
   - **图集** - 上传多张产品图片
3. 选择分类
4. 发布

### 第三步：显示展厅

在任何页面或文章中添加短代码：

```
[pocket_showroom]
```

或在 PHP 模板中使用：

```php
<?php echo do_shortcode('[pocket_showroom]'); ?>
```

## CSV 导入/导出

### 导出模板

1. 进入 **Pocket Showroom** → **导入/导出**
2. 点击 **下载 CSV 模板** 获取模板文件

### 导入产品

1. 在 CSV 文件中填写产品数据
2. 进入 **Pocket Showroom** → **导入/导出**
3. 上传 CSV 文件
4. 点击 **导入**

### CSV 格式说明

| 列名 | 说明 |
|------|------|
| `post_title` | 产品名称 |
| `_ps_model` | 型号/SKU 编号 |
| `_ps_material` | 材质 |
| `_ps_moq` | 起订量 |
| `_ps_lead_time` | 交货期 |
| `_ps_list_price` | 价格 |
| `ps_category` | 分类别名 |
| `post_content` | 产品描述 |

## REST API 与微信小程序

插件提供专为**微信小程序**和移动应用集成设计的 REST API 接口：

| 接口 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ps/v1/products` | GET | 获取产品列表（支持分页、筛选） |
| `/wp-json/ps/v1/products/{id}` | GET | 获取单个产品详情 |
| `/wp-json/ps/v1/categories` | GET | 获取所有分类 |
| `/wp-json/ps/v1/banner` | GET | 获取横幅配置 |
| `/wp-json/ps/v1/ping` | GET | 健康检查 |

### 微信小程序集成

本插件专为微信小程序无缝集成而设计。REST API 提供：

- **完整产品数据** - 包括型号、材质、尺寸变体、起订量、交货期等所有字段
- **多图画廊** - 多张产品图片 URL
- **分类导航** - 分类层级结构，支持筛选
- **横幅配置** - 从 WordPress 后台动态获取横幅设置
- **请求限流** - 内置保护机制（每 IP 每分钟 120 次请求）

产品 API 返回示例：

```json
{
  "id": 123,
  "title": "现代沙发",
  "model": "SF-2024-001",
  "price": "$599",
  "material": "真皮",
  "moq": "10 件",
  "leadTime": "30 天",
  "gallery": ["https://example.com/img1.jpg", "https://example.com/img2.jpg"],
  "sizeVariants": [
    {"size": "双人位", "price": "$599"},
    {"size": "三人位", "price": "$799"}
  ],
  "categories": [{"id": 1, "name": "客厅", "slug": "living-room"}]
}
```

## 自动更新

本插件支持从 GitHub Releases 自动更新：

1. 当发布新版本时，您会在 **WordPress 后台** → **插件** 中看到更新通知
2. 点击 **现在更新** 自动下载并安装更新
3. 您也可以使用 **检查更新** 链接手动检查更新

## 自定义设置

### 颜色

进入 **Pocket Showroom** → **设置** 自定义：
- 主题色
- 按钮文字颜色
- 横幅遮罩颜色

### 横幅

自定义首页横幅：
- 横幅图片
- 标题和描述
- 按钮文字和链接

### 水印

配置图片水印：
- 水印文字
- 透明度
- 字体大小
- 位置

## 更新日志

### v1.1.2 (2026-02-14)
- 修复：文件依赖缺失导致的严重错误
- 改进：增强云端连接错误处理

### v1.1.1 (2026-02-14)
- 首次公开发布
- GitHub 自动更新支持
- Bug 修复和性能优化

### v1.1.0
- 添加 CSV 导入/导出功能
- 多图画廊支持
- 社交分享和 Open Graph 标签

### v1.0.0
- 初始版本

## 技术支持

- **问题反馈**: [GitHub Issues](https://github.com/evolution301/pocket-showroom-core/issues)
- **讨论交流**: [GitHub Discussions](https://github.com/evolution301/pocket-showroom-core/discussions)

## 参与贡献

欢迎贡献代码！请随时提交 Pull Request。

## 许可证

本插件基于 GPLv2 或更高版本许可。详见 [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)。

## 作者

**Evolution301**
- GitHub: [@evolution301](https://github.com/evolution301)

---

⭐ 如果这个项目对您有帮助，请给个 Star 支持！
