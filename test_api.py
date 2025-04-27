import aiohttp
import asyncio

async def test_api():
    api_key = "sk-9535e904340c486ca1cb4a9ae8fd1fc0"  # Your API key
    api_base = "https://api.deepseek.com/v1"
    model = "deepseek-chat"
    
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}"
    }
    
    data = {
        "model": model,
        "messages": [{"role": "user", "content": "Hello, please briefly introduce yourself"}],
        "temperature": 0.7,
        "max_tokens": 1000
    }
    
    url = f"{api_base}/chat/completions"
    
    async with aiohttp.ClientSession() as session:
        async with session.post(url, headers=headers, json=data, ssl=False) as resp:
            print(f"Status code: {resp.status}")
            if resp.status == 200:
                result = await resp.json()
                response = result.get("choices", [{}])[0].get("message", {}).get("content", "")
                print(f"Reply: {response}")
            else:
                text = await resp.text()
                print(f"Error: {text}")

if __name__ == "__main__":
    asyncio.run(test_api())