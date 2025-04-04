# DeepChat

DeepChat is an AI chat application that supports both text conversations and PDF file processing.

## Requirements

- Python 3.8+
- PHP server
- Conda (for environment management)

## Setup

### 1. Create and Activate Virtual Environment

```bash
# Create a new conda environment named 'deepchat'
conda create -n deepchat python=3.9

# Activate the environment
conda activate deepchat
```

### 2. Install Dependencies

```bash
# Navigate to project directory
cd deepchat

# Install required packages
pip install fastapi uvicorn aiohttp PyPDF2
pip install python-multipart
```

### 3. API Key Configuration

You have three options to configure your API key:

#### Option 1: Environment Variables

Set the API key as an environment variable:

```bash
# Linux/macOS
export DEEPCHAT_API_KEY="your-api-key-here"

# Windows (Command Prompt)
set DEEPCHAT_API_KEY=your-api-key-here

# Windows (PowerShell)
$env:DEEPCHAT_API_KEY="your-api-key-here"
```

#### Option 2: Config File

The application will automatically create a `config.json` file on first run. You can edit this file to add your API key:

```json
{
    "api_key": "your-api-key-here",
    "api_base": "https://api.deepseek.com/v1",
    "model": "deepseek-chat",
    "temperature": 0.7,
    "max_tokens": 1000,
    "upload_max_size": 10485760
}
```

#### Option 3: API Endpoint

Once the application is running, you can use the config API endpoint to update settings:

```bash
curl -X POST "http://localhost:8000/api/config" \
     -H "Content-Type: application/json" \
     -d '{"api_key": "your-api-key-here"}'
```

## Running the Application

### 1. Start the Backend Server

```bash
# Make sure you're in the project directory
cd deepchat

# Activate the environment
conda activate deepchat

# Start the backend server
python backend.py
```

The backend server will run on port 8000 by default.

### 2. Start the Frontend Server

In a new terminal:

```bash
# Navigate to the project directory
cd deepchat

# Start PHP development server
php -S localhost:8001 -t .
```

### 3. Access the Application

Open your browser and go to:

```
http://localhost:8001/Main/Chat_Interface.php
```

## Features

- Real-time chat with AI
- PDF document processing and analysis
- File upload and processing
- WebSocket for real-time updates
- Centralized configuration management

## Project Structure

- `backend.py`: FastAPI server handling chat and file processing
- `Main/Chat_Interface.php`: Main chat interface
- `uploads/`: Directory for uploaded files
- `runs/`: Directory for storing task results
- `cache/`: Cache directory
- `config.json`: Configuration file for API settings

## Advanced Configuration

You can configure additional parameters using environment variables:

```bash
# API base URL
export DEEPCHAT_API_BASE="https://api.deepseek.com/v1"

# Model name
export DEEPCHAT_MODEL="deepseek-chat"

# Temperature setting (0.0-1.0)
export DEEPCHAT_TEMPERATURE=0.7

# Maximum token length
export DEEPCHAT_MAX_TOKENS=1000

# Maximum upload file size in bytes
export DEEPCHAT_UPLOAD_MAX_SIZE=10485760
```

## Troubleshooting

- If you encounter issues with PDF processing, ensure PyPDF2 is installed
- Check your API key if you receive authentication errors
- For WebSocket connection issues, ensure your browser supports WebSockets
- If you see "API Key not set" warnings, follow the configuration steps above

## 功能特性

- **实时对话**: 使用WebSocket实现流式响应，无需刷新页面
- **文件处理**: 支持PDF和文本文件上传与分析
- **进度反馈**: 实时显示任务处理进度和状态
- **配置灵活**: 可配置API连接参数和模型选择
- **API测试**: 内置API连接测试功能

## 系统要求

- Python 3.7+ 
- PHP 7.4+/8.0+ (用于网页界面)
- 网络服务器(Apache, Nginx等)

## 安装步骤

### 安装Python依赖

```bash
pip install fastapi uvicorn aiohttp python-multipart
```

### 配置API密钥

1. 启动应用程序后，点击"API设置"按钮
2. 输入您的API密钥和模型选择
3. 设置合适的API Base URL和WebSocket URL
4. 点击"保存配置"

### 设置环境变量(可选)

也可以通过环境变量设置API配置:

```bash
export API_KEY="your-api-key"
export LANGUAGE_MODEL="deepseek-chat"
export LANGUAGE_MODEL_API_BASE="https://api.deepseek.com/v1"
```

## 启动服务

### Linux/Mac:

```bash
bash start_backend.sh
```

### Windows:

```
双击 start_backend.bat
```

启动后，后端会在0.0.0.0:9000端口运行。

## 使用方法

1. 访问网页界面: http://your-server/Main/Chat_Interface.php
2. 开始对话，上传文件，或配置API

### 聊天功能

- 在输入框中输入消息并按Enter或点击"发送"按钮
- 使用"重新生成"按钮重新生成最后的响应
- 使用"清空对话"按钮开始新对话

### 文件上传

- 点击上传区域或将文件拖放到上传区域
- 支持PDF和文本文件(TXT, MD, CSV)
- 文件大小限制为10MB

### API设置

1. 点击左侧边栏的"API设置"按钮
2. 配置API密钥、API Base URL和WebSocket Base URL
3. 点击"保存配置"

## 目录结构

```
/
├── backend.py        # 后端服务入口
├── start_backend.sh  # 启动脚本(Linux/Mac)
├── start_backend.bat # 启动脚本(Windows)
├── runs/             # 任务结果存储目录
├── uploads/          # 上传文件存储目录 
├── cache/            # 缓存目录
├── Main/             # 前端文件
│   ├── Chat_Interface.php # 聊天界面
│   └── Main Page.php      # 主页
└── Background/       # 背景图像
```



## 许可证

MIT License

