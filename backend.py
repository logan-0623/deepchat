import asyncio
import hashlib
import json
import os
import sys
import traceback
from datetime import datetime
from typing import Optional, Dict, List, Any
from fastapi import FastAPI, File, Form, HTTPException, Request, UploadFile, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse
import uuid
import aiohttp
from urllib.parse import urljoin

# 常量定义
DEBUG = True
RUNS_DIR = "runs"
UPLOADS_DIR = "uploads"
CACHE_DIR = "cache"
STAGES = [
    "初始化",
    "解析文件",
    "调用API",
    "生成回复",
    "完成"
]

# 创建必要的目录
for dir_path in [RUNS_DIR, UPLOADS_DIR, CACHE_DIR]:
    os.makedirs(dir_path, exist_ok=True)

# 服务器配置
app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://127.0.0.1:8000"],  # 明确添加前端URL
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 存储活动连接和任务进度
active_connections: Dict[str, WebSocket] = {}
progress_store: Dict[str, Dict] = {}

class AsyncLLM:
    """异步LLM API调用类，参考pptagent实现"""
    
    def __init__(self, model: str, api_base: Optional[str] = None, api_key: Optional[str] = None):
        self.model = model
        self.api_base = api_base or os.environ.get("API_BASE", "https://api.deepseek.com/v1")
        self.api_key = api_key or os.environ.get("API_KEY", "sk-c5f415578acb43a99871b38d273cafb7")
        print(f"初始化 AsyncLLM: model={model}, api_base={self.api_base}")
    
    async def generate(self, messages: List[Dict[str, str]], temperature: float = 0.7, max_tokens: int = 1000) -> Dict[str, Any]:
        """生成回复"""
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {self.api_key}"
        }
        
        data = {
            "model": self.model,
            "messages": messages,
            "temperature": temperature,
            "max_tokens": max_tokens
        }
        
        endpoint = urljoin(self.api_base, "/chat/completions")
        
        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    endpoint,
                    headers=headers,
                    json=data,
                    ssl=False  # 禁用SSL验证以解决某些环境的证书问题
                ) as response:
                    if response.status != 200:
                        error_text = await response.text()
                        raise HTTPException(
                            status_code=response.status,
                            detail=f"API返回错误状态码: {response.status}, 错误: {error_text}"
                        )
                    
                    result = await response.json()
                    return result
        except Exception as e:
            print(f"API调用失败: {str(e)}")
            traceback.print_exc()
            raise HTTPException(status_code=500, detail=f"API调用失败: {str(e)}")

    async def test_connection(self) -> bool:
        """测试API连接"""
        try:
            test_message = "This is a test message. Please respond with 'API test successful'."
            messages = [{"role": "user", "content": test_message}]
            result = await self.generate(messages)
            return True
        except Exception as e:
            print(f"连接测试失败: {str(e)}")
            return False

class ProgressManager:
    """进度管理类，用于管理和报告任务进度"""
    
    def __init__(self, task_id: str, stages: List[str], debug: bool = True):
        self.task_id = task_id
        self.stages = stages
        self.debug = debug
        self.failed = False
        self.current_stage = 0
        self.total_stages = len(stages)

    async def report_progress(self):
        if self.task_id not in active_connections:
            return
        
        self.current_stage += 1
        progress = int((self.current_stage / self.total_stages) * 100)
        await send_progress(
            active_connections[self.task_id],
            f"阶段: {self.stages[self.current_stage - 1]}",
            progress,
        )

    async def fail_stage(self, error_message: str):
        if self.task_id not in active_connections:
            return
            
        await send_progress(
            active_connections[self.task_id],
            f"{self.stages[self.current_stage]} 错误: {error_message}",
            100,
            error=error_message
        )
        self.failed = True
        active_connections.pop(self.task_id, None)
        print(f"{self.task_id}: {self.stages[self.current_stage]} 错误: {error_message}")

async def send_progress(websocket: Optional[WebSocket], status: str, progress: int, error: str = None):
    """发送进度更新到客户端"""
    if websocket is None:
        print(f"websocket为空, status: {status}, progress: {progress}")
        return
    
    message = {
        "progress": progress,
        "status": status
    }
    
    if error:
        message["error"] = error
    
    await websocket.send_json(message)

# 初始化语言模型
language_model = AsyncLLM(
    model=os.environ.get("LANGUAGE_MODEL", "deepseek-chat"),
    api_base=os.environ.get("LANGUAGE_MODEL_API_BASE", None),
    api_key=os.environ.get("API_KEY", None)
)

