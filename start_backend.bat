@echo off
echo Starting Deepchat backend service...

:: Check if Python is installed
python --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Error: Python not found. Please install Python and try again.
    pause
    exit /b 1
)

:: Check and install dependencies
echo Checking and installing dependencies...
python -m pip install fastapi uvicorn aiohttp python-multipart

:: Create necessary directories
if not exist uploads mkdir uploads
if not exist runs mkdir runs
if not exist cache mkdir cache

:: Start backend service
echo Starting Deepchat backend service...
python backend.py

pause