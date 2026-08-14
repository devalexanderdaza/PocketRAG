document.addEventListener('DOMContentLoaded', () => {
    const messagesContainer = document.getElementById('messages-container');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    const newChatBtn = document.getElementById('new-chat-btn');
    const suggestionCards = document.querySelectorAll('.suggestion-card');
    const modeBadge = document.getElementById('mode-badge');
    const latencyInfo = document.getElementById('latency-info');

    let conversationHistory = [];

    // Auto-expand textarea
    chatInput.addEventListener('input', () => {
        chatInput.style.height = 'auto';
        chatInput.style.height = `${Math.min(chatInput.scrollHeight, 150)}px`;
    });

    // Send message on Enter (Shift+Enter for newline)
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    newChatBtn.addEventListener('click', () => {
        conversationHistory = [];
        messagesContainer.innerHTML = '';
        modeBadge.className = 'badge badge-hybrid';
        modeBadge.innerText = 'hybrid';
        latencyInfo.innerText = '0 ms';
        appendMessage('assistant', 'Hello! I am PocketRAG. How can I help you today regarding the knowledge base?');
    });

    suggestionCards.forEach(card => {
        card.addEventListener('click', () => {
            const prompt = card.getAttribute('data-prompt');
            if (prompt) {
                chatInput.value = prompt;
                sendMessage();
            }
        });
    });

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        // Render User Message
        appendMessage('user', text);
        chatInput.value = '';
        chatInput.style.height = 'auto';

        // Prepare History Payload
        const payload = {
            message: text,
            history: conversationHistory
        };

        // Render Loading Assistant Indicator
        const loadingId = appendLoadingIndicator();
        scrollToBottom();

        sendBtn.disabled = true;

        try {
            const response = await fetch('/index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            removeMessage(loadingId);

            if (response.ok && data) {
                // Update Conversation History
                conversationHistory.push({ role: 'user', content: text });
                conversationHistory.push({ role: 'assistant', content: data.reply });

                // Render Assistant Response with Metadata & Citations
                appendMessage('assistant', data.reply, data.sources, data.search_query);

                // Update Header Badges
                if (data.mode) {
                    modeBadge.className = `badge ${data.fallback_occurred ? 'badge-fallback' : 'badge-hybrid'}`;
                    modeBadge.innerText = data.mode;
                }

                if (data.duration_ms !== undefined) {
                    latencyInfo.innerText = `${data.duration_ms} ms`;
                }
            } else {
                appendMessage('assistant', `⚠️ Error: ${data.error || 'Could not retrieve response.'}`);
            }
        } catch (err) {
            removeMessage(loadingId);
            appendMessage('assistant', `⚠️ Connection error: ${err.message}`);
        } finally {
            sendBtn.disabled = false;
            scrollToBottom();
        }
    }

    function appendMessage(role, text, sources = [], searchQuery = null) {
        const msgRow = document.createElement('div');
        msgRow.className = `message-row ${role}`;

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = role === 'assistant' ? '⚡' : '👤';

        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'message-content-wrapper';

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.innerHTML = parseMarkdown(text);

        contentWrapper.appendChild(bubble);

        // Sources / Citations UI
        if (sources && sources.length > 0) {
            const sourcesContainer = document.createElement('div');
            sourcesContainer.className = 'sources-accordion';

            const header = document.createElement('div');
            header.className = 'sources-header';
            header.innerHTML = `📚 <span>${sources.length} cited source(s)</span> ${searchQuery ? `<small>(${searchQuery})</small>` : ''}`;

            const content = document.createElement('div');
            content.className = 'sources-content';

            sources.forEach(src => {
                const chip = document.createElement('div');
                chip.className = 'source-chip';
                const loc = [];
                if (src.file) loc.push(src.file);
                if (src.heading) loc.push(src.heading);
                const linePart = src.line ? `L${src.line}` : '';
                const ref = loc.length ? loc.join(' › ') + (linePart ? ` (${linePart})` : '') : '';
                chip.innerHTML = `📄 <strong>${escapeHtml(src.id)}</strong>`
                    + (ref ? ` <span class="source-ref">${escapeHtml(ref)}</span>` : '')
                    + ` <span class="source-score">${src.score ? src.score.toFixed(3) : ''}</span>`;
                content.appendChild(chip);
            });

            sourcesContainer.appendChild(header);
            sourcesContainer.appendChild(content);
            contentWrapper.appendChild(sourcesContainer);
        }

        msgRow.appendChild(avatar);
        msgRow.appendChild(contentWrapper);
        messagesContainer.appendChild(msgRow);

        scrollToBottom();
    }

    function appendLoadingIndicator() {
        const id = 'loading-' + Date.now();
        const msgRow = document.createElement('div');
        msgRow.className = 'message-row assistant';
        msgRow.id = id;

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = '⚡';

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.innerHTML = `<div class="typing-dots"><span></span><span></span><span></span></div>`;

        msgRow.appendChild(avatar);
        msgRow.appendChild(bubble);
        messagesContainer.appendChild(msgRow);

        return id;
    }

    function removeMessage(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Micro markdown parser safely formatted
    function parseMarkdown(str) {
        if (!str) return '';
        let escaped = escapeHtml(str);

        // Code blocks
        escaped = escaped.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        // Inline code
        escaped = escaped.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Bold
        escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        // Italic
        escaped = escaped.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        // Newlines to <br>
        escaped = escaped.replace(/\n/g, '<br>');

        return escaped;
    }

    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
});