async def process_file(file: UploadFile, task_id: str) -> dict:
    """处理上传的文件"""
    if not file:
        raise HTTPException(status_code=400, detail="没有文件上传")

    # 读取文件内容
    content = await file.read()
    file_size = len(content)
    
    # 文件大小限制 (10MB)
    max_size = 10 * 1024 * 1024
    if file_size > max_size:
        raise HTTPException(
            status_code=400,
            detail=f"文件大小超过限制（最大{max_size/1024/1024}MB）"
        )

    # 生成文件名
    file_hash = hashlib.md5(content).hexdigest()
    ext = os.path.splitext(file.filename)[1]
    filename = f"{task_id}_{file_hash}{ext}"
    filepath = os.path.join(UPLOADS_DIR, filename)

    # 保存文件
    with open(filepath, "wb") as f:
        f.write(content)

    # 处理不同类型的文件
    content_type = file.content_type or ""
    if "pdf" in content_type.lower():
        try:
            # 尝试引入并使用PDF处理类
            try:
                from Chat import LightPDFProcessor
                processor = LightPDFProcessor(filepath)
                summary = processor.generateSummary()
            except ImportError:
                # 如果无法导入LightPDFProcessor，使用简单的处理逻辑
                summary = f"已成功上传PDF文件 {file.filename}。请告诉我您想了解什么内容？"
            
            return {
                "status": "success",
                "type": "pdf",
                "task_id": task_id,
                "file_name": filename,
                "summary": summary
            }
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"PDF处理失败: {str(e)}")
    
    # 处理文本文件
    if "text" in content_type.lower() or ext.lower() in ['.txt', '.md', '.csv']:
        try:
            file_content = content.decode('utf-8')
        except UnicodeDecodeError:
            try:
                file_content = content.decode('gbk')  # 尝试使用GBK编码
            except:
                file_content = "文件内容无法解码，可能包含二进制数据。"
        
        return {
            "status": "success",
            "type": "text",
            "task_id": task_id,
            "file_name": filename,
            "content": file_content
        }

    # 如果是其他类型的文件
    return {
        "status": "success",
        "type": "other",
        "task_id": task_id,
        "file_name": filename,
        "content": f"已成功上传文件 {file.filename}。文件类型: {content_type}"
    }

@app.websocket("/ws/{task_id}")
async def websocket_endpoint(websocket: WebSocket, task_id: str):
    """WebSocket端点用于实时通信"""
    try:
        print(f"接收到WebSocket连接请求: {task_id}")
        
        # 接受连接
        await websocket.accept()
        print(f"WebSocket连接已接受: {task_id}")
        
        # 存储连接
        active_connections[task_id] = websocket
        
        # 发送初始状态
        try:
            await websocket.send_json({
                "type": "connection_status",
                "status": "connected",
                "task_id": task_id,
                "message": "WebSocket连接成功"
            })
            print(f"已发送连接成功消息: {task_id}")
        except Exception as e:
            print(f"发送初始状态失败: {task_id}, 错误: {str(e)}")
        
        # 如果任务已存在，发送当前状态
        if task_id in progress_store:
            task_type = progress_store[task_id].get("type", "unknown")
            try:
                await websocket.send_json({
                    "type": "task_info",
                    "task_id": task_id,
                    "task_type": task_type,
                    "message": f"任务 {task_id} 状态: 处理中"
                })
                print(f"已发送任务信息: {task_id}, 类型: {task_type}")
            except Exception as e:
                print(f"发送任务信息失败: {task_id}, 错误: {str(e)}")
        
        # 保持连接直到客户端断开
        try:
            while True:
                data = await websocket.receive_text()
                print(f"收到WebSocket消息: {task_id}, 数据: {data}")
                # 这里可以处理客户端发送的消息
        except WebSocketDisconnect:
            print(f"WebSocket连接断开: {task_id}")
        except Exception as e:
            print(f"WebSocket连接异常: {task_id}, 错误: {str(e)}")
    except Exception as e:
        print(f"处理WebSocket连接时出错: {task_id}, 错误: {str(e)}")
        traceback.print_exc()
    finally:
        # 清理连接
        if task_id in active_connections:
            active_connections.pop(task_id, None)
            print(f"已清理WebSocket连接: {task_id}")

@app.post("/api/chat")
async def chat(request: Request):
    """处理聊天请求"""
    data = await request.json()
    message = data.get("message")
    if not message:
        raise HTTPException(status_code=400, detail="消息不能为空")

    task_id = str(uuid.uuid4())
    progress_store[task_id] = {"type": "chat", "message": message}
    
    # 异步处理聊天消息
    asyncio.create_task(process_chat(task_id, message))
    
    return {"task_id": task_id}

async def process_chat(task_id: str, message: str):
    """处理聊天消息并通过WebSocket发送结果"""
    # 等待WebSocket连接
    for _ in range(50):  # 等待最多1秒
        if task_id in active_connections:
            break
        await asyncio.sleep(0.02)
    
    progress = ProgressManager(task_id, STAGES)
    
    try:
        # 初始化
        await progress.report_progress()
        
        # 解析消息
        await asyncio.sleep(0.3)
        await progress.report_progress()
        
        # 调用API
        await progress.report_progress()
        messages = [{"role": "user", "content": message}]
        result = await language_model.generate(messages)
        
        reply = result.get("choices", [{}])[0].get("message", {}).get("content", "")
        if not reply:
            raise HTTPException(status_code=500, detail="API响应格式错误")
        
        # 生成回复
        await asyncio.sleep(0.3)
        await progress.report_progress()
        
        # 完成
        if task_id in active_connections:
            await active_connections[task_id].send_json({
                "progress": 100,
                "status": "完成",
                "reply": reply
            })
            
        # 保存结果到文件
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        with open(os.path.join(task_dir, "result.json"), "w", encoding="utf-8") as f:
            json.dump({"status": "success", "reply": reply}, f, ensure_ascii=False)
        
        # 完成
        await progress.report_progress()
        
    except Exception as e:
        await progress.fail_stage(str(e))
        traceback.print_exc()

