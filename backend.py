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

# Try to import PyPDF2 library
try:
    import PyPDF2

    HAS_PYPDF2 = True
except ImportError:
    HAS_PYPDF2 = False
    print("PyPDF2 library not installed, PDF processing will be unavailable")

# Constant definitions
DEBUG = True
RUNS_DIR = "runs"
UPLOADS_DIR = "uploads"
CACHE_DIR = "cache"
CONFIG_FILE = "config.json"
STAGES = [
    "Initialization",
    "File parsing",
    "API call",
    "Generating reply",
    "Complete"
]

# Create necessary directories
for dir_path in [RUNS_DIR, UPLOADS_DIR, CACHE_DIR]:
    os.makedirs(dir_path, exist_ok=True)

# Server configuration
app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Store active connections and task progress
active_connections: Dict[str, WebSocket] = {}
progress_store: Dict[str, Dict] = {}


class Config:
    def __init__(self):
        # Default configuration
        self.api_key = ""
        self.api_base = "https://api.deepseek.com/v1"
        self.model = "deepseek-chat"
        self.temperature = 0.7
        self.max_tokens = 1000
        self.upload_max_size = 10 * 1024 * 1024  # 10MB

        # First try to load from environment variables
        self._load_from_env()

        # Then try to load from config file
        self._load_from_file()

        # If API key is still empty, print warning
        if not self.api_key:
            print("Warning: API key not set! Please set DEEPCHAT_API_KEY environment variable or configure api_key in config.json")

    def _load_from_env(self):
        """Load configuration from environment variables"""
        self.api_key = os.environ.get("DEEPCHAT_API_KEY", self.api_key)
        self.api_base = os.environ.get("DEEPCHAT_API_BASE", self.api_base)
        self.model = os.environ.get("DEEPCHAT_MODEL", self.model)

        # Try to load numeric configurations
        try:
            if "DEEPCHAT_TEMPERATURE" in os.environ:
                self.temperature = float(os.environ["DEEPCHAT_TEMPERATURE"])
            if "DEEPCHAT_MAX_TOKENS" in os.environ:
                self.max_tokens = int(os.environ["DEEPCHAT_MAX_TOKENS"])
            if "DEEPCHAT_UPLOAD_MAX_SIZE" in os.environ:
                self.upload_max_size = int(os.environ["DEEPCHAT_UPLOAD_MAX_SIZE"])
        except (ValueError, TypeError) as e:
            print(f"Environment variable configuration error: {e}")

    def _load_from_file(self):
        """Load configuration from config file"""
        if not os.path.exists(CONFIG_FILE):
            # If file doesn't exist, create default config file
            self._save_to_file()
            return

        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                config_data = json.load(f)

            # Update configuration
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
            print(f"Error loading config file: {e}")
            traceback.print_exc()

    def _save_to_file(self):
        """Save current configuration to file"""
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
            print(f"Configuration saved to {CONFIG_FILE}")
        except Exception as e:
            print(f"Error saving config file: {e}")
            traceback.print_exc()

    def update_config(self, new_config):
        """Update configuration and save to file"""
        # Update configuration
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

        # Save to file
        self._save_to_file()
        return {"status": "success", "message": "Configuration updated"}


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
            print(f"Cannot report progress - task {self.task_id} has no active WebSocket connection")
            print(f"Task {self.task_id} will continue processing in background")
            return

        try:
            self.current_stage += 1
            progress = int((self.current_stage / self.total_stages) * 100)
            stage_name = self.stages[self.current_stage - 1] if self.current_stage <= len(self.stages) else "Complete"

            message = {
                "progress": progress,
                "status": f"Stage: {stage_name}",
            }

            # Ensure WebSocket connection is still valid
            if active_connections[self.task_id].client_state == WebSocketState.CONNECTED:
                print(f"Sending progress update: task {self.task_id}, progress {progress}%, stage {stage_name}")
                await active_connections[self.task_id].send_json(message)
            else:
                print(f"Cannot send progress - WebSocket connection for task {self.task_id} is closed")
                print(f"Task {self.task_id} will continue processing in background")
                # Remove invalid connection
                if self.task_id in active_connections:
                    active_connections.pop(self.task_id)
                    print(f"Removed invalid WebSocket connection: {self.task_id}")
        except Exception as e:
            print(f"Error reporting progress: {str(e)}")
            print(f"Task {self.task_id} will continue processing in background")
            traceback.print_exc()

    async def fail_stage(self, error_message: str):
        if self.task_id not in active_connections:
            print(f"Cannot report failure - task {self.task_id} has no active WebSocket connection")
            return

        try:
            error_msg = f"{self.stages[self.current_stage]} error: {error_message}"

            # Ensure WebSocket connection is still valid
            if active_connections[self.task_id].client_state == WebSocketState.CONNECTED:
                await active_connections[self.task_id].send_json({
                    "progress": 100,
                    "status": "Failed",
                    "error": error_msg
                })
                self.failed = True
                print(f"Task {self.task_id}: {error_msg}")
            else:
                print(f"Cannot send failure notification - WebSocket connection for task {self.task_id} is closed")
        except Exception as e:
            print(f"Error reporting failure: {str(e)}")
            traceback.print_exc()


