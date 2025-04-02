# Deepchat AI 系统

基于FastAPI和WebSocket构建的实时聊天和文档处理系统。

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

## 故障排除

### 无法连接到后端服务

- 确保后端服务正在运行(`python backend.py`)
- 检查防火墙设置，确保9000端口开放
- 检查浏览器控制台是否有错误消息

### WebSocket连接错误

- 确保WebSocket URL配置正确(ws://127.0.0.1:9000)
- 检查是否有代理或VPN阻止WebSocket连接
- 尝试使用IP地址而不是localhost

### API调用失败

- 检查API密钥是否正确
- 确保API基础URL配置正确
- 查看后端控制台日志以获取详细错误信息

## 许可证

MIT License

