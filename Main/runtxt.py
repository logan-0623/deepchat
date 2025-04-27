import os
import sys
import json
import asyncio
import hashlib
import PyPDF2
from typing import Dict
# Correctly import OpenAI's asynchronous client
from openai import AsyncOpenAI
import traceback

# Import configuration (if running as a standalone script, use defaults)
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
            print(f"Initializing LightPDFProcessor, PDF path: {pdf_path}")
            self.pdf_path = pdf_path
            
            # Ensure the file exists and is readable
            if not os.path.exists(pdf_path):
                raise FileNotFoundError(f"PDF file not found: {pdf_path}")
            
            if not os.path.isfile(pdf_path):
                raise ValueError(f"The specified path is not a file: {pdf_path}")
            
            # Use provided task_id or compute a hash as fallback
            if task_id:
                self.task_id = task_id
                print(f"Using provided task ID: {self.task_id}")
            else:
                try:
                    self.task_id = hashlib.md5(open(pdf_path, 'rb').read()).hexdigest()
                    print(f"Generated task ID: {self.task_id}")
                except Exception as e:
                    print(f"Failed to compute file hash: {str(e)}")
                    # Use filename as fallback
                    self.task_id = hashlib.md5(pdf_path.encode()).hexdigest()
                    print(f"Using fallback task ID: {self.task_id}")
            
            # Create cache directory - use absolute path
            # Get the root directory of the current script
            script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            # Create cache directory with absolute path
            self.cache_dir = os.path.join(script_dir, "pdf_cache", self.task_id)
            print(f"Using cache directory: {self.cache_dir}")
            os.makedirs(self.cache_dir, exist_ok=True)
            
            # Initialize API client - use AsyncOpenAI
            try:
                # Try using API settings from backend to avoid base URL issues
                api_base = getattr(config, "api_base", "https://api.deepseek.com/v1")
                # Normalize API base URL to remove trailing /v1
                if api_base.endswith("/v1"):
                    api_base = api_base[:-3]
                
                api_key = getattr(config, "api_key", "")
                if not api_key:
                    print("Warning: API key is empty")
                
                print(f"Initializing AsyncOpenAI client, base URL: {api_base}")
                self.client = AsyncOpenAI(
                    api_key=api_key,
                    base_url=api_base
                )
                print("API client initialized successfully")
            except Exception as e:
                print(f"API client initialization failed: {str(e)}")
                traceback.print_exc()
                # Set a flag indicating client initialization failed
                self.api_init_failed = True
            
        except Exception as e:
            print(f"LightPDFProcessor initialization failed: {str(e)}")
            traceback.print_exc()
            # Reraise the exception so the caller knows initialization failed
            raise

    def _parse_with_pypdf2(self) -> str:
        """Parse PDF text using PyPDF2"""
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
            raise RuntimeError(f"PDF parsing failed: {str(e)}")

    async def generate_summary(self) -> str:
        """Generate structured academic abstract"""
        print("========================Generating Structured Abstract===================================")
        print(f"Starting to process PDF: {self.pdf_path}, Task ID: {self.task_id}")

        cache_file = os.path.join(self.cache_dir, "structured_abstract.json")
        print(f"Cache file path: {cache_file}")

        # Check cache
        if os.path.exists(cache_file):
            try:
                print(f"Found cache file: {cache_file}")
                with open(cache_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    if 'abstract' in data and isinstance(data['abstract'], str) and data['abstract'].strip():
                        print(f"Using cached abstract, length: {len(data['abstract'])} characters")
                        return data['abstract']
                    else:
                        print("Cache file format invalid or empty, regenerating abstract")
            except Exception as e:
                print(f"Failed to read cache file: {str(e)}, regenerating abstract")
                traceback.print_exc()
        else:
            print("No cache file found, will create new abstract")

        # Parse raw text
        try:
            print("Starting to parse PDF text...")
            raw_text = self._parse_with_pypdf2()
            text_length = len(raw_text) if raw_text else 0
            print(f"Extracted text length: {text_length} characters")
            
            if not raw_text or text_length < 100:  # Set a reasonable minimum length threshold
                print(f"Warning: Extracted text content is empty or too short ({text_length} characters)")
                return "Unable to extract valid text content from PDF. Please ensure the PDF contains extractable text."
        except Exception as e:
            print(f"Failed to parse PDF: {str(e)}")
            traceback.print_exc()
            return f"PDF parsing failed: {str(e)}"

        # Build the structured prompt
        structured_prompt = f"""Please generate a structured academic abstract based on the research content below, strictly following the format requirements:

        # Format Specification
        1. Use the following six-level heading structure (bolded):
        **Abstract**
        **Introduction**
        **Related Work**
        **Methodology**
        **Experiment**
        **Conclusion**

        2. Each section should contain 3-5 bullet points (• at the beginning), which must:
        - Start with a capital letter and have no punctuation at the end
        - Include key technical terms
        - Highlight innovations and experimental validation

        3. Terminology usage guidelines:
        - Spell out abbreviations in full when they first appear
        - Enclose mathematical symbols with $ ... $

        # Research Content Input
        {raw_text}

        Please strictly follow the example terminology guidelines, mathematical symbol formatting, and structural requirements to generate the abstract."""

        try:
            # Try using the configured model, otherwise fall back to "deepseek-chat"
            model = getattr(config, "model", "deepseek-chat")
            print(f"Using model {model} to generate abstract via API...")
            
            # Check if the client is initialized
            if not hasattr(self, 'client') or self.client is None:
                print("API client not initialized, recreating client")
                api_base = config.api_base
                # Normalize API base URL to remove trailing /v1
                if api_base.endswith("/v1"):
                    api_base = api_base[:-3]
                
                self.client = AsyncOpenAI(api_key=config.api_key, base_url=api_base)

            # Call the asynchronous API to generate the structured abstract
            print("Starting asynchronous API call...")
            response = await self.client.chat.completions.create(
                model=model,
                messages=[{"role": "user", "content": structured_prompt}],
                temperature=0.3,
                max_tokens=1500,
                top_p=0.9
            )
            print("Asynchronous API call completed, parsing response...")

            # Verify the response is valid
            if not response or not hasattr(response, 'choices') or not response.choices:
                print("Invalid API response")
                return "API call failed, unable to generate PDF summary."

            # Extract content correctly from the asynchronous API response
            try:
                abstract = response.choices[0].message.content
                print(f"Successfully retrieved API response, abstract length: {len(abstract)} characters")
                
                # Ensure the abstract is not empty
                if not abstract or not abstract.strip():
                    print("Warning: Retrieved abstract is empty")
                    return "API returned an empty abstract, please try again."
            except Exception as e:
                print(f"Unable to extract content from API response: {str(e)}")
                print(f"Response object type: {type(response)}")
                traceback.print_exc()
                return f"Unable to extract content from API response: {str(e)}"

            # Cache the result
            try:
                print(f"Caching abstract to: {cache_file}")
                # Ensure the cache directory exists
                os.makedirs(os.path.dirname(cache_file), exist_ok=True)
                with open(cache_file, 'w', encoding='utf-8') as f:
                    json.dump({"abstract": abstract}, f, ensure_ascii=False)
                print("Abstract successfully cached")
            except Exception as e:
                print(f"Failed to cache result: {str(e)}")
                traceback.print_exc()

            return abstract

        except Exception as e:
            print(f"Abstract generation failed: {str(e)}")
            traceback.print_exc()  # Print full error stack trace
            return f"PDF abstract generation failed: {str(e)}"


async def main():
    # Change the folder path as needed
    pdf_path = "../2503.18227v3.pdf"

    processor = LightPDFProcessor(pdf_path)

    try:
        summary = await processor.generate_summary()
        print("# Document Summary\n" + summary)

        # Save summary to a txt file in the same directory as the original PDF
        pdf_dir = os.path.dirname(pdf_path)
        pdf_name = os.path.splitext(os.path.basename(pdf_path))[0]
        txt_filename = f"{pdf_name}_structured_abstract.txt"
        txt_path = os.path.join(pdf_dir, txt_filename)
        with open(txt_path, 'w', encoding='utf-8') as f:
            f.write(summary)
        print(f"\nSummary saved to: {txt_path}")

    except Exception as e:
        print(f"Processing failed: {str(e)}")


if __name__ == "__main__":
    asyncio.run(main())