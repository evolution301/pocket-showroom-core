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
Write-Host "获取 GitHub Token..." -ForegroundColor Gray
$credOutput = echo "protocol=https`nhost=github.com" | git credential-manager get
if ($credOutput -match "password=(.+)") {
    $token = $Matches[1].Trim()
} else {
    Write-Error "无法获取 GitHub Token，请确保已登录 Git"
    exit 1
}

Write-Host ""
Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   Pocket Showroom Release Script       ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""
Write-Host "Version: $Version" -ForegroundColor White
Write-Host "Message: $Message" -ForegroundColor White
Write-Host ""

# 确认发布
$confirm = Read-Host "确认发布? (Y/n)"
if ($confirm -eq "n" -or $confirm -eq "N") {
    Write-Host "已取消" -ForegroundColor Yellow
    exit 0
}

# Step 1: Git Commit
Write-Host ""
Write-Host "[1/6] 提交更改..." -ForegroundColor Yellow
git add -A
git commit -m "Release v${Version}: $Message"

# Step 2: Create Tag
Write-Host "[2/6] 创建 Tag $Version..." -ForegroundColor Yellow
git tag $Version

# Step 3: Push to GitHub
Write-Host "[3/6] 推送到 GitHub..." -ForegroundColor Yellow
git push origin master --tags

# Step 4: Create ZIP (with proper folder structure and version number)
Write-Host "[4/6] 创建 ZIP 包..." -ForegroundColor Yellow
$parentDir = Split-Path -Parent $PWD
$zipPath = Join-Path $parentDir "pocket-showroom-core-$Version.zip"
$tempDir = Join-Path $parentDir "temp-package-$Version"
$pluginDir = Join-Path $tempDir "pocket-showroom-core"

# 创建正确的目录结构
New-Item -ItemType Directory -Force -Path $pluginDir | Out-Null

# 复制文件（排除不需要的）
Get-ChildItem -Path $PWD -Exclude @('.git','temp-package*') | Copy-Item -Destination $pluginDir -Recurse -Force

# 删除不需要发布的文件
Remove-Item -Force (Join-Path $pluginDir 'release.ps1') -ErrorAction SilentlyContinue
Remove-Item -Force (Join-Path $pluginDir 'RELEASE_GUIDE.md') -ErrorAction SilentlyContinue

# 压缩（包含外层 pocket-showroom-core 文件夹）
Compress-Archive -Path $pluginDir -DestinationPath $zipPath -Force

# 清理临时目录
Remove-Item -Recurse -Force $tempDir -ErrorAction SilentlyContinue

# Step 5: Create GitHub Release
Write-Host "[5/6] 创建 GitHub Release..." -ForegroundColor Yellow
$headers = @{
    'Authorization' = "token $token"
    'Accept' = 'application/vnd.github.v3+json'
    'Content-Type' = 'application/json'
}

$changelogUrl = "https://github.com/evolution301/pocket-showroom-core/releases/tag/$Version"
$body = @{
    tag_name = $Version
    name = "v$Version - $Message"
    body = @"
## 更新内容

$Message

---

### 安装方法
1. 下载 \`pocket-showroom-core-$Version.zip\`
2. 进入 WordPress 后台 → 插件 → 安装插件 → 上传插件
3. 上传 ZIP 文件并激活

**完整更新日志**: $changelogUrl
"@
    draft = $false
    prerelease = $false
} | ConvertTo-Json -Depth 10 -Compress $false

try {
    $response = Invoke-RestMethod -Uri 'https://api.github.com/repos/evolution301/pocket-showroom-core/releases' -Method Post -Headers $headers -Body $body
    $releaseId = $response.id
    Write-Host "Release ID: $releaseId" -ForegroundColor Gray
} catch {
    Write-Error "创建 Release 失败: $_"
    exit 1
}

# Step 6: Upload ZIP
Write-Host "[6/6] 上传 ZIP 资产..." -ForegroundColor Yellow
$zipFileName = "pocket-showroom-core-$Version.zip"
$fileBytes = [System.IO.File]::ReadAllBytes($zipPath)
$uploadHeaders = @{
    'Authorization' = "token $token"
    'Accept' = 'application/vnd.github.v3+json'
    'Content-Type' = 'application/zip'
}
$uploadUrl = "https://uploads.github.com/repos/evolution301/pocket-showroom-core/releases/$releaseId/assets?name=$zipFileName"

try {
    Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $uploadHeaders -Body $fileBytes | Out-Null
} catch {
    Write-Error "上传 ZIP 失败: $_"
    exit 1
}

# 完成
Write-Host ""
Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║           发布成功!                    ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "Release URL:" -ForegroundColor White
Write-Host "https://github.com/evolution301/pocket-showroom-core/releases/tag/$Version" -ForegroundColor Cyan
Write-Host ""
Write-Host "Download URL:" -ForegroundColor White
Write-Host "https://github.com/evolution301/pocket-showroom-core/releases/download/$Version/pocket-showroom-core-$Version.zip" -ForegroundColor Cyan
Write-Host ""

# 清理 ZIP
Remove-Item $zipPath -Force
Write-Host "已清理临时 ZIP 文件" -ForegroundColor Gray
