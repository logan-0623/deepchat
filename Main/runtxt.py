import os
import sys
import json
import asyncio
import hashlib
import PyPDF2
from typing import Dict
# 正确导入OpenAI的异步客户端
from openai import AsyncOpenAI
import traceback

# 导入配置（如果作为独立脚本运行，则使用默认值）
try:
    from backend import config
except ImportError:
    class Config:
        def __init__(self):
            self.api_key = "sk-9535e904340c486ca1cb4a9ae8fd1fc0"
            self.api_base = "https://api.deepseek.com/v1"
            self.model = "deepseek-chat"
    config = Config()

class LightPDFProcessor:
    def __init__(self, pdf_path: str, task_id: str = None):
        try:
            print(f"初始化 LightPDFProcessor, PDF路径: {pdf_path}")
            self.pdf_path = pdf_path
            
            # 确保文件存在且可读
            if not os.path.exists(pdf_path):
                raise FileNotFoundError(f"PDF文件不存在: {pdf_path}")
            
            if not os.path.isfile(pdf_path):
                raise ValueError(f"指定的路径不是文件: {pdf_path}")
            
            # 使用提供的task_id或计算哈希值作为备用
            if task_id:
                self.task_id = task_id
                print(f"使用提供的任务ID: {self.task_id}")
            else:
                try:
                    self.task_id = hashlib.md5(open(pdf_path, 'rb').read()).hexdigest()
                    print(f"生成任务ID: {self.task_id}")
                except Exception as e:
                    print(f"计算文件哈希值失败: {str(e)}")
                    # 使用文件名作为备用
                    self.task_id = hashlib.md5(pdf_path.encode()).hexdigest()
                    print(f"使用备用任务ID: {self.task_id}")
            
            # 创建缓存目录 - 使用绝对路径
            # 获取当前脚本所在的根目录
            script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            # 使用绝对路径创建缓存目录
            self.cache_dir = os.path.join(script_dir, "pdf_cache", self.task_id)
            print(f"使用缓存目录: {self.cache_dir}")
            os.makedirs(self.cache_dir, exist_ok=True)
            
            # 初始化API客户端 - 使用AsyncOpenAI
            try:
                # 尝试使用backend配置的API，防止API基础URL错误
                api_base = getattr(config, "api_base", "https://api.deepseek.com/v1")
                # 修复API基础URL，确保没有/v1后缀
                if api_base.endswith("/v1"):
                    api_base = api_base[:-3]
                
                api_key = getattr(config, "api_key", "")
                if not api_key:
                    print("警告: API密钥为空")
                
                print(f"初始化AsyncOpenAI客户端, 基础URL: {api_base}")
                self.client = AsyncOpenAI(
                    api_key=api_key,
                    base_url=api_base
                )
                print("API客户端初始化成功")
            except Exception as e:
                print(f"API客户端初始化失败: {str(e)}")
                traceback.print_exc()
                # 设置一个标记，表示客户端初始化失败
                self.api_init_failed = True
            
        except Exception as e:
            print(f"LightPDFProcessor初始化失败: {str(e)}")
            traceback.print_exc()
            # 重新抛出异常以便调用者知道初始化失败了
            raise

    def _parse_with_pypdf2(self) -> str:
        """使用 PyPDF2 解析 PDF 文本"""
        text = []
        try:
            with open(self.pdf_path, 'rb') as f:
                reader = PyPDF2.PdfReader(f)
                for page in reader.pages:
                    page_text = page.extract_text()
                    if page_text:
                        cleaned_text = ' '.join(page_text.split()).strip()
                        text.append(cleaned_text)
                return '\n\n'.join(text)
        except Exception as e:
            raise RuntimeError(f"PDF解析失败: {str(e)}")

    async def generate_summary(self) -> str:
        """生成结构化学术摘要"""
        print("========================Generating Structured Abstract===================================")
        print(f"开始处理PDF: {self.pdf_path}, 任务ID: {self.task_id}")

        cache_file = os.path.join(self.cache_dir, "structured_abstract.json")
        print(f"缓存文件路径: {cache_file}")

        # 检查缓存
        if os.path.exists(cache_file):
            try:
                print(f"找到缓存文件: {cache_file}")
                with open(cache_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    if 'abstract' in data and isinstance(data['abstract'], str) and data['abstract'].strip():
                        print(f"使用缓存的摘要，长度: {len(data['abstract'])} 字符")
                        return data['abstract']
                    else:
                        print(f"缓存文件格式异常或内容为空，重新生成摘要")
            except Exception as e:
                print(f"读取缓存文件失败: {str(e)}，重新生成摘要")
                traceback.print_exc()
        else:
            print(f"未找到缓存文件，将创建新的摘要")

        # 解析原始文本
        try:
            print(f"开始解析PDF文本...")
            raw_text = self._parse_with_pypdf2()
            text_length = len(raw_text) if raw_text else 0
            print(f"提取的文本长度: {text_length} 字符")
            
            if not raw_text or text_length < 100:  # 设置一个合理的最小长度阈值
                print(f"警告: 提取的文本内容为空或过短 ({text_length} 字符)")
                return "无法从PDF提取有效文本内容，请确保PDF文件包含可提取的文本。"
        except Exception as e:
            print(f"解析PDF失败: {str(e)}")
            traceback.print_exc()
            return f"PDF解析失败: {str(e)}"

        # 构建结构化提示词
        structured_prompt = f"""请根据以下研究内容生成结构化学术摘要，严格遵循以下格式要求：

        # 格式规范
        1. 使用以下六级标题结构（加粗）：
        **Abstract**
        **Introduction** 
        **Related Work**
        **Methodology**  
        **Experiment**
        **Conclusion**
    
        2. 每个章节包含3-5个要点（• 开头），要点需满足：
        - 首字母大写，结尾无标点
        - 包含关键技术术语
        - 突出创新点和实验验证
    
        3. 专业术语使用规范：
        - 首次出现缩写时标注全称
        - 数学符号用$包裹
    
        # 研究内容输入
        {raw_text}
    
        请严格按照示例的术语规范、数学符号格式和结构要求生成摘要。"""

        try:
            # 尝试使用配置中的模型，如果失败则使用默认的"deepseek-chat"模型
            model = getattr(config, "model", "deepseek-chat")
            print(f"使用模型 {model} 调用API生成摘要...")
            
            # 检查客户端是否已初始化
            if not hasattr(self, 'client') or self.client is None:
                print("API客户端未初始化，重新创建客户端")
                api_base = config.api_base
                # 修复API基础URL，确保没有/v1后缀
                if api_base.endswith("/v1"):
                    api_base = api_base[:-3]
                
                self.client = AsyncOpenAI(api_key=config.api_key, base_url=api_base)

            # 调用异步API生成结构化摘要
            print("开始调用异步API...")
            response = await self.client.chat.completions.create(
                model=model,
                messages=[{"role": "user", "content": structured_prompt}],
                temperature=0.3,
                max_tokens=1500,
                top_p=0.9
            )
            print("API调用完成，解析响应...")

            # 检查响应是否有效
            if not response or not hasattr(response, 'choices') or not response.choices:
                print("API响应无效")
                return "API调用失败，无法生成PDF摘要。"

            # 正确从异步API响应中提取内容
            try:
                abstract = response.choices[0].message.content
                print(f"成功获取API响应，摘要长度: {len(abstract)} 字符")
                
                # 确保摘要不为空
                if not abstract or not abstract.strip():
                    print("警告: 获取的摘要为空")
                    return "API返回的摘要为空，请重试。"
            except Exception as e:
                print(f"无法从API响应中提取内容: {str(e)}")
                print(f"响应对象类型: {type(response)}")
                traceback.print_exc()
                return f"无法从API响应中提取内容: {str(e)}"

            # 缓存结果
            try:
                print(f"缓存摘要到: {cache_file}")
                # 确保缓存目录存在
                os.makedirs(os.path.dirname(cache_file), exist_ok=True)
                with open(cache_file, 'w', encoding='utf-8') as f:
                    json.dump({"abstract": abstract}, f, ensure_ascii=False)
                print(f"摘要已成功缓存")
            except Exception as e:
                print(f"缓存结果失败: {str(e)}")
                traceback.print_exc()

            return abstract

        except Exception as e:
            print(f"摘要生成失败: {str(e)}")
            traceback.print_exc()  # 打印完整错误堆栈
            return f"PDF摘要生成失败: {str(e)}"


async def main():
    # 更改文件夹路径
    pdf_path = "../2503.18227v3.pdf"

    processor = LightPDFProcessor(pdf_path)

    try:
        summary = await processor.generate_summary()
        print("# 文档摘要\n" + summary)

        # 保存摘要到与原PDF同目录的txt文件
        pdf_dir = os.path.dirname(pdf_path)
        pdf_name = os.path.splitext(os.path.basename(pdf_path))[0]
        txt_filename = f"{pdf_name}_structured_abstract.txt"
        txt_path = os.path.join(pdf_dir, txt_filename)
        with open(txt_path, 'w', encoding='utf-8') as f:
            f.write(summary)
        print(f"\n摘要已保存至：{txt_path}")

    except Exception as e:
        print(f"处理失败: {str(e)}")


if __name__ == "__main__":
    asyncio.run(main())