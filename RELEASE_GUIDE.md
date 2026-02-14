# Pocket Showroom Core - 发布指南

> 本文档记录如何将插件新版本发布到 GitHub Releases

---

## 快速发布清单

### 1️⃣ 更新版本号

需要同时更新 **3 个文件** 中的版本号：

| 文件 | 需要更新的位置 |
|------|---------------|
| `pocket-showroom-core.php` | `Version: X.X.X` 和 `PS_CORE_VERSION` 常量 |
| `readme.txt` | `Stable tag: X.X.X` |
| `README.md` | 下载链接 URL 和 Changelog 章节 |

### 2️⃣ 提交代码

```bash
cd "C:\Users\A\Desktop\AI 项目\Pocket Showroom\pocket-showroom-core"

# 查看更改
git status
git diff

# 添加所有更改
git add -A

# 提交
git commit -m "Release v1.1.3: 简短描述更新内容"
```

### 3️⃣ 创建 Tag

```bash
# 创建 tag（注意：不要加 v 前缀，使用纯数字如 1.1.3）
git tag 1.1.3

# 查看已有 tags
git tag
```

### 4️⃣ 推送到 GitHub

```bash
# 推送代码和 tags
git push origin master --tags
```

### 5️⃣ 创建 ZIP 包

```bash
# 在项目根目录执行（Pocket Showroom 文件夹）
cd "C:\Users\A\Desktop\AI 项目\Pocket Showroom"

# 使用 PowerShell 压缩
powershell -Command "Compress-Archive -Path 'pocket-showroom-core\*' -DestinationPath 'pocket-showroom-core.zip' -Force"
```

### 6️⃣ 创建 GitHub Release（使用 API）

**获取 Token（如已登录 Git 可跳过）：**
```bash
# 从 Git Credential Manager 获取 token
echo "protocol=https
host=github.com" | git credential-manager get
# 输出中的 password= 后面就是 token
```

**创建 Release：**
```powershell
# 替换下面的 YOUR_TOKEN 和版本号
$headers = @{
    'Authorization' = 'token YOUR_TOKEN'
    'Accept' = 'application/vnd.github.v3+json'
    'Content-Type' = 'application/json'
}

$body = @{
    tag_name = '1.1.3'
    name = 'v1.1.3 - 更新标题'
    body = @'
## 更新内容

### 新功能
- 功能描述

### 修复
- 修复描述

### 安装
下载 pocket-showroom-core.zip 并上传到 WordPress

**Full Changelog**: https://github.com/evolution301/pocket-showroom-core/compare/上一版本...当前版本
'@
    draft = $false
    prerelease = $false
} | ConvertTo-Json -Depth 10

$response = Invoke-RestMethod -Uri 'https://api.github.com/repos/evolution301/pocket-showroom-core/releases' -Method Post -Headers $headers -Body $body
Write-Output $response.upload_url
# 记录输出的 upload_url 中的 release ID，下一步需要用
```

### 7️⃣ 上传 ZIP 资产

```powershell
# 替换 YOUR_TOKEN 和 RELEASE_ID（从上一步获取）
$filePath = 'C:\Users\A\Desktop\AI 项目\Pocket Showroom\pocket-showroom-core.zip'
$fileBytes = [System.IO.File]::ReadAllBytes($filePath)

$headers = @{
    'Authorization' = 'token YOUR_TOKEN'
    'Accept' = 'application/vnd.github.v3+json'
    'Content-Type' = 'application/zip'
}

$uploadUrl = 'https://uploads.github.com/repos/evolution301/pocket-showroom-core/releases/RELEASE_ID/assets?name=pocket-showroom-core.zip'
Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $headers -Body $fileBytes
```

### 8️⃣ 验证 Release

```powershell
$headers = @{
    'Authorization' = 'token YOUR_TOKEN'
    'Accept' = 'application/vnd.github.v3+json'
}
$response = Invoke-RestMethod -Uri 'https://api.github.com/repos/evolution301/pocket-showroom-core/releases/latest' -Method Get -Headers $headers
Write-Output $response.html_url
```

---

## 一键发布脚本

