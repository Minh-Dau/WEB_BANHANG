<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Trợ lý AI Thương mại</title>

  <!-- Thêm Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <style>
    /* Giữ nguyên CSS của bạn */
    #chat-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 360px;
      height: 480px;
      border-radius: 16px;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      overflow: hidden;
      border: 1px solid #e0e0e0;
      transition: height 0.3s ease;
    }

    #chat-container.minimized {
      height: 50px;
    }

    #chat-header {
      background-color: rgb(0, 0, 0);
      color: white;
      padding: 12px 16px;
      font-weight: bold;
      font-size: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
    }

    #chat-messages {
      flex-grow: 1;
      padding: 16px;
      overflow-y: auto;
      background-color: #f9f9f9;
    }

    .message {
      margin-bottom: 12px;
      padding: 10px 14px;
      border-radius: 12px;
      max-width: 80%;
      font-size: 14px;
      line-height: 1.4;
      word-wrap: break-word;
    }

    .user-message {
      background-color: rgb(0, 0, 0);
      color: white;
      margin-left: auto;
      border-bottom-right-radius: 0;
    }

    .bot-message {
      background-color: #eeeeee;
      color: #333;
      margin-right: auto;
      border-bottom-left-radius: 0;
    }

    #chat-input-container {
      display: flex;
      border-top: 1px solid #ddd;
      padding: 10px;
      background-color: white;
    }

    #chat-input {
      flex: 1;
      padding: 10px 12px;
      border-radius: 20px;
      border: 1px solid #ccc;
      outline: none;
    }

    #send-btn {
      background-color: rgb(0, 123, 255);
      color: white;
      border: none;
      padding: 10px 16px;
      margin-left: 8px;
      border-radius: 20px;
      cursor: pointer;
    }

    #send-btn:hover {
      background-color: #0056b3;
    }

    .toggle-icon {
      font-size: 18px;
      user-select: none;
    }

    #chat-container.minimized #chat-messages,
    #chat-container.minimized #chat-input-container {
      display: none;
    }
  </style>
</head>
<body>
  <div id="chat-container">
    <div id="chat-header" onclick="toggleChat()">
      💬 Trợ lý AI
      <i class="bi bi-chevron-down toggle-icon" id="toggle-icon"></i>
    </div>
    <div id="chat-messages"></div>
    <div id="chat-input-container">
      <input type="text" id="chat-input" placeholder="Gõ tin nhắn..." />
      <button id="send-btn">Gửi</button>
    </div>
  </div>

  <script>
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    const chatContainer = document.getElementById('chat-container');
    const toggleIcon = document.getElementById('toggle-icon');

    // Đọc trạng thái từ localStorage khi load
    window.onload = () => {
      const isMinimized = localStorage.getItem('chatbox-minimized') === 'true';
      if (isMinimized) {
        chatContainer.classList.add('minimized');
        toggleIcon.className = 'bi bi-chevron-up toggle-icon';
      }
    };

    function toggleChat() {
      chatContainer.classList.toggle('minimized');
      const isMinimized = chatContainer.classList.contains('minimized');
      toggleIcon.className = isMinimized ? 'bi bi-chevron-up toggle-icon' : 'bi bi-chevron-down toggle-icon';
      localStorage.setItem('chatbox-minimized', isMinimized);
    }

    function addMessage(message, isUser) {
      const messageDiv = document.createElement('div');
      messageDiv.className = 'message ' + (isUser ? 'user-message' : 'bot-message');
      messageDiv.textContent = message;
      chatMessages.appendChild(messageDiv);
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function sendMessageToAI(message) {
      try {
        const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer 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',
            'HTTP-Referer': window.location.origin,
            'X-Title': 'E-commerce Chatbot'
          },
          body: JSON.stringify({
            model: 'meta-llama/llama-3.1-8b-instruct:free',
            messages: [
              { role: 'system', content: 'Bạn là một trợ lý hỗ trợ khách hàng cho trang web thương mại điện tử. Trả lời thân thiện và hữu ích.' },
              { role: 'user', content: message }
            ]
          })
        });

        const data = await response.json();
        return data.choices[0]?.message?.content || 'Xin lỗi, tôi không hiểu câu hỏi của bạn.';
      } catch (error) {
        console.error('Lỗi khi gọi API:', error);
        return 'Có lỗi xảy ra, vui lòng thử lại sau.';
      }
    }

    async function handleSend() {
      const userMessage = chatInput.value.trim();
      if (!userMessage) return;
      addMessage(userMessage, true);
      chatInput.value = '';

      const botReply = await sendMessageToAI(userMessage);
      addMessage(botReply, false);
    }

    sendBtn.addEventListener('click', handleSend);
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') handleSend();
    });
  </script>
</body>
</html>
