// <?php
// header("Content-Type: application/json");
// $data = json_decode(file_get_contents("php://input"), true);
// $user_message = $data['message'];
//
// // 替换为你的 DeepSeek API 密钥
// $api_key = "sk-c5f415578acb43a99871b38d273cafb7";
//
// // DeepSeek 的 API 地址（需根据文档确认）
// $deepseek_url = "https://api.deepseek.com";
//
// $post_fields = [
//     "model" => "deepseek-chat", // 模型名称需确认
//     "messages" => [
//         ["role" => "system", "content" => "You are a helpful assistant."],
//         ["role" => "user", "content" => $user_message]
//     ],
//     "temperature" => 0.7
// ];
//
// $ch = curl_init();
// curl_setopt($ch, CURLOPT_URL, $deepseek_url);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// curl_setopt($ch, CURLOPT_POST, 1);
//
// // 根据 DeepSeek 的鉴权方式调整 Header
// curl_setopt($ch, CURLOPT_HTTPHEADER, [
//     "Content-Type: application/json",
//     "Authorization: Bearer " . $api_key // 或 "API-Key: " . $api_key
// ]);
//
// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
//
// $response = curl_exec($ch);
// curl_close($ch);
//
// $result = json_decode($response, true);
//
// // 根据 DeepSeek 的响应结构调整字段
// $reply = $result['choices'][0]['message']['content'] ?? 'Sorry, something went wrong.';
//
// echo json_encode(['reply' => $reply]);

<?php
// 调用 Python 脚本的路径
$pythonScript = './runtxt.py';

$command = "python3 " . $pythonScript . " 2>&1"; // 2>&1 用于捕获错误信息
$output = shell_exec($command);

// 根据 Python 脚本的输出格式处理返回内容，例如如果返回的是纯文本摘要：
echo json_encode(['abstract' => $output]);
?>