async def send_progress(websocket: Optional[WebSocket], status: str, progress: int):
    """Send progress update"""
    if websocket is None:
        print(f"Cannot send progress - websocket is None, status: {status}, progress: {progress}")
        return

    try:
        message = {"progress": progress, "status": status}
        await websocket.send_json(message)
        print(f"Progress sent: {progress}%, {status}")
    except Exception as e:
        print(f"Error sending progress: {str(e)}")
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
    """Process uploaded file"""
    if not filepath or not os.path.exists(filepath):
        raise HTTPException(status_code=400, detail="File does not exist")

    # Read file content
    with open(filepath, "rb") as f:
        content = f.read()

    # If it's a PDF file, process PDF
    if content_type == "application/pdf":
        try:
            # Try to import PDF processing module
            pdf_module_available = False

            try:
                from Main.runtxt import LightPDFProcessor
                pdf_module_available = True
                print("Found PDF processing module, will use LightPDFProcessor")
            except ImportError:
                try:
                    from runtxt import LightPDFProcessor
                    pdf_module_available = True
                    print("Found PDF processing module, will use LightPDFProcessor")
                except ImportError:
                    print("PDF processing module not available, will process PDF as plain text")

            if pdf_module_available:
                # Use LightPDFProcessor to process PDF
                print(f"Starting PDF processing: {filepath}")
                print(f"File absolute path: {os.path.abspath(filepath)}")
                print(f"File size: {os.path.getsize(filepath)} bytes")

                try:
                    # Pass task_id to ensure each upload is processed uniquely
                    processor = LightPDFProcessor(filepath, task_id=task_id)
                    print("LightPDFProcessor instantiated successfully")
                except Exception as e:
                    print(f"Failed to instantiate LightPDFProcessor: {str(e)}")
                    traceback.print_exc()
                    return {
                        "status": "warning",
                        "type": "pdf_error",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": f"PDF processing initialization failed: {str(e)}"
                    }

                # Check if WebSocket connection exists
                if task_id in active_connections:
                    # Send processing start notification
                    try:
                        await active_connections[task_id].send_json({
                            "status": "Processing",
                            "progress": 30,
                            "message": "Parsing PDF file, please wait..."
                        })
                        print("Sent PDF processing start notification")
                    except Exception as e:
                        print(f"Failed to send PDF processing start notification: {str(e)}")

                # Asynchronously generate summary
                try:
                    print("Starting asynchronous PDF summary generation")
                    # Set longer timeout, PDF processing needs more time
                    summary = await asyncio.wait_for(
                        processor.generate_summary(),
                        timeout=300  # Set 5 minute timeout, much longer than default
                    )
                    print("PDF summary generation call completed")

                    # Check if summary is None or not a string
                    if summary is None:
                        print(f"Warning: PDF processing result is None")
                        summary = "PDF processing failed, unable to generate summary. Please ensure the PDF file is valid and readable."
                    elif not isinstance(summary, str):
                        print(f"Warning: PDF processing result is not string type, but {type(summary)}")
                        # Try to convert to string
                        try:
                            summary = str(summary)
                            print(f"Converted result to string, length: {len(summary)}")
                        except Exception as e:
                            print(f"Failed to convert result to string: {str(e)}")
                            summary = "PDF processing result format is abnormal, cannot be displayed."

                    # Ensure summary is string before using len()
                    summary_length = len(summary) if isinstance(summary, str) else 0
                    print(f"PDF summary generation completed, length: {summary_length} characters")

                    # Check if summary is empty
                    if summary_length < 10:  # Set a reasonable minimum length threshold
                        print(f"Warning: Generated summary is empty or too short ({summary_length} characters)")
                        summary = "Processing result is empty. PDF may not contain enough text content, or format is not supported."

                    result = {
                        "status": "success",
                        "type": "pdf",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": summary
                    }

                    # Save a copy of the result to task directory before returning
                    task_dir = os.path.join(RUNS_DIR, task_id)
                    os.makedirs(task_dir, exist_ok=True)
                    with open(os.path.join(task_dir, "pdf_result.json"), "w", encoding="utf-8") as f:
                        json.dump(result, f, ensure_ascii=False)
                    print(f"Saved PDF processing result to: {os.path.join(task_dir, 'pdf_result.json')}")

                    # If WebSocket connection exists, send processing success notification
                    if task_id in active_connections:
                        try:
                            # Add a small delay to ensure connection stability
                            await asyncio.sleep(0.5)

                            # Verify connection state
                            if active_connections[task_id].client_state == WebSocketState.CONNECTED:
                                print(f"Sending PDF processing result via WebSocket, length: {summary_length} characters")

                                # First send a preparation message
                                await active_connections[task_id].send_json({
                                    "status": "Ready",
                                    "progress": 95,
                                    "message": "PDF processing completed, preparing to display results..."
                                })

                                # Wait a short time
                                await asyncio.sleep(0.5)

                                # Send final result
                                await active_connections[task_id].send_json({
                                    "status": "Complete",
                                    "progress": 100,
                                    "type": "pdf",
                                    "file_name": os.path.basename(filepath),
                                    "content": summary
                                })
                                print(f"PDF processing result sent via WebSocket")

                                # Record after successful sending
                                with open(os.path.join(task_dir, "websocket_sent.txt"), "w") as f:
                                    f.write("Send time: " + datetime.now().isoformat())
                            else:
                                print(f"WebSocket connection state abnormal: {active_connections[task_id].client_state}, trying to provide result via API")
                                # Save a marker in task directory to make API interface discover the generated result
                                with open(os.path.join(task_dir, "result_ready.txt"), "w") as f:
                                    f.write("1")
                        except Exception as ws_error:
                            print(f"Failed to send result via WebSocket: {str(ws_error)}")
                            traceback.print_exc()
                    else:
                        print(f"Cannot find WebSocket connection for task {task_id}, result will be provided via API interface")

                    return result
                except asyncio.TimeoutError as e:
                    # If generation times out, return friendly error message
                    print(f"PDF processing timeout: {filepath}, error: {str(e)}")
                    traceback.print_exc()
                    return {
                        "status": "warning",
                        "type": "pdf_timeout",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": "PDF processing takes longer time, system continues processing in background. Please try again later or upload a smaller file."
                    }
                except Exception as e:
                    print(f"Error during PDF summary generation: {str(e)}")
                    traceback.print_exc()
                    return {
                        "status": "warning",
                        "type": "pdf_error",
                        "task_id": task_id,
                        "file_name": os.path.basename(filepath),
                        "content": f"PDF processing failed: {str(e)}"
                    }

            else:
                # If no special PDF processor, use basic PyPDF2 to extract text
                if HAS_PYPDF2:
                    text = ""
                    with open(filepath, 'rb') as f:
                        # Send processing status
                        if task_id in active_connections:
                            await active_connections[task_id].send_json({
                                "status": "Processing",
                                "progress": 30,
                                "message": "Parsing PDF file, please wait..."
                            })

                        reader = PyPDF2.PdfReader(f)
                        total_pages = len(reader.pages)

                        for page_num in range(total_pages):
                            # Update progress every 10% of pages
                            if task_id in active_connections and page_num % max(1, total_pages // 10) == 0:
                                progress = min(30 + int(60 * page_num / total_pages), 90)
                                await active_connections[task_id].send_json({
                                    "status": "Processing",
                                    "progress": progress,
                                    "message": f"Processing PDF page {page_num + 1}/{total_pages}..."
                                })

                            page = reader.pages[page_num]
                            text += f"\n--- Page {page_num + 1} ---\n"
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
                        "content": "PDF file uploaded, but system does not have PDF processing library installed. Please install PyPDF2 to enable PDF processing."
                    }
        except Exception as e:
            print(f"PDF processing failed: {str(e)}")
            traceback.print_exc()
            # Return error message instead of throwing exception
            return {
                "status": "warning",  # Changed to warning instead of error, show friendlier message
                "type": "pdf_error",
                "task_id": task_id,
                "file_name": os.path.basename(filepath),
                "content": f"PDF processing takes longer time, system will continue processing in background. You can check the result later, or try uploading a PDF with less content."
            }

    # If it's a text file, read content directly
    if content_type == "text/plain":
        try:
            text_content = content.decode("utf-8")
        except UnicodeDecodeError:
            # Try other encodings
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

    raise HTTPException(status_code=400, detail="Unsupported file type")


