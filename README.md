# DeepChat System Access and Installation Guide

## System Overview
DeepChat is an AI-powered chat application with both web interface and local deployment options. The system supports real-time conversations and PDF file processing.

## Access Methods

### 1. Web Version (Recommended)
The application is permanently hosted and accessible at:
```
https://mynamedeepchat.xyz/
```
This is the recommended way to access the system as it requires no local installation.

### 2. Local Installation
For local deployment, follow these steps:

#### Prerequisites
- Python 3.8+
- Conda package manager
- Git

#### Installation Steps
1. Clone the repository:
```bash
git clone https://github.com/your-repo/deepchat.git
cd deepchat
```

2. Create and activate the Conda environment:
```bash
conda create -n ai python=3.9
conda activate deepchat
```

3. Install dependencies:
```bash
pip install fastapi uvicorn aiohttp PyPDF2 python-multipart
```

4. Start the backend server:
```bash
python backend.py
```

5. Access the application:
- Open your web browser
- Navigate to: `http://localhost:8001/Main/Chat_Interface.php`

## System Architecture
```
/
├── backend.py        # Backend service entry
├── start_backend.sh  # Start script (Linux/Mac)
├── start_backend.bat # Start script (Windows)
├── aichat.sql       # Database schema
├── runs/            # Task result storage
├── uploads/         # Uploaded files storage
├── cache/           # Cache directory
├── Main/            # Frontend files
│   ├── Chat_Interface.php
│   └── Main Page.php
└── Background/      # Background images
```

## System Requirements
- Operating System: Windows 10+, macOS 10.15+, or Linux
- Memory: Minimum 4GB RAM
- Storage: Minimum 1GB free space
- Internet connection for API access

## Code Repository
The complete source code is available at:
```
https://github.com/your-repo/deepchat
```

## Support
For technical support or issues, please contact:
- GitHub Issues: https://github.com/your-repo/deepchat/issues 