将以下内容保存为 `release.ps1`，放在 `pocket-showroom-core` 目录：

```powershell
# release.ps1 - Pocket Showroom 一键发布脚本
# 用法: .\release.ps1 -Version "1.1.3" -Message "更新描述"

param(
    [Parameter(Mandatory=$true)]
    [string]$Version,
    
    [Parameter(Mandatory=$true)]
    [string]$Message
)

$ErrorActionPreference = "Stop"

# 获取 GitHub Token
$credOutput = echo "protocol=https`nhost=github.com" | git credential-manager get
$token = ($credOutput | Select-String "password=(.+)").Matches.Groups[1].Value

Write-Host "=== Pocket Showroom Release Script ===" -ForegroundColor Cyan
Write-Host "Version: $Version"
Write-Host "Message: $Message"
Write-Host ""

# Step 1: Git Commit
Write-Host "[1/6] Committing changes..." -ForegroundColor Yellow
git add -A
git commit -m "Release v$Version`: $Message"

# Step 2: Create Tag
Write-Host "[2/6] Creating tag $Version..." -ForegroundColor Yellow
git tag $Version

# Step 3: Push to GitHub
Write-Host "[3/6] Pushing to GitHub..." -ForegroundColor Yellow
git push origin master --tags

# Step 4: Create ZIP
Write-Host "[4/6] Creating ZIP package..." -ForegroundColor Yellow
cd ..
powershell -Command "Compress-Archive -Path 'pocket-showroom-core\*' -DestinationPath 'pocket-showroom-core.zip' -Force"
$zipPath = "$PWD\pocket-showroom-core.zip"

# Step 5: Create GitHub Release
Write-Host "[5/6] Creating GitHub Release..." -ForegroundColor Yellow
$headers = @{
    'Authorization' = "token $token"
    'Accept' = 'application/vnd.github.v3+json'
    'Content-Type' = 'application/json'
}
$body = @{
    tag_name = $Version
    name = "v$Version - $Message"
    body = "## 更新内容`n`n$Message`n`n---`n`n### 安装`n下载 pocket-showroom-core.zip 并上传到 WordPress"
    draft = $false
    prerelease = $false
} | ConvertTo-Json -Depth 10

$response = Invoke-RestMethod -Uri 'https://api.github.com/repos/evolution301/pocket-showroom-core/releases' -Method Post -Headers $headers -Body $body
$releaseId = $response.id
Write-Host "Release created with ID: $releaseId"

# Step 6: Upload ZIP
Write-Host "[6/6] Uploading ZIP asset..." -ForegroundColor Yellow
$fileBytes = [System.IO.File]::ReadAllBytes($zipPath)
$uploadHeaders = @{
    'Authorization' = "token $token"
    'Accept' = 'application/vnd.github.v3+json'
    'Content-Type' = 'application/zip'
}
$uploadUrl = "https://uploads.github.com/repos/evolution301/pocket-showroom-core/releases/$releaseId/assets?name=pocket-showroom-core.zip"
Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $fileBytes

Write-Host ""
Write-Host "=== Release Complete! ===" -ForegroundColor Green
Write-Host "URL: https://github.com/evolution301/pocket-showroom-core/releases/tag/$Version"
```

**使用方法：**
```powershell
cd "C:\Users\A\Desktop\AI 项目\Pocket Showroom\pocket-showroom-core"
.\release.ps1 -Version "1.1.3" -Message "修复XXX问题"
```

---

## 注意事项

1. **版本号格式**：使用纯数字 `1.1.3`，不要加 `v` 前缀
2. **Token 安全**：不要将 token 提交到代码仓库
3. **ZIP 内容**：只压缩 `pocket-showroom-core` 文件夹**内的内容**，不要包含文件夹本身
4. **更新顺序**：先更新版本号 → 提交 → 打 tag → 推送 → 创建 release
5. **自动更新**：WordPress 端会自动检测新版本并提示更新

---

## 相关链接

- **仓库地址**: https://github.com/evolution301/pocket-showroom-core
- **Releases**: https://github.com/evolution301/pocket-showroom-core/releases
- **API 文档**: https://docs.github.com/en/rest/releases