@app.websocket("/ws/{task_id}")
async def websocket_endpoint(websocket: WebSocket, task_id: str):
    try:
        print(f"Received WebSocket connection request: {task_id}")

        # Accept connection
        await websocket.accept()
        print(f"WebSocket connection accepted: {task_id}")

        # Store connection - use global dictionary to save connection
        active_connections[task_id] = websocket
        print(f"Saved WebSocket connection: {task_id}, current connections: {len(active_connections)}")

        # Send initial status
        try:
            await websocket.send_json({
                "type": "connection_status",
                "status": "connected",
                "task_id": task_id,
                "message": "WebSocket connection successful"
            })
            print(f"Sent connection success message: {task_id}")
        except Exception as e:
            print(f"Failed to send initial status: {task_id}, error: {str(e)}")

        # If task already exists, send current status
        if task_id in progress_store:
            task_type = progress_store[task_id].get("type", "unknown")
            try:
                await websocket.send_json({
                    "type": "task_info",
                    "task_id": task_id,
                    "task_type": task_type,
                    "message": f"Task {task_id} status: Processing"
                })
                print(f"Sent task information: {task_id}, type: {task_type}")
            except Exception as e:
                print(f"Failed to send task information: {task_id}, error: {str(e)}")

        # Keep connection until client disconnects
        try:
            while True:
                data = await websocket.receive_json()
                print(f"Received WebSocket message: {task_id}, data: {data}")

                # Handle ping message
                if data.get("type") == "ping":
                    await websocket.send_json({"type": "pong"})
                    print(f"Replied to ping: {task_id}")
                    continue

                # Handle stop thinking request
                if data.get("type") == "stop_thinking":
                    print(f"Received stop thinking request: {task_id}")
                    # Set flag to tell task processing function to stop processing
                    if task_id in progress_store:
                        progress_store[task_id]["stopped"] = True
                        # Add flag indicating this is a user-initiated stop request, not WebSocket disconnection
                        progress_store[task_id]["user_stopped"] = True
                    await websocket.send_json({
                        "type": "stop_thinking_response",
                        "status": "success",
                        "message": "Thinking stopped"
                    })
                    break

        except json.JSONDecodeError:
            # Handle non-JSON messages
            try:
                text_data = await websocket.receive_text()
                print(f"Received non-JSON text message: {task_id}, data: {text_data}")
            except Exception as e:
                print(f"Error receiving text message: {str(e)}")

        except WebSocketDisconnect:
            print(f"WebSocket connection disconnected: {task_id}")
            # Important modification: Do not stop task processing when connection disconnects
            # Only record disconnection event, but do not mark task as stopped
            print(f"WebSocket connection disconnected, but task {task_id} will continue processing")
            # Delete connection from active_connections
            if task_id in active_connections:
                active_connections.pop(task_id)
                print(f"Removed connection {task_id} from active_connections")

        except Exception as e:
            print(f"WebSocket connection error: {task_id}, error: {str(e)}")
    except Exception as e:
        print(f"Error handling WebSocket connection: {task_id}, error: {str(e)}")
        traceback.print_exc()
    finally:
        # Note: Do not remove connection here, let it remain until task completion
        print(f"WebSocket connection handling completed: {task_id}")


