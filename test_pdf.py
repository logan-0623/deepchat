import asyncio
import sys
import os
import time
from Main.runtxt import LightPDFProcessor

async def test_pdf_processing():
    """测试PDF处理功能"""
    # 获取要处理的PDF文件路径
    if len(sys.argv) > 1:
        pdf_path = sys.argv[1]
    else:
        # 尝试从uploads目录查找PDF文件
        uploads_dir = "uploads"
        pdf_files = [f for f in os.listdir(uploads_dir) if f.endswith('.pdf')]
        if not pdf_files:
            print("错误: 未找到PDF文件。请指定PDF文件路径作为参数或上传PDF文件。")
            return
        pdf_path = os.path.join(uploads_dir, pdf_files[0])
    
    print(f"测试处理PDF文件: {pdf_path}")
    
    # 检查文件是否存在
    if not os.path.exists(pdf_path):
        print(f"错误: 文件 {pdf_path} 不存在")
        return
    
    try:
        # 创建处理器实例
        start_time = time.time()
        print("初始化LightPDFProcessor...")
        processor = LightPDFProcessor(pdf_path)
        print(f"初始化耗时: {time.time() - start_time:.2f}秒")
        
        # 生成摘要
        print("开始生成摘要...")
        start_time = time.time()
        summary = await processor.generate_summary()
        processing_time = time.time() - start_time
        
        # 显示结果
        print(f"处理完成，耗时: {processing_time:.2f}秒")
        print(f"摘要长度: {len(summary)} 字符")
        print("\n摘要内容前200个字符:")
        print(summary[:200] + "...")
        
        # 保存结果
        output_file = f"test_result_{os.path.basename(pdf_path)}.txt"
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(summary)
        print(f"完整结果已保存到: {output_file}")
        
        return {"success": True, "summary": summary, "time": processing_time}
        
    except Exception as e:
        import traceback
        print(f"测试失败: {str(e)}")
        traceback.print_exc()
        return {"success": False, "error": str(e)}
    
if __name__ == "__main__":
    asyncio.run(test_pdf_processing()) 