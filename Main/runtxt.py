import os
import sys
import json
import asyncio
import hashlib
import PyPDF2
from typing import Dict
from openai import AsyncAzureOpenAI
from openai import OpenAI

class LightPDFProcessor:
    def __init__(self, pdf_path: str):
        self.pdf_path = pdf_path
        self.task_id = hashlib.md5(open(pdf_path, 'rb').read()).hexdigest()
        self.cache_dir = os.path.join("pdf_cache", self.task_id)
        # 换api
        self.client = OpenAI(api_key="sk-c5f415578acb43a99871b38d273cafb7",
                             base_url="https://api.deepseek.com")

        os.makedirs(self.cache_dir, exist_ok=True)

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

        cache_file = os.path.join(self.cache_dir, "structured_abstract.json")

        # 检查缓存
        if os.path.exists(cache_file):
            with open(cache_file) as f:
                return json.load(f)['abstract']

        # 解析原始文本
        raw_text = self._parse_with_pypdf2()
        if not raw_text:
            raise ValueError("无法提取有效文本内容")

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
            # 调用API生成结构化摘要
            response = await self.client.chat.completions.create(
                model="ssr",
                messages=[{"role": "user", "content": structured_prompt}],
                temperature=0.3,
                max_tokens=1500,
                top_p=0.9
            )

            # 后处理验证
            abstract = response.choices[0].message.content

            # 缓存结果
            with open(cache_file, 'w') as f:
                json.dump({"abstract": abstract}, f)

            return abstract

        except Exception as e:
            raise RuntimeError(f"摘要生成失败: {str(e)}")


async def main():
    # 更改文件夹路径
    pdf_path = "./2503.18227v3.pdf"

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