@app.post("/api/chat")
async def chat(request: Request):
    data = await request.json()
    message = data.get("message")
    if not message:
        raise HTTPException(status_code=400, detail="Message cannot be empty")

    # Use task_id from frontend if provided, otherwise generate new one
    task_id = data.get("task_id") or str(uuid.uuid4())

    progress_store[task_id] = {"type": "chat", "message": message, "stopped": False}

    # Process chat message asynchronously
    asyncio.create_task(process_chat(task_id, message))

    return {"task_id": task_id}


async def process_chat(task_id: str, message: str):
    print(f"Starting to process task {task_id}, waiting for WebSocket connection...")

    # Initialize task status
    progress_store[task_id] = {"type": "chat", "message": message, "stopped": False}

    # Wait for WebSocket connection
    connection_timeout = 10  # 10 seconds timeout
    for _ in range(connection_timeout * 10):  # Wait longer (10 seconds)
        if task_id in active_connections:
            print(f"Found WebSocket connection for task {task_id}")
            break
        await asyncio.sleep(0.1)

        # Check if task has been stopped by user
        # Note: WebSocket disconnection should not stop task processing
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"Task {task_id} was stopped by user while waiting for WebSocket connection")
            return

    # Continue processing even if no WebSocket connection found
    if task_id not in active_connections:
        print(f"Warning: Task {task_id} has no active WebSocket connection, but will continue processing")

    print(f"Starting to process chat task: {task_id}")
    progress = ProgressManager(task_id, STAGES)

    try:
        # Initialization
        await progress.report_progress()
        print(f"Task {task_id}: Initialization complete")

        # Check if task has been stopped by user (not connection disconnect)
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped", False):
            print(f"Task {task_id} was stopped by user after initialization")
            return

        # Simulate parsing
        await asyncio.sleep(0.5)
        await progress.report_progress()
        print(f"Task {task_id}: Parsing complete")

        # Check if task has been stopped by user (not connection disconnect)
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped", False):
            print(f"Task {task_id} was stopped by user after parsing")
            return

        # Call API
        await progress.report_progress()
        print(f"Task {task_id}: Starting API call")

        # Check if task has been stopped by user (not connection disconnect)
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped", False):
            print(f"Task {task_id} was stopped by user before API call")
            return

        # Call API directly instead of through function
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

            print(f"Task {task_id}: Sending API request to {config.api_base}/chat/completions")
            async with aiohttp.ClientSession() as session:
                async with session.post(
                        f"{config.api_base}/chat/completions",
                        headers=headers,
                        json=data
                ) as response:
                    print(f"Task {task_id}: Received API response, status code: {response.status}")

                    # Check if task has been stopped by user (not connection disconnect)
                    if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped", False):
                        print(f"Task {task_id} was stopped by user after receiving API response")
                        return

                    if response.status != 200:
                        error_text = await response.text()
                        print(f"Task {task_id}: API error: {error_text}")
                        raise Exception(f"API error: {response.status}, {error_text}")

                    result = await response.json()
                    if "choices" not in result or not result["choices"]:
                        raise Exception("API response format error: no choices field")

                    reply = result["choices"][0]["message"]["content"]
                    print(f"Task {task_id}: API reply length: {len(reply)} characters")

                    # Store result as dictionary
                    result = {
                        "status": "success",
                        "reply": reply,
                        "task_id": task_id
                    }
        except Exception as api_error:
            print(f"Task {task_id}: API call failed: {str(api_error)}")
            traceback.print_exc()
            raise api_error

        print(f"Task {task_id}: API call complete")

        # Check if task has been stopped by user (not connection disconnect)
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped", False):
            print(f"Task {task_id} was stopped by user after API call completion")
            return

        # Generate reply
        await asyncio.sleep(0.5)
        await progress.report_progress()
        print(f"Task {task_id}: Reply generation complete")

        # Check if task has been stopped by user (not connection disconnect)
        if progress_store.get(task_id, {}).get("stopped", False) and progress_store.get(task_id, {}).get("user_stopped", False):
            print(f"Task {task_id} was stopped by user after reply generation")
            return

        # Check WebSocket connection again
        if task_id in active_connections:
            try:
                print(f"Task {task_id}: Sending final reply")
                final_message = {
                    "progress": 100,
                    "status": "Complete",
                    "reply": result["reply"]
                }

                # Ensure WebSocket is still in open state
                if active_connections[task_id].client_state == WebSocketState.CONNECTED:
                    await active_connections[task_id].send_json(final_message)
                    print(f"Task {task_id}: Final reply sent: {len(result['reply'])} characters")
                else:
                    print(f"Task {task_id}: WebSocket closed, cannot send reply")

                # Keep connection for a while after message sent
                await asyncio.sleep(1)
            except Exception as e:
                print(f"Error sending final reply: {task_id}, error: {str(e)}")
                traceback.print_exc()
        else:
            print(f"Task {task_id}: WebSocket connection lost, cannot send reply")

        # Save result to file
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        with open(os.path.join(task_dir, "result.json"), "w") as f:
            json.dump(result, f, ensure_ascii=False)

        # Complete
        await progress.report_progress()
        print(f"Task {task_id}: All complete")

        # After task completion, remove connection and task from dictionaries
        if task_id in active_connections:
            active_connections.pop(task_id, None)
            print(f"Task {task_id}: Cleaned up WebSocket connection")

    except Exception as e:
        print(f"Task {task_id} processing failed: {str(e)}")
        await progress.fail_stage(str(e))
        traceback.print_exc()

        # Ensure connection is cleaned up on error
        if task_id in active_connections:
            active_connections.pop(task_id, None)
            print(f"Task {task_id}: Cleaned up WebSocket connection after error")


