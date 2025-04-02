import asyncio
import hashlib
import json
import os
import sys
import traceback
from datetime import datetime
from typing import Optional, Dict, List
from fastapi import FastAPI, File, Form, HTTPException, Request, UploadFile, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
import uuid
import aiohttp

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
    allow_origins=["*", "http://localhost:63342"],  # 添加PHPStorm默认服务器
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 存储活动连接和任务进度
active_connections: Dict[str, WebSocket] = {}
progress_store: Dict[str, Dict] = {}

class Config:
    def __init__(self):
        self.api_key = "sk-c5f415578acb43a99871b38d273cafb7"
        self.api_base = "https://api.deepseek.com/v1"
        self.model = "deepseek-chat"
        self.temperature = 0.7
        self.max_tokens = 1000
        self.upload_max_size = 10 * 1024 * 1024  # 10MB

config = Config()

class ProgressManager:
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
        )
        self.failed = True
        active_connections.pop(self.task_id, None)
        print(f"{self.task_id}: {self.stages[self.current_stage]} 错误: {error_message}")

async def send_progress(websocket: Optional[WebSocket], status: str, progress: int):
    if websocket is None:
        print(f"websocket为空, status: {status}, progress: {progress}")
        return
    await websocket.send_json({"progress": progress, "status": status})

async def call_llm_api(message: str, task_id: str = None) -> dict:
    """调用 LLM API"""
    async with aiohttp.ClientSession() as session:
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {config.api_key}"
        }
        data = {
            "model": config.model,
            "messages": [{"role": "user", "content": message}],
            "temperature": config.temperature,
            "max_tokens": config.max_tokens
        }
        
        try:
            async with session.post(
                f"{config.api_base}/chat/completions",
                headers=headers,
                json=data
            ) as response:
                if response.status != 200:
                    raise HTTPException(
                        status_code=response.status,
                        detail=f"API返回错误状态码: {response.status}"
                    )
                
                result = await response.json()
                if "choices" not in result or not result["choices"]:
                    raise HTTPException(status_code=500, detail="API响应格式错误")
                
                return {
                    "status": "success",
                    "reply": result["choices"][0]["message"]["content"],
                    "task_id": task_id
                }
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))

async def process_file(file: UploadFile, task_id: str) -> dict:
    """处理上传的文件"""
    if not file:
        raise HTTPException(status_code=400, detail="没有文件上传")

    # 读取文件内容
    content = await file.read()
    file_size = len(content)
    
    if file_size > config.upload_max_size:
        raise HTTPException(
            status_code=400,
            detail=f"文件大小超过限制（最大{config.upload_max_size/1024/1024}MB）"
        )

    # 生成文件名
    file_hash = hashlib.md5(content).hexdigest()
    ext = os.path.splitext(file.filename)[1]
    filename = f"{task_id}_{file_hash}{ext}"
    filepath = os.path.join(UPLOADS_DIR, filename)

    # 保存文件
    with open(filepath, "wb") as f:
        f.write(content)

    # 如果是PDF文件，处理PDF
    if file.content_type == "application/pdf":
        try:
            from Chat import LightPDFProcessor
            processor = LightPDFProcessor(filepath)
            summary = processor.generateSummary()
            return {
                "status": "success",
                "type": "pdf",
                "task_id": task_id,
                "file_name": filename,
                "summary": summary
            }
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"PDF处理失败: {str(e)}")

    # 如果是文本文件，直接读取内容
    if file.content_type == "text/plain":
        return {
            "status": "success",
            "type": "text",
            "task_id": task_id,
            "file_name": filename,
            "content": content.decode()
        }

    raise HTTPException(status_code=400, detail="不支持的文件类型")

@app.websocket("/ws/{task_id}")
async def websocket_endpoint(websocket: WebSocket, task_id: str):
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
    # 等待WebSocket连接
    for _ in range(50):  # 等待最多1秒
        if task_id in active_connections:
            break
        await asyncio.sleep(0.02)
    
    progress = ProgressManager(task_id, STAGES)
    
    try:
        # 初始化
        await progress.report_progress()
        
        # 模拟解析
        await asyncio.sleep(0.5)
        await progress.report_progress()
        
        # 调用API
        await progress.report_progress()
        result = await call_llm_api(message, task_id)
        
        # 生成回复
        await asyncio.sleep(0.5)
        await progress.report_progress()
        
        # 完成
        if task_id in active_connections:
            await active_connections[task_id].send_json({
                "progress": 100,
                "status": "完成",
                "reply": result["reply"]
            })
            
        # 保存结果到文件
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        with open(os.path.join(task_dir, "result.json"), "w") as f:
            json.dump(result, f, ensure_ascii=False)
        
        # 完成
        await progress.report_progress()
        
    except Exception as e:
        await progress.fail_stage(str(e))
        traceback.print_exc()

@app.post("/api/upload")
async def upload_file(
    file: UploadFile = File(...),
):
    task_id = str(uuid.uuid4())
    progress_store[task_id] = {"type": "upload", "filename": file.filename}
    
    # 异步处理文件
    asyncio.create_task(process_upload(task_id, file))
    
    return {"task_id": task_id}

async def process_upload(task_id: str, file: UploadFile):
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
        
        # 调用API
        await progress.report_progress()
        
        # 生成回复
        await asyncio.sleep(0.5)
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
        with open(os.path.join(task_dir, "result.json"), "w") as f:
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
        with open(result_file, "r") as f:
            return json.load(f)
    raise HTTPException(status_code=404, detail="任务结果不存在")

@app.get("/api/test")
async def test_api():
    """测试API连接"""
    try:
        # 先进行一个简单的自检
        status_info = {
            "server_status": "运行中",
            "api_base": config.api_base,
            "model": config.model,
            "upload_dir_exists": os.path.exists(UPLOADS_DIR),
            "cache_dir_exists": os.path.exists(CACHE_DIR),
            "runs_dir_exists": os.path.exists(RUNS_DIR),
        }
        
        print("开始API测试...")
        # 尝试调用LLM API
        try:
            start_time = datetime.now()
            result = await call_llm_api("This is a test message. Please respond with 'API test successful'")
            end_time = datetime.now()
            response_time = (end_time - start_time).total_seconds() * 1000  # 毫秒
            
            status_info["api_call_status"] = "成功"
            status_info["api_response_time_ms"] = response_time
            status_info["api_response"] = result["reply"]
            
            return {
                "status": "success",
                "message": "API连接测试成功",
                "server_info": status_info,
                "response": result
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
    return {"message": "Deepchat API服务正在运行"}

async def test_connection():
    try:
        await call_llm_api("Test connection")
        print("LLM API连接成功")
        return True
    except Exception as e:
        print(f"LLM API连接失败: {e}")
        return False

if __name__ == "__main__":
    import uvicorn
    asyncio.run(test_connection())
    uvicorn.run(app, host="0.0.0.0", port=8000) 
    print("服务已启动......")