@app.post("/api/upload")
async def upload_file(
    file: UploadFile = File(...),
):
    """处理文件上传请求"""
    task_id = str(uuid.uuid4())
    progress_store[task_id] = {"type": "upload", "filename": file.filename}
    
    # 异步处理文件
    asyncio.create_task(process_upload(task_id, file))
    
    return {"task_id": task_id}

async def process_upload(task_id: str, file: UploadFile):
    """处理文件上传并通过WebSocket发送结果"""
    # 等待WebSocket连接
    for _ in range(50):  # 等待最多1秒
        if task_id in active_connections:
            break
        await asyncio.sleep(0.02)
    
    progress = ProgressManager(task_id, STAGES)
    
    try:
        # 初始化
        await progress.report_progress()
        
        # 解析文件
        await progress.report_progress()
        result = await process_file(file, task_id)
        
        # 调用API处理
        await progress.report_progress()
        
        # 生成回复
        await asyncio.sleep(0.3)
        await progress.report_progress()
        
        # 完成
        if task_id in active_connections:
            await active_connections[task_id].send_json({
                "progress": 100,
                "status": "完成",
                "type": result["type"],
                "file_name": result["file_name"],
                "content": result.get("summary", result.get("content", ""))
            })
        
        # 保存结果到文件
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        with open(os.path.join(task_dir, "result.json"), "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False)
        
        # 完成
        await progress.report_progress()
        
    except Exception as e:
        await progress.fail_stage(str(e))
        traceback.print_exc()

@app.get("/api/result/{task_id}")
async def get_result(task_id: str):
    """获取任务结果"""
    result_file = os.path.join(RUNS_DIR, task_id, "result.json")
    if os.path.exists(result_file):
        with open(result_file, "r", encoding="utf-8") as f:
            return json.load(f)
    raise HTTPException(status_code=404, detail="任务结果不存在")

@app.get("/api/test")
async def test_api():
    """测试API连接"""
    try:
        # 先进行一个简单的自检
        status_info = {
            "server_status": "运行中",
            "model": language_model.model,
            "api_base": language_model.api_base,
            "upload_dir_exists": os.path.exists(UPLOADS_DIR),
            "cache_dir_exists": os.path.exists(CACHE_DIR),
            "runs_dir_exists": os.path.exists(RUNS_DIR),
        }
        
        print("开始API测试...")
        # 尝试调用LLM API
        try:
            start_time = datetime.now()
            messages = [{"role": "user", "content": "This is a test message. Please respond with 'API test successful'"}]
            result = await language_model.generate(messages)
            end_time = datetime.now()
            response_time = (end_time - start_time).total_seconds() * 1000  # 毫秒
            
            reply = result.get("choices", [{}])[0].get("message", {}).get("content", "")
            
            status_info["api_call_status"] = "成功"
            status_info["api_response_time_ms"] = response_time
            status_info["api_response"] = reply
            
            return {
                "status": "success",
                "message": "API连接测试成功",
                "server_info": status_info,
                "reply": reply
            }
            
        except Exception as api_error:
            print(f"API调用失败: {str(api_error)}")
            traceback.print_exc()
            
            # 尝试验证API密钥是否有效
            if isinstance(api_error, HTTPException) and api_error.status_code == 401:
                status_info["api_call_status"] = "密钥无效"
            else:
                status_info["api_call_status"] = "失败"
                
            status_info["api_error"] = str(api_error)
            
            return {
                "status": "error",
                "message": f"API调用失败: {str(api_error)}",
                "server_info": status_info,
                "detail": str(api_error)
            }
            
    except Exception as e:
        print(f"测试API过程中出现错误: {str(e)}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"测试API过程中出现错误: {str(e)}")

@app.get("/")
async def hello():
    """根路径请求处理"""
    return {"message": "Deepchat API服务正在运行", "status": "success"}

async def test_connection():
    """启动时测试API连接"""
    try:
        connection_successful = await language_model.test_connection()
        if connection_successful:
            print("LLM API连接成功")
        else:
            print("LLM API连接失败")
        return connection_successful
    except Exception as e:
        print(f"LLM API连接失败: {e}")
        return False

if __name__ == "__main__":
    import uvicorn
    asyncio.run(test_connection())
    uvicorn.run(app, host="0.0.0.0", port=9000) 