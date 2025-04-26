import asyncio
import sys
import os
import time
from Main.runtxt import LightPDFProcessor

async def test_pdf_processing():
    """Test PDF processing functionality"""
    # Get the path of the PDF file to process
    if len(sys.argv) > 1:
        pdf_path = sys.argv[1]
    else:
        # Try to find a PDF file in the uploads directory
        uploads_dir = "uploads"
        pdf_files = [f for f in os.listdir(uploads_dir) if f.endswith('.pdf')]
        if not pdf_files:
            print("Error: No PDF file found. Please specify the PDF file path as an argument or upload a PDF file.")
            return
        pdf_path = os.path.join(uploads_dir, pdf_files[0])
    
    print(f"Testing processing of PDF file: {pdf_path}")
    
    # Check if the file exists
    if not os.path.exists(pdf_path):
        print(f"Error: File {pdf_path} does not exist")
        return
    
    try:
        # Create processor instance
        start_time = time.time()
        print("Initializing LightPDFProcessor...")
        processor = LightPDFProcessor(pdf_path)
        print(f"Initialization took: {time.time() - start_time:.2f} seconds")
        
        # Generate summary
        print("Starting summary generation...")
        start_time = time.time()
        summary = await processor.generate_summary()
        processing_time = time.time() - start_time
        
        # Display results
        print(f"Processing completed in: {processing_time:.2f} seconds")
        print(f"Summary length: {len(summary)} characters")
        print("\nFirst 200 characters of summary:")
        print(summary[:200] + "...")
        
        # Save results
        output_file = f"test_result_{os.path.basename(pdf_path)}.txt"
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(summary)
        print(f"Full results saved to: {output_file}")
        
        return {"success": True, "summary": summary, "time": processing_time}
        
    except Exception as e:
        import traceback
        print(f"Test failed: {str(e)}")
        traceback.print_exc()
        return {"success": False, "error": str(e)}
    
if __name__ == "__main__":
    asyncio.run(test_pdf_processing())