@app.post("/api/upload")
async def upload_file(
        file: UploadFile = File(...),
        task_id: str = Form(None),
):
    # If client didn't provide task_id, generate a new one
    if not task_id:
        task_id = str(uuid.uuid4())

    progress_store[task_id] = {"type": "upload", "filename": file.filename, "stopped": False}
    print(f"Starting to process uploaded file: {file.filename}, Task ID: {task_id}, Content Type: {file.content_type}")

    # Read file content immediately to avoid file being closed
    content = await file.read()
    file_size = len(content)
    print(f"File size: {file_size} bytes")

    if file_size > config.upload_max_size:
        print(f"File size exceeds limit: {file_size} > {config.upload_max_size}")
        raise HTTPException(
            status_code=400,
            detail=f"File size exceeds limit (maximum {config.upload_max_size / 1024 / 1024}MB)"
        )

    # Generate filename
    file_hash = hashlib.md5(content).hexdigest()
    ext = os.path.splitext(file.filename)[1]
    filename = f"{task_id}_{file_hash}{ext}"
    filepath = os.path.join(UPLOADS_DIR, filename)
    print(f"Saving file to: {filepath}")

    # Ensure upload directory exists
    os.makedirs(UPLOADS_DIR, exist_ok=True)

    # Save file
    with open(filepath, "wb") as f:
        f.write(content)
    print(f"File saved, size: {os.path.getsize(filepath)} bytes")

    # Process file asynchronously, pass file path instead of file object
    asyncio.create_task(process_upload(task_id, file.filename, filepath, file.content_type))

    return {"task_id": task_id}


