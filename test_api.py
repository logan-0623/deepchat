import aiohttp
import asyncio

async def test_api():
    api_key = ""  # 您的API密钥
    api_base = "https://api.deepseek.com/v1"
    model = "deepseek-chat"
    
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}"
    }
    
    data = {
        "model": model,
        "messages": [{"role": "user", "content": "你好，请简单介绍一下自己"}],
        "temperature": 0.7,
        "max_tokens": 1000
    }
    
    url = f"{api_base}/chat/completions"
    
    async with aiohttp.ClientSession() as session:
        async with session.post(url, headers=headers, json=data, ssl=False) as resp:
            print(f"状态码: {resp.status}")
            if resp.status == 200:
                result = await resp.json()
                response = result.get("choices", [{}])[0].get("message", {}).get("content", "")
                print(f"回复: {response}")
            else:
                text = await resp.text()
                print(f"错误: {text}")

if __name__ == "__main__":
    asyncio.run(test_api())