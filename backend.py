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
from starlette.websockets import WebSocketState
import uuid
import aiohttp
import os.path

# 尝试导入 PyPDF2 库
try:
    import PyPDF2

    HAS_PYPDF2 = True
except ImportError:
    HAS_PYPDF2 = False
    print("PyPDF2 库未安装，PDF处理功能将不可用")

# 常量定义
DEBUG = True
RUNS_DIR = "runs"
UPLOADS_DIR = "uploads"
CACHE_DIR = "cache"
CONFIG_FILE = "config.json"
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
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 存储活动连接和任务进度
active_connections: Dict[str, WebSocket] = {}
progress_store: Dict[str, Dict] = {}


class Config:
    def __init__(self):
        # 默认配置
        self.api_key = ""
        self.api_base = "https://api.deepseek.com/v1"
        self.model = "deepseek-chat"
        self.temperature = 0.7
        self.max_tokens = 1000
        self.upload_max_size = 10 * 1024 * 1024  # 10MB

        # 先尝试从环境变量加载
        self._load_from_env()

        # 然后尝试从配置文件加载
        self._load_from_file()

        # 如果API密钥仍然为空，打印警告
        if not self.api_key:
            print("警告: API密钥未设置! 请设置环境变量DEEPCHAT_API_KEY或在config.json中配置api_key")

    def _load_from_env(self):
        """从环境变量加载配置"""
        self.api_key = os.environ.get("DEEPCHAT_API_KEY", self.api_key)
        self.api_base = os.environ.get("DEEPCHAT_API_BASE", self.api_base)
        self.model = os.environ.get("DEEPCHAT_MODEL", self.model)

        # 尝试加载数值型配置
        try:
            if "DEEPCHAT_TEMPERATURE" in os.environ:
                self.temperature = float(os.environ["DEEPCHAT_TEMPERATURE"])
            if "DEEPCHAT_MAX_TOKENS" in os.environ:
                self.max_tokens = int(os.environ["DEEPCHAT_MAX_TOKENS"])
            if "DEEPCHAT_UPLOAD_MAX_SIZE" in os.environ:
                self.upload_max_size = int(os.environ["DEEPCHAT_UPLOAD_MAX_SIZE"])
        except (ValueError, TypeError) as e:
            print(f"环境变量配置错误: {e}")

    def _load_from_file(self):
        """从配置文件加载配置"""
        if not os.path.exists(CONFIG_FILE):
            # 如果文件不存在，创建默认配置文件
            self._save_to_file()
            return

        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                config_data = json.load(f)

            # 更新配置
            if "api_key" in config_data and config_data["api_key"]:
                self.api_key = config_data["api_key"]
            if "api_base" in config_data:
                self.api_base = config_data["api_base"]
            if "model" in config_data:
                self.model = config_data["model"]
            if "temperature" in config_data:
                self.temperature = float(config_data["temperature"])
            if "max_tokens" in config_data:
                self.max_tokens = int(config_data["max_tokens"])
            if "upload_max_size" in config_data:
                self.upload_max_size = int(config_data["upload_max_size"])

        except Exception as e:
            print(f"加载配置文件时出错: {e}")
            traceback.print_exc()

    def _save_to_file(self):
        """保存当前配置到文件"""
        config_data = {
            "api_key": self.api_key,
            "api_base": self.api_base,
            "model": self.model,
            "temperature": self.temperature,
            "max_tokens": self.max_tokens,
            "upload_max_size": self.upload_max_size
        }

        try:
            with open(CONFIG_FILE, "w", encoding="utf-8") as f:
                json.dump(config_data, f, indent=4, ensure_ascii=False)
            print(f"配置已保存到 {CONFIG_FILE}")
        except Exception as e:
            print(f"保存配置文件时出错: {e}")
            traceback.print_exc()

    def update_config(self, new_config):
        """更新配置并保存到文件"""
        # 更新配置
        if "api_key" in new_config and new_config["api_key"]:
            self.api_key = new_config["api_key"]
        if "api_base" in new_config:
            self.api_base = new_config["api_base"]
        if "model" in new_config:
            self.model = new_config["model"]
        if "temperature" in new_config:
            self.temperature = float(new_config["temperature"])
        if "max_tokens" in new_config:
            self.max_tokens = int(new_config["max_tokens"])
        if "upload_max_size" in new_config:
            self.upload_max_size = int(new_config["upload_max_size"])

        # 保存到文件
        self._save_to_file()
        return {"status": "success", "message": "配置已更新"}


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
            print(f"无法报告进度 - 任务 {self.task_id} 没有活动的WebSocket连接")
            print(f"任务 {self.task_id} 将继续在后台处理")
            return

        try:
            self.current_stage += 1
            progress = int((self.current_stage / self.total_stages) * 100)
            stage_name = self.stages[self.current_stage - 1] if self.current_stage <= len(self.stages) else "完成"

            message = {
                "progress": progress,
                "status": f"阶段: {stage_name}",
            }

            # 确保WebSocket连接仍然有效
            if active_connections[self.task_id].client_state == WebSocketState.CONNECTED:
                print(f"发送进度更新: 任务 {self.task_id}, 进度 {progress}%, 阶段 {stage_name}")
                await active_connections[self.task_id].send_json(message)
            else:
                print(f"无法发送进度 - 任务 {self.task_id} 的WebSocket连接已关闭")
                print(f"任务 {self.task_id} 将继续在后台处理")
                # 移除无效的连接
                if self.task_id in active_connections:
                    active_connections.pop(self.task_id)
                    print(f"移除无效的WebSocket连接: {self.task_id}")
        except Exception as e:
            print(f"报告进度时出错: {str(e)}")
            print(f"任务 {self.task_id} 将继续在后台处理")
            traceback.print_exc()

    async def fail_stage(self, error_message: str):
        if self.task_id not in active_connections:
            print(f"无法报告失败 - 任务 {self.task_id} 没有活动的WebSocket连接")
            return

        try:
            error_msg = f"{self.stages[self.current_stage]} 错误: {error_message}"

            # 确保WebSocket连接仍然有效
            if active_connections[self.task_id].client_state == WebSocketState.CONNECTED:
                await active_connections[self.task_id].send_json({
                    "progress": 100,
                    "status": "失败",
                    "error": error_msg
                })
                self.failed = True
                print(f"任务 {self.task_id}: {error_msg}")
            else:
                print(f"无法发送失败通知 - 任务 {self.task_id} 的WebSocket连接已关闭")
        except Exception as e:
            print(f"报告失败时出错: {str(e)}")
            traceback.print_exc()