async def process_upload(task_id: str, filename: str, filepath: str, content_type: str):
    # Initialize task status
    progress_store[task_id] = {"type": "upload", "filename": filename, "stopped": False}
    print(f"Processing uploaded file: {filename}, Task ID: {task_id}, Content Type: {content_type}")

    # Wait for WebSocket connection
    for _ in range(50):  # Wait up to 1 second
        if task_id in active_connections:
            print(f"Found WebSocket connection: {task_id}")
            break
        await asyncio.sleep(0.02)

        # Check if task has been stopped
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"Task {task_id} was stopped while waiting for WebSocket connection")
            return

    progress = ProgressManager(task_id, STAGES)

    try:
        # Initialize
        await progress.report_progress()
        print(f"Task {task_id}: Initialization complete")

        # Check if task has been stopped
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"Task {task_id} was stopped after initialization")
            return

        # Parse file
        await progress.report_progress()
        print(f"Task {task_id}: Starting to parse file")

        # For PDF files, send special prompt message
        if content_type == "application/pdf" and task_id in active_connections:
            await active_connections[task_id].send_json({
                "progress": 40,
                "status": "Processing PDF file...",
                "message": "PDF processing may take longer time, please wait. Large PDF files may take several minutes."
            })
            print(f"Task {task_id}: Sent PDF processing prompt")

        print(f"Task {task_id}: Calling process_file to process file")
        result = await process_file(filepath, filename, content_type, task_id)
        print(f"Task {task_id}: File processing completed, result type: {result.get('type', 'unknown')}")

        # Check if task has been stopped
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"Task {task_id} was stopped after parsing")
            return

        # Call API
        await progress.report_progress()
        print(f"Task {task_id}: API stage completed")

        # Check if task has been stopped
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"Task {task_id} was stopped after calling API")
            return

        # Generate reply
        await asyncio.sleep(0.5)
        await progress.report_progress()
        print(f"Task {task_id}: Reply generation completed")

        # Check if task has been stopped
        if progress_store.get(task_id, {}).get("stopped", False):
            print(f"Task {task_id} was stopped after reply generation")
            return

        # Complete
        if task_id in active_connections:
            # Prepare response content based on result type
            response_content = ""
            status_message = "Complete"

            # Special handling for PDF-related results
            if result["type"] == "pdf_timeout":
                status_message = "PDF processing takes longer time"
                response_content = result.get("content", "")
                print(f"Task {task_id}: PDF processing timeout")
            elif result["type"] == "pdf_error":
                status_message = "PDF processing notification"
                response_content = result.get("content", "")
                print(f"Task {task_id}: PDF processing error: {response_content}")
            elif result["type"] == "pdf_unsupported":
                status_message = "PDF feature limited"
                response_content = result.get("content", "")
                print(f"Task {task_id}: PDF feature limited: {response_content}")
            else:
                # For other types, use summary or content field
                response_content = result.get("summary", result.get("content", ""))
                print(f"Task {task_id}: Processing successful, response content length: {len(response_content)} characters")

            try:
                await active_connections[task_id].send_json({
                    "progress": 100,
                    "status": status_message,
                    "type": result["type"],
                    "file_name": result["file_name"],
                    "content": response_content
                })
                print(f"Task {task_id}: Sent final response")
            except Exception as e:
                print(f"Task {task_id}: Failed to send response: {str(e)}")
                traceback.print_exc()
        else:
            print(f"Task {task_id}: Cannot send response, WebSocket connection is closed")

        # Save result to file
        task_dir = os.path.join(RUNS_DIR, task_id)
        os.makedirs(task_dir, exist_ok=True)
        result_file = os.path.join(task_dir, "result.json")
        with open(result_file, "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False)
        print(f"Task {task_id}: Result saved to {result_file}")

        # Complete
        await progress.report_progress()
        print(f"Task {task_id}: All complete")

    except Exception as e:
        print(f"Task {task_id} processing failed: {str(e)}")
        await progress.fail_stage(str(e))
        traceback.print_exc()


@app.get("/api/result/{task_id}")
async def get_result(task_id: str):
    """Get task result"""
    print(f"Request to get result for task {task_id}")

    # First check for PDF-specific result file
    pdf_result_file = os.path.join(RUNS_DIR, task_id, "pdf_result.json")
    if os.path.exists(pdf_result_file):
        try:
            print(f"Found PDF result file: {pdf_result_file}")
            with open(pdf_result_file, "r", encoding="utf-8") as f:
                result = json.load(f)
                print(
                    f"Successfully loaded PDF result file, type: {result.get('type', 'unknown')}, content length: {len(result.get('content', ''))}")
                return result
        except Exception as e:
            print(f"Error reading PDF result file: {str(e)}")
            traceback.print_exc()

    # Check regular result file
    result_file = os.path.join(RUNS_DIR, task_id, "result.json")
    if os.path.exists(result_file):
        try:
            print(f"Found result file for task {task_id}")
            with open(result_file, "r", encoding="utf-8") as f:
                result = json.load(f)
                print(f"Successfully loaded result file, type: {result.get('type', 'unknown')}")
                return result
        except Exception as e:
            print(f"Error reading result file for task {task_id}: {str(e)}")
            traceback.print_exc()
            raise HTTPException(status_code=500, detail=f"Error reading result file: {str(e)}")

    # Check if there's a marker file indicating result was generated but WebSocket send failed
    ready_file = os.path.join(RUNS_DIR, task_id, "result_ready.txt")
    if os.path.exists(ready_file):
        # Try to find any possible result files
        print(f"Task {task_id} has completion marker but result file is missing")
        # Look for all json files in directory
        task_dir = os.path.join(RUNS_DIR, task_id)
        json_files = [f for f in os.listdir(task_dir) if f.endswith('.json')]

        if json_files:
            # Try to load the first JSON file found
            try:
                with open(os.path.join(task_dir, json_files[0]), "r", encoding="utf-8") as f:
                    print(f"Attempting to load alternative result file: {json_files[0]}")
                    result = json.load(f)
                    return result
            except Exception as e:
                print(f"Error reading alternative result file: {str(e)}")
                traceback.print_exc()

    # If it's an ongoing task, try to get from memory
    if task_id in progress_store:
        task_info = progress_store[task_id]
        task_type = task_info.get("type")

        # Task is ongoing but no result yet
        print(f"Task {task_id} is still in progress, type: {task_type}")

        # Try to call API directly for result
        if task_type == "chat":
            try:
                message = task_info.get("message", "")
                if message:
                    print(f"Attempting to recall API for task {task_id}")
                    result = await call_llm_api(message, task_id)

                    # Save result to file
                    task_dir = os.path.join(RUNS_DIR, task_id)
                    os.makedirs(task_dir, exist_ok=True)
                    with open(result_file, "w", encoding="utf-8") as f:
                        json.dump(result, f, ensure_ascii=False)

                    print(f"Generated new API result for task {task_id}")
                    return result
            except Exception as e:
                print(f"Error recalling API for task {task_id}: {str(e)}")
                traceback.print_exc()

        # Return in-progress status
        return {
            "status": "processing",
            "task_id": task_id,
            "task_type": task_type,
            "message": "Task is being processed"
        }

    # Task doesn't exist
    print(f"No result or progress information found for task {task_id}")
    raise HTTPException(status_code=404, detail="Task result does not exist")


@app.get("/api/test")
async def test_api():
    try:
        print("API test endpoint called")
        # First do a simple self-check
        status_info = {
            "server_status": "Running",
            "api_base": config.api_base,
            "model": config.model,
            "upload_dir_exists": os.path.exists(UPLOADS_DIR),
            "cache_dir_exists": os.path.exists(CACHE_DIR),
            "runs_dir_exists": os.path.exists(RUNS_DIR),
        }

        print("Starting API test...")
        # Try to call LLM API
        try:
            start_time = datetime.now()
            result = await call_llm_api("This is a test message. Please respond with 'API test successful'")
            end_time = datetime.now()
            response_time = (end_time - start_time).total_seconds() * 1000  # milliseconds

            status_info["api_call_status"] = "Success"
            status_info["api_response_time_ms"] = response_time
            status_info["api_response"] = result["reply"]

            return {
                "status": "success",
                "message": "API connection test successful",
                "server_info": status_info,
                "response": result
            }

        except Exception as api_error:
            print(f"API call failed: {str(api_error)}")
            traceback.print_exc()

            # Try to verify if API key is valid
            if isinstance(api_error, HTTPException) and api_error.status_code == 401:
                status_info["api_call_status"] = "Invalid key"
            else:
                status_info["api_call_status"] = "Failed"

            status_info["api_error"] = str(api_error)

            return {
                "status": "error",
                "message": f"API call failed: {str(api_error)}",
                "server_info": status_info,
                "detail": str(api_error)
            }

    except Exception as e:
        print(f"Error during API test: {str(e)}")
        traceback.print_exc()
        raise


@app.get("/")
async def hello():
    return {"message": "Deepchat API service is running"}


async def test_connection():
    try:
        await call_llm_api("Test connection")
        print("LLM API connection successful")
        return True
    except Exception as e:
        print(f"LLM API connection failed: {e}")
        return False


@app.get("/api/ping")
async def ping():
    """Simple ping test to check if server is running"""
    return {"status": "ok", "timestamp": datetime.now().isoformat()}


@app.post("/api/config")
async def update_config(request: Request):
    """Update API configuration"""
    try:
        data = await request.json()
        result = config.update_config(data)
        return result
    except Exception as e:
        print(f"Error updating configuration: {str(e)}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Failed to update configuration: {str(e)}")


@app.get("/api/config")
async def get_config():
    """Get current API configuration"""
    # Return configuration but hide complete API key value
    config_data = {
        "api_base": config.api_base,
        "model": config.model,
        "temperature": config.temperature,
        "max_tokens": config.max_tokens,
        "upload_max_size": config.upload_max_size
    }

    # Only show API key existence status, don't return actual value
    if config.api_key:
        # If there's an API key, only return first 4 and last 4 digits, replace middle with *
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
    print(f"Port is {port}")
    asyncio.run(test_connection())
    print("Service started......")
    uvicorn.run(app, host="0.0.0.0", port=port)
