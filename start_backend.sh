#!/bin/bash

# 检查Python是否安装
if ! command -v python3 &> /dev/null; then
    echo "错误: 未找到Python3。请安装Python3后再试。"
    exit 1
fi

# 检查依赖
echo "检查和安装依赖..."
python3 -m pip install fastapi uvicorn aiohttp python-multipart

# 确保存在必要的目录
mkdir -p uploads runs cache

# 启动后端服务
echo "启动Deepchat后端服务..."
python3 backend.py 