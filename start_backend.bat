@echo off
echo 正在启动Deepchat后端服务...

:: 检查Python是否安装
python --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo 错误: 未找到Python。请安装Python后再试。
    pause
    exit /b 1
)

:: 检查并安装依赖
echo 检查和安装依赖...
python -m pip install fastapi uvicorn aiohttp python-multipart

:: 创建必要的目录
if not exist uploads mkdir uploads
if not exist runs mkdir runs
if not exist cache mkdir cache

:: 启动后端服务
echo 启动Deepchat后端服务...
python backend.py

pause 