async def send_progress(websocket: Optional[WebSocket], status: str, progress: int):
    """发送进度更新"""
    if websocket is None:
        print(f"无法发送进度 - websocket为空, status: {status}, progress: {progress}")
        return

    try:
        message = {"progress": progress, "status": status}
        await websocket.send_json(message)
        print(f"进度已发送: {progress}%, {status}")
    except Exception as e:
        print(f"发送进度时出错: {str(e)}")
        traceback.print_exc()


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


async def process_file(filepath: str, filename: str, content_type: str, task_id: str) -> dict:
    """处理上传的文件"""
    if not filepath or not os.path.exists(filepath):
        raise HTTPException(status_code=400, detail="文件不存在")

    # 读取文件内容
    with open(filepath, "rb") as f:
        content = f.read()

    # 如果是PDF文件，处理PDF
    if content_type == "application/pdf":
        try:
            # 尝试导入PDF处理模块
            pdf_module_available = False

            try:
                from Main.runtxt import LightPDFProcessor
                pdf_module_available = True
                print("找到PDF处理模块，将使用LightPDFProcessor")
            except ImportError:
                try:
                    from runtxt import LightPDFProcessor
                    pdf_module_available = True
                    print("找到PDF处理模块，将使用LightPDFProcessor")
                except ImportError:
                    print("PDF处理模块不可用，将PDF作为普通文本处理")

            if pdf_module_available:
                # 使用LightPDFProcessor处理PDF
                print(f"开始处理PDF: {filepath}")
                print(f"文件绝对路径: {os.path.abspath(filepath)}")
                print(f"文件大小: {os.path.getsize(filepath)} 字节")

                try:
                    # 传递task_id来确保每次上传都是唯一的处理
                    processor = LightPDFProcessor(filepath, task_id=task_id)
                    print("LightPDFProcessor 实例化成功")
                except Exception as e:
                    print(f"实例化 LightPDFProcessor 失败: {str(e)}")
                    traceback.print_exc()
                    return {
                        "status": "warning",
                        "type": "pdf_error",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": f"PDF处理初始化失败: {str(e)}"
                    }

                # 检查WebSocket连接是否存在
                if task_id in active_connections:
                    # 发送处理开始的通知
                    try:
                        await active_connections[task_id].send_json({
                            "status": "进行中",
                            "progress": 30,
                            "message": "正在解析PDF文件，请耐心等待..."
                        })
                        print("已发送PDF处理开始通知")
                    except Exception as e:
                        print(f"发送PDF处理开始通知失败: {str(e)}")

                # 异步生成摘要
                try:
                    print("开始异步生成PDF摘要")
                    # 设置较长的超时时间，PDF处理需要更多时间
                    summary = await asyncio.wait_for(
                        processor.generate_summary(),
                        timeout=300  # 设置5分钟超时，比默认的要长得多
                    )
                    print("PDF摘要生成调用已完成")

                    # 检查summary是否为None或非字符串
                    if summary is None:
                        print(f"警告: PDF处理结果为None")
                        summary = "PDF处理失败，无法生成摘要。请确保PDF文件有效并且可读取。"
                    elif not isinstance(summary, str):
                        print(f"警告: PDF处理结果不是字符串类型，而是 {type(summary)}")
                        # 尝试转换为字符串
                        try:
                            summary = str(summary)
                            print(f"已将结果转换为字符串，长度: {len(summary)}")
                        except Exception as e:
                            print(f"转换结果为字符串失败: {str(e)}")
                            summary = "PDF处理结果格式异常，无法显示。"

                    # 确保summary是字符串后再使用len()
                    summary_length = len(summary) if isinstance(summary, str) else 0
                    print(f"PDF摘要生成完成，长度: {summary_length} 字符")

                    # 检查摘要是否为空
                    if summary_length < 10:  # 设置一个合理的最小长度阈值
                        print(f"警告: 生成的摘要为空或过短 ({summary_length} 字符)")
                        summary = "处理结果为空。PDF可能没有包含足够的文本内容，或者格式不受支持。"

                    result = {
                        "status": "success",
                        "type": "pdf",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": summary
                    }

                    # 在返回结果前保存一份结果到任务目录
                    task_dir = os.path.join(RUNS_DIR, task_id)
                    os.makedirs(task_dir, exist_ok=True)
                    with open(os.path.join(task_dir, "pdf_result.json"), "w", encoding="utf-8") as f:
                        json.dump(result, f, ensure_ascii=False)
                    print(f"已保存PDF处理结果到: {os.path.join(task_dir, 'pdf_result.json')}")

                    # 如果WebSocket连接存在，发送处理成功的通知
                    if task_id in active_connections:
                        try:
                            # 添加一个小延迟确保连接稳定
                            await asyncio.sleep(0.5)

                            # 验证连接状态
                            if active_connections[task_id].client_state == WebSocketState.CONNECTED:
                                print(f"通过WebSocket发送PDF处理结果，长度: {summary_length} 字符")

                                # 先发送一个准备消息
                                await active_connections[task_id].send_json({
                                    "status": "准备完成",
                                    "progress": 95,
                                    "message": "PDF处理已完成，正在准备显示结果..."
                                })

                                # 再等待一小段时间
                                await asyncio.sleep(0.5)

                                # 发送最终结果
                                await active_connections[task_id].send_json({
                                    "status": "完成",
                                    "progress": 100,
                                    "type": "pdf",
                                    "file_name": os.path.basename(filepath),
                                    "content": summary
                                })
                                print(f"PDF处理结果已通过WebSocket发送")

                                # 发送成功后记录
                                with open(os.path.join(task_dir, "websocket_sent.txt"), "w") as f:
                                    f.write("发送时间: " + datetime.now().isoformat())
                            else:
                                print(
                                    f"WebSocket连接状态异常: {active_connections[task_id].client_state}，尝试通过API提供结果")
                                # 通过在任务目录保存标记，使API接口能发现已生成的结果
                                with open(os.path.join(task_dir, "result_ready.txt"), "w") as f:
                                    f.write("1")
                        except Exception as ws_error:
                            print(f"通过WebSocket发送结果失败: {str(ws_error)}")
                            traceback.print_exc()
                    else:
                        print(f"找不到任务 {task_id} 的WebSocket连接，结果将通过API接口提供")

                    return result
                except asyncio.TimeoutError as e:
                    # 如果生成超时，返回友好的错误信息
                    print(f"PDF处理超时: {filepath}, 错误: {str(e)}")
                    traceback.print_exc()
                    return {
                        "status": "warning",
                        "type": "pdf_timeout",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": "PDF处理时间较长，系统继续在后台处理。请稍后再试或上传较小的文件。"
                    }
                except Exception as e:
                    print(f"PDF摘要生成过程中出错: {str(e)}")
                    traceback.print_exc()
                    return {
                        "status": "warning",
                        "type": "pdf_error",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": f"PDF处理失败: {str(e)}"
                    }

            else:
                # 如果没有特殊的PDF处理器，使用基本的PyPDF2提取文本
                if HAS_PYPDF2:
                    text = ""
                    with open(filepath, 'rb') as f:
                        # 发送处理状态
                        if task_id in active_connections:
                            await active_connections[task_id].send_json({
                                "status": "进行中",
                                "progress": 30,
                                "message": "正在解析PDF文件，请耐心等待..."
                            })

                        reader = PyPDF2.PdfReader(f)
                        total_pages = len(reader.pages)

                        for page_num in range(total_pages):
                            # 每处理10%的页面更新一次进度
                            if task_id in active_connections and page_num % max(1, total_pages // 10) == 0:
                                progress = min(30 + int(60 * page_num / total_pages), 90)
                                await active_connections[task_id].send_json({
                                    "status": "进行中",
                                    "progress": progress,
                                    "message": f"正在处理PDF第 {page_num + 1}/{total_pages} 页..."
                                })

                            page = reader.pages[page_num]
                            text += f"\n--- 第 {page_num + 1} 页 ---\n"
                            text += page.extract_text()

                    return {
                        "status": "success",
                        "type": "pdf",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": text
                    }
                else:
                    return {
                        "status": "warning",
                        "type": "pdf_unsupported",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": "PDF文件已上传，但系统未安装PDF处理库。请安装PyPDF2以启用PDF处理功能。"
                    }
        except Exception as e:
            print(f"PDF处理失败: {str(e)}")
            traceback.print_exc()
            # 返回错误信息而不是抛出异常
            return {
                "status": "warning",  # 改为warning而不是error，显示更友好的消息
                "type": "pdf_error",
                "task_id": task_id,
                "file_name": os.path.basename(filepath),
                "content": f"PDF处理需要较长时间，系统将在后台继续处理。您可以稍后再查询结果，或者尝试上传内容较少的PDF文件。"
            }

    # 如果是文本文件，直接读取内容
    if content_type == "text/plain":
        try:
            text_content = content.decode("utf-8")
        except UnicodeDecodeError:
            # 尝试其他编码
            try:
                text_content = content.decode("gbk")
            except:
                text_content = content.decode("latin1")

        return {
            "status": "success",
            "type": "text",
            "task_id": task_id,
            "file_name": os.path.basename(filepath),
            "content": text_content
        }

    raise HTTPException(status_code=400, detail="不支持的文件类型")


@app.websocket("/ws/{task_id}")
async def websocket_endpoint(websocket: WebSocket, task_id: str):
    try:
        print(f"接收到WebSocket连接请求: {task_id}")

        # 接受连接
        await websocket.accept()
        print(f"WebSocket连接已接受: {task_id}")

        # 存储连接 - 使用全局字典保存连接
        active_connections[task_id] = websocket
        print(f"已保存WebSocket连接: {task_id}, 当前连接数: {len(active_connections)}")

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
                data = await websocket.receive_json()
                print(f"收到WebSocket消息: {task_id}, 数据: {data}")

                # 处理ping消息
                if data.get("type") == "ping":
                    await websocket.send_json({"type": "pong"})
                    print(f"回复ping: {task_id}")
                    continue

                # 处理停止思考请求
                if data.get("type") == "stop_thinking":
                    print(f"收到停止思考请求: {task_id}")
                    # 设置标志，告知任务处理函数停止处理
                    if task_id in progress_store:
                        progress_store[task_id]["stopped"] = True
                        # 添加标志表明这是用户主动发起的停止请求，而不是WebSocket断开
                        progress_store[task_id]["user_stopped"] = True
                    await websocket.send_json({
                        "type": "stop_thinking_response",
                        "status": "success",
                        "message": "已停止思考"
                    })
                    break

        except json.JSONDecodeError:
            # 处理非JSON消息
            try:
                text_data = await websocket.receive_text()
                print(f"收到非JSON文本消息: {task_id}, 数据: {text_data}")
            except Exception as e:
                print(f"接收文本消息时出错: {str(e)}")

        except WebSocketDisconnect:
            print(f"WebSocket连接断开: {task_id}")
            # 重要修改：不要在连接断开时停止任务处理
            # 只记录连接断开事件，但不标记任务为停止
            print(f"WebSocket连接已断开，但任务 {task_id} 将继续处理")
            # 删除 active_connections 中的连接
            if task_id in active_connections:
                active_connections.pop(task_id)
                print(f"已从active_connections中移除连接 {task_id}")

        except Exception as e:
            print(f"WebSocket连接异常: {task_id}, 错误: {str(e)}")
    except Exception as e:
        print(f"处理WebSocket连接时出错: {task_id}, 错误: {str(e)}")
        traceback.print_exc()
    finally:
        # 注意：不要在这里移除连接，让它保持到任务完成
        print(f"WebSocket连接处理完成: {task_id}")


@app.post("/api/chat")
async def chat(request: Request):
    data = await request.json()
    message = data.get("message")
    if not message:
        raise HTTPException(status_code=400, detail="消息不能为空")

    # 使用前端提供的task_id，如果没有提供才生成新的
    task_id = data.get("task_id") or str(uuid.uuid4())

    progress_store[task_id] = {"type": "chat", "message": message, "stopped": False}

    # 异步处理聊天消息
    asyncio.create_task(process_chat(task_id, message))

    return {"task_id": task_id}


async def process_chat(task_id: str, message: str):
    print(f"开始处理任务 {task_id}, 等待WebSocket连接...")

    # 初始化任务状态
    progress_store[task_id] = {"type": "chat", "message": message, "stopped": False}

    # 等待WebSocket连接
    connection_timeout = 10  # 10秒超时
    for _ in range(connection_timeout * 10):  # 等待更长时间 (10秒)
        if task_id in active_connections:
            print(f"找到任务 {task_id} 的WebSocket连接")
            break
        await asyncio.sleep(0.1)

        # 检查任务是否被用户主动停止
        # 注意：WebSocket断开连接不应该停止任务处理
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"任务 {task_id} 在等待WebSocket连接时已被用户主动停止")
            return

    # 即使没有找到WebSocket连接，仍然继续处理
    if task_id not in active_connections:
        print(f"警告: 任务 {task_id} 没有活动的WebSocket连接，但任务将继续处理")

    print(f"开始处理聊天任务: {task_id}")
    progress = ProgressManager(task_id, STAGES)

    try:
        # 初始化
        await progress.report_progress()
        print(f"任务 {task_id}: 初始化完成")

        # 检查任务是否被用户主动停止（而不是连接断开）
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped",
                                                                                                         False):
            print(f"任务 {task_id} 在初始化后被用户主动停止")
            return

        # 模拟解析
        await asyncio.sleep(0.5)
        await progress.report_progress()
        print(f"任务 {task_id}: 解析完成")

        # 检查任务是否被用户主动停止（而不是连接断开）
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped",
                                                                                                         False):
            print(f"任务 {task_id} 在解析后被用户主动停止")
            return

        # 调用API
        await progress.report_progress()
        print(f"任务 {task_id}: 开始调用API")

        # 检查任务是否被用户主动停止（而不是连接断开）
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped",
                                                                                                         False):
            print(f"任务 {task_id} 在API调用前被用户主动停止")
            return

        # 直接调用API而不是通过函数
        try:
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

            print(f"任务 {task_id}: 发送API请求到 {config.api_base}/chat/completions")
            async with aiohttp.ClientSession() as session:
                async with session.post(
                        f"{config.api_base}/chat/completions",
                        headers=headers,
                        json=data
                ) as response:
                    print(f"任务 {task_id}: 收到API响应, 状态码: {response.status}")

                    # 检查任务是否被用户主动停止（而不是连接断开）
                    if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get(
                            "user_stopped", False):
                        print(f"任务 {task_id} 在收到API响应后被用户主动停止")
                        return

                    if response.status != 200:
                        error_text = await response.text()
                        print(f"任务 {task_id}: API错误: {error_text}")
                        raise Exception(f"API错误: {response.status}, {error_text}")

                    result = await response.json()
                    if "choices" not in result or not result["choices"]:
                        raise Exception("API响应格式错误: 没有choices字段")

                    reply = result["choices"][0]["message"]["content"]
                    print(f"任务 {task_id}: API回复长度: {len(reply)} 字符")

                    # 将结果保存为字典
                    result = {
                        "status": "success",
                        "reply": reply,
                        "task_id": task_id
                    }
        except Exception as api_error:
            print(f"任务 {task_id}: API调用失败: {str(api_error)}")
            traceback.print_exc()
            raise api_error

        print(f"任务 {task_id}: API调用完成")

        # 检查任务是否被用户主动停止（而不是连接断开）
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped",
                                                                                                         False):
            print(f"任务 {task_id} 在API调用完成后被用户主动停止")
            return

        # 生成回复
        await asyncio.sleep(0.5)
        await progress.report_progress()
        print(f"任务 {task_id}: 生成回复完成")

        # 检查任务是否被用户主动停止（而不是连接断开）
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped",
                                                                                                         False):
            print(f"任务 {task_id} 在生成回复后被用户主动停止")
            return

        # 再次检查WebSocket连接是否仍然有效
        if task_id in active_connections:
            try:
                print(f"任务 {task_id}: 发送最终回复")
                final_message = {
                    "progress": 100,
                    "status": "完成",
                    "reply": result["reply"]
                }

                # 确保WebSocket仍然处于开启状态
                if active_connections[task_id].client_state == WebSocketState.CONNECTED:
                    await active_connections[task_id].send_json(final_message)
                    print(f"任务 {task_id}: 最终回复已发送: {len(result['reply'])}字符")
                else:
                    print(f"任务 {task_id}: WebSocket已关闭，无法发送回复")

                # 确保消息发送后保持连接一段时间
                await asyncio.sleep(1)
            except Exception as e:
                print(f"发送最终回复时出错: {task_id}, 错误: {str(e)}")
                traceback.print_exc()
        else:
            print(f"任务 {task_id}: WebSocket连接已丢失，无法发送回复")

        # 保存结果到文件
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        with open(os.path.join(task_dir, "result.json"), "w") as f:
            json.dump(result, f, ensure_ascii=False)

        # 完成
        await progress.report_progress()
        print(f"任务 {task_id}: 全部完成")

        # 任务完成后，将连接和任务从字典中删除
        if task_id in active_connections:
            active_connections.pop(task_id, None)
            print(f"任务 {task_id}: 已清理WebSocket连接")

    except Exception as e:
        print(f"任务 {task_id} 处理失败: {str(e)}")
        await progress.fail_stage(str(e))
        traceback.print_exc()

        # 出错时，确保连接被清理
        if task_id in active_connections:
            active_connections.pop(task_id, None)
            print(f"任务 {task_id}: 错误后已清理WebSocket连接")


