document.addEventListener('DOMContentLoaded', () => {
    const chatBox = document.getElementById('chatBox');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const receiverIdInput = document.getElementById('receiverId');

    let isFirstLoad = true;
    let previousMessagesHash = '';

    // Function to render messages
    const renderMessages = (messages) => {
        if (!Array.isArray(messages)) return;

        chatBox.innerHTML = '';

        if (messages.length === 0) {
            chatBox.innerHTML = '<div class="empty-state">Belum ada percakapan. Mulai kirim pesan!</div>';
            return;
        }

        messages.forEach(msg => {
            const isSent = parseInt(msg.sender_id) === CURRENT_USER_ID;
            const bubble = document.createElement('div');
            bubble.classList.add('chat-bubble', isSent ? 'sent' : 'received');

            // Prevent XSS
            const safeText = document.createTextNode(msg.message);
            const textSpan = document.createElement('span');
            textSpan.appendChild(safeText);

            const timeSpan = document.createElement('span');
            timeSpan.classList.add('chat-time');
            timeSpan.textContent = msg.time;

            bubble.appendChild(textSpan);
            bubble.appendChild(timeSpan);
            chatBox.appendChild(bubble);
        });

        // Auto scroll to bottom
        chatBox.scrollTop = chatBox.scrollHeight;
    };

    // Function to fetch messages from API
    const fetchMessages = async () => {
        try {
            const response = await fetch(GET_MESSAGES_URL, { cache: 'no-store' });
            const result = await response.json();

            if (result.success) {
                const currentHash = JSON.stringify(result.data);
                // Update DOM only if data changed or first load
                if (currentHash !== previousMessagesHash || isFirstLoad) {
                    previousMessagesHash = currentHash;
                    renderMessages(result.data);
                    isFirstLoad = false;
                }
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    };

    // Send Message Handler
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const messageText = messageInput.value.trim();

        if (!messageText) return;

        sendBtn.disabled = true;
        messageInput.disabled = true;

        const formData = new FormData();
        formData.append('receiver_id', receiverIdInput.value);
        formData.append('message', messageText);

        try {
            const response = await fetch(SEND_MESSAGE_URL, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                messageInput.value = '';
                await fetchMessages();
            } else {
                alert(result.message || 'Gagal mengirim pesan');
            }
        } catch (error) {
            alert('Terjadi kesalahan jaringan.');
            console.error(error);
        } finally {
            sendBtn.disabled = false;
            messageInput.disabled = false;
            messageInput.focus();
        }
    });

    // Initial Load & Interval Polling (Every 2.5s)
    fetchMessages();
    setInterval(fetchMessages, 2500);
});