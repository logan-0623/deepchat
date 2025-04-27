#!/bin/bash

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo "Error: Python3 not found. Please install Python3 and try again."
    exit 1
fi

# Checking and installing dependencies
echo "Checking and installing dependencies..."
python3 -m pip install fastapi uvicorn aiohttp python-multipart
python3 -m pip install -r requirements.txt

# Ensure necessary directories exist
mkdir -p uploads runs cache

# Start Deepchat backend service
echo "Starting Deepchat backend service..."
python3 backend.py