@app.post("/api/upload")
async def upload_file(
        file: UploadFile = File(...),
        task_id: str = Form(None),
):
    # 如果客户端没有提供task_id，则生成一个新的
    if not task_id:
        task_id = str(uuid.uuid4())

    progress_store[task_id] = {"type": "upload", "filename": file.filename, "stopped": False}
    print(f"开始处理上传文件: {file.filename}, 任务ID: {task_id}, 内容类型: {file.content_type}")

    # 立即读取文件内容，避免文件被关闭
    content = await file.read()
    file_size = len(content)
    print(f"文件大小: {file_size} 字节")

    if file_size > config.upload_max_size:
        print(f"文件大小超过限制: {file_size} > {config.upload_max_size}")
        raise HTTPException(
            status_code=400,
            detail=f"文件大小超过限制（最大{config.upload_max_size / 1024 / 1024}MB）"
        )

    # 生成文件名
    file_hash = hashlib.md5(content).hexdigest()
    ext = os.path.splitext(file.filename)[1]
    filename = f"{task_id}_{file_hash}{ext}"
    filepath = os.path.join(UPLOADS_DIR, filename)
    print(f"保存文件到: {filepath}")

    # 确保上传目录存在
    os.makedirs(UPLOADS_DIR, exist_ok=True)

    # 保存文件
    with open(filepath, "wb") as f:
        f.write(content)
    print(f"文件已保存，大小: {os.path.getsize(filepath)} 字节")

    # 异步处理文件，传递文件路径而不是文件对象
    asyncio.create_task(process_upload(task_id, file.filename, filepath, file.content_type))

    return {"task_id": task_id}


async def process_upload(task_id: str, filename: str, filepath: str, content_type: str):
    # 初始化任务状态
    progress_store[task_id] = {"type": "upload", "filename": filename, "stopped": False}
    print(f"处理上传文件: {filename}, 任务ID: {task_id}, 内容类型: {content_type}")

    # 等待WebSocket连接
    for _ in range(50):  # 等待最多1秒
        if task_id in active_connections:
            print(f"已找到WebSocket连接: {task_id}")
            break
        await asyncio.sleep(0.02)

        # 检查任务是否被停止
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"任务 {task_id} 在等待WebSocket连接时已被停止")
            return

    progress = ProgressManager(task_id, STAGES)

    try:
        # 初始化
        await progress.report_progress()
        print(f"任务 {task_id}: 初始化完成")

        # 检查任务是否被停止
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"任务 {task_id} 在初始化后被停止")
            return

        # 解析文件
        await progress.report_progress()
        print(f"任务 {task_id}: 开始解析文件")

        # 对于PDF文件，发送特殊的提示消息
        if content_type == "application/pdf" and task_id in active_connections:
            await active_connections[task_id].send_json({
                "progress": 40,
                "status": "处理PDF文件中...",
                "message": "PDF处理可能需要较长时间，请耐心等待。大型PDF文件可能需要几分钟时间。"
            })
            print(f"任务 {task_id}: 已发送PDF处理提示")

        print(f"任务 {task_id}: 调用process_file处理文件")
        result = await process_file(filepath, filename, content_type, task_id)
        print(f"任务 {task_id}: 文件处理完成，结果类型: {result.get('type', 'unknown')}")

        # 检查任务是否被停止
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"任务 {task_id} 在解析文件后被停止")
            return

        # 调用API
        await progress.report_progress()
        print(f"任务 {task_id}: API阶段完成")

        # 检查任务是否被停止
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"任务 {task_id} 在调用API后被停止")
            return

        # 生成回复
        await asyncio.sleep(0.5)
        await progress.report_progress()
        print(f"任务 {task_id}: 生成回复阶段完成")

        # 检查任务是否被停止
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"任务 {task_id} 在生成回复后被停止")
            return

        # 完成
        if task_id in active_connections:
            # 根据结果类型准备响应内容
            response_content = ""
            status_message = "完成"

            # 特殊处理PDF相关的结果
            if result["type"] == "pdf_timeout":
                status_message = "PDF处理时间较长"
                response_content = result.get("content", "")
                print(f"任务 {task_id}: PDF处理超时")
            elif result["type"] == "pdf_error":
                status_message = "PDF处理通知"
                response_content = result.get("content", "")
                print(f"任务 {task_id}: PDF处理错误: {response_content}")
            elif result["type"] == "pdf_unsupported":
                status_message = "PDF功能受限"
                response_content = result.get("content", "")
                print(f"任务 {task_id}: PDF功能受限: {response_content}")
            else:
                # 对于其他类型，使用summary或content字段
                response_content = result.get("summary", result.get("content", ""))
                print(f"任务 {task_id}: 处理成功，响应内容长度: {len(response_content)} 字符")

            try:
                await active_connections[task_id].send_json({
                    "progress": 100,
                    "status": status_message,
                    "type": result["type"],
                    "file_name": result["file_name"],
                    "content": response_content
                })
                print(f"任务 {task_id}: 已发送最终响应")
            except Exception as e:
                print(f"任务 {task_id}: 发送响应失败: {str(e)}")
                traceback.print_exc()
        else:
            print(f"任务 {task_id}: 无法发送响应，WebSocket连接已关闭")

        # 保存结果到文件
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        result_file = os.path.join(task_dir, "result.json")
        with open(result_file, "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False)
        print(f"任务 {task_id}: 结果已保存到 {result_file}")

        # 完成
        await progress.report_progress()
        print(f"任务 {task_id}: 全部完成")

    except Exception as e:
        print(f"任务 {task_id} 处理失败: {str(e)}")
        await progress.fail_stage(str(e))
        traceback.print_exc()


@app.get("/api/result/{task_id}")
async def get_result(task_id: str):
    """获取任务结果"""
    print(f"请求获取任务 {task_id} 的结果")

    # 首先检查PDF特定的结果文件
    pdf_result_file = os.path.join(RUNS_DIR, task_id, "pdf_result.json")
    if os.path.exists(pdf_result_file):
        try:
            print(f"找到PDF结果文件: {pdf_result_file}")
            with open(pdf_result_file, "r", encoding="utf-8") as f:
                result = json.load(f)
                print(
                    f"成功加载PDF结果文件，类型: {result.get('type', 'unknown')}, 内容长度: {len(result.get('content', ''))}")
                return result
        except Exception as e:
            print(f"读取PDF结果文件时出错: {str(e)}")
            traceback.print_exc()

    # 检查常规结果文件
    result_file = os.path.join(RUNS_DIR, task_id, "result.json")
    if os.path.exists(result_file):
        try:
            print(f"找到任务 {task_id} 的结果文件")
            with open(result_file, "r", encoding="utf-8") as f:
                result = json.load(f)
                print(f"成功加载结果文件，类型: {result.get('type', 'unknown')}")
                return result
        except Exception as e:
            print(f"读取任务 {task_id} 的结果文件时出错: {str(e)}")
            traceback.print_exc()
            raise HTTPException(status_code=500, detail=f"读取结果文件出错: {str(e)}")

    # 检查是否有标记文件，表明结果已生成但WebSocket发送失败
    ready_file = os.path.join(RUNS_DIR, task_id, "result_ready.txt")
    if os.path.exists(ready_file):
        # 尝试查找任何可能存在的结果文件
        print(f"任务 {task_id} 有处理完成标记，但结果文件不存在")
        # 查找目录中的所有json文件
        task_dir = os.path.join(RUNS_DIR, task_id)
        json_files = [f for f in os.listdir(task_dir) if f.endswith('.json')]

        if json_files:
            # 尝试加载第一个找到的JSON文件
            try:
                with open(os.path.join(task_dir, json_files[0]), "r", encoding="utf-8") as f:
                    print(f"尝试加载备选结果文件: {json_files[0]}")
                    result = json.load(f)
                    return result
            except Exception as e:
                print(f"读取备选结果文件时出错: {str(e)}")
                traceback.print_exc()

    # 如果是正在进行的任务，尝试从内存中获取
    if task_id in progress_store:
        task_info = progress_store[task_id]
        task_type = task_info.get("type")

        # 任务正在进行中，但没有结果
        print(f"任务 {task_id} 仍在进行中，类型: {task_type}")

        # 尝试调用API直接获取结果
        if task_type == "chat":
            try:
                message = task_info.get("message", "")
                if message:
                    print(f"尝试为任务 {task_id} 重新调用API")
                    result = await call_llm_api(message, task_id)

                    # 保存结果到文件
                    task_dir = os.path.join(RUNS_DIR, task_id)
                    os.makedirs(task_dir, exist_ok=True)
                    with open(result_file, "w", encoding="utf-8") as f:
                        json.dump(result, f, ensure_ascii=False)

                    print(f"为任务 {task_id} 生成了新的API结果")
                    return result
            except Exception as e:
                print(f"为任务 {task_id} 重新调用API时出错: {str(e)}")
                traceback.print_exc()

        # 返回进行中状态
        return {
            "status": "processing",
            "task_id": task_id,
            "task_type": task_type,
            "message": "任务正在处理中"
        }

    # 任务不存在
    print(f"未找到任务 {task_id} 的结果或进度信息")
    raise HTTPException(status_code=404, detail="任务结果不存在")


@app.get("/api/test")
async def test_api():
    try:
        print("API测试端点被调用")
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
        raise


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


@app.get("/api/ping")
async def ping():
    """简单的ping测试，用于检查服务器是否运行"""
    return {"status": "ok", "timestamp": datetime.now().isoformat()}


@app.post("/api/config")
async def update_config(request: Request):
    """更新API配置"""
    try:
        data = await request.json()
        result = config.update_config(data)
        return result
    except Exception as e:
        print(f"更新配置时出错: {str(e)}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"更新配置失败: {str(e)}")


@app.get("/api/config")
async def get_config():
    """获取当前API配置"""
    # 返回配置但隐藏API密钥的完整值
    config_data = {
        "api_base": config.api_base,
        "model": config.model,
        "temperature": config.temperature,
        "max_tokens": config.max_tokens,
        "upload_max_size": config.upload_max_size
    }

    # 只显示API密钥的存在状态，不返回具体值
    if config.api_key:
        # 如果有API密钥，只返回前4位和后4位，中间用*替代
        if len(config.api_key) > 8:
            masked_key = config.api_key[:4] + "*" * (len(config.api_key) - 8) + config.api_key[-4:]
        else:
            masked_key = "****"
        config_data["api_key"] = masked_key
    else:
        config_data["api_key"] = ""

    return config_data


if __name__ == "__main__":
    import uvicorn

    port = 8000
    print(f"端口为{port}")
    asyncio.run(test_connection())
    print("服务已启动......")
    uvicorn.run(app, host="0.0.0.0", port=port)
