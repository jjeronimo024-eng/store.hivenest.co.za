(() => {
    'use strict';
    const modal = document.getElementById('live-chat-modal');
    const startButton = document.getElementById('start-live-chat');
    if (!modal || !startButton) return;
    const startView = document.getElementById('live-chat-start-view');
    const conversation = document.getElementById('live-chat-conversation');
    const startForm = document.getElementById('live-chat-start-form');
    const compose = document.getElementById('live-chat-compose');
    const messages = document.getElementById('live-chat-messages');
    const status = document.getElementById('live-chat-status');
    const errorBox = document.getElementById('live-chat-error');
    const base = window.location.hostname.includes('holohive.co.za')
        ? 'https://hivenest.holohive.co.za' : `${window.location.protocol}//${window.location.host}`;
    let session = sessionStorage.getItem('hivenest_chat_session') || '';
    let token = sessionStorage.getItem('hivenest_chat_token') || '';
    let lastMessage = 0;
    let waitingSince = null;
    let pollTimer = null;
    let clockTimer = null;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));
    async function request(options = {}, query = '') {
        const response = await fetch(`${base}/api/chat${query}`, {
            credentials:'include',
            ...options,
            headers:{
                Accept:'application/json',
                ...(options.method === 'POST' ? {'Content-Type':'application/json'} : {}),
                ...(token ? {'X-Chat-Token':token} : {}),
            },
        });
        const text = await response.text();
        let data = {};
        try { data = text ? JSON.parse(text) : {}; } catch { throw new Error('Chat server returned an invalid response.'); }
        if (!response.ok) throw new Error(data.error || 'Chat request failed.');
        return data;
    }
    function open() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (session && token) {
            showConversation();
            poll();
        } else {
            startView.hidden = false;
            conversation.hidden = true;
        }
    }
    function closeWindow() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }
    function showConversation() {
        startView.hidden = true;
        conversation.hidden = false;
    }
    function updateClock() {
        if (!waitingSince || status.dataset.state !== 'waiting') return;
        const seconds = Math.max(0, Math.floor((Date.now() - waitingSince.getTime()) / 1000));
        const minutes = String(Math.floor(seconds / 60)).padStart(2,'0');
        const remainder = String(seconds % 60).padStart(2,'0');
        status.textContent = `Waiting for a support agent · ${minutes}:${remainder}`;
    }
    function render(items) {
        items.forEach(item => {
            const node = document.createElement('div');
            node.className = `live-chat-message ${item.actor_type}`;
            node.innerHTML = `${escapeHtml(item.message)}<small>${item.actor_type === 'admin' ? escapeHtml(item.agent_name || 'Support agent') : escapeHtml(item.actor_type)} · ${escapeHtml(item.created_at || '')}</small>`;
            messages.appendChild(node);
            lastMessage = Math.max(lastMessage, Number(item.id || 0));
        });
        if (items.length) messages.scrollTop = messages.scrollHeight;
    }
    async function poll() {
        if (!session || !token || modal.hidden) return;
        try {
            const data = await request({}, `?session=${encodeURIComponent(session)}&after=${lastMessage}`);
            render(Array.isArray(data.messages) ? data.messages : []);
            const state = data.chat?.status || 'waiting';
            status.dataset.state = state;
            if (state === 'waiting') {
                waitingSince = new Date(String(data.chat.waiting_since).replace(' ','T'));
                updateClock();
            } else if (state === 'active') {
                status.textContent = 'Connected to a HiveNest support agent';
            } else {
                status.textContent = 'This chat has ended. The transcript remains stored for support records.';
                compose.hidden = true;
                document.getElementById('live-chat-end').hidden = true;
            }
        } catch (error) {
            console.error('Live chat polling failed:', error);
            errorBox.textContent = error.message;
        }
        window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(poll, 3000);
    }
    startButton.addEventListener('click', event => { event.preventDefault(); open(); });
    modal.querySelector('[data-chat-close]').addEventListener('click', closeWindow);
    modal.addEventListener('click', event => { if (event.target === modal) closeWindow(); });
    startForm.addEventListener('submit', async event => {
        event.preventDefault();
        errorBox.textContent = '';
        const values = Object.fromEntries(new FormData(startForm).entries());
        const button = startForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const data = await request({
                method:'POST',
                body:JSON.stringify({...values,action:'start',page_url:window.location.href}),
            });
            session = data.session;
            token = data.token;
            sessionStorage.setItem('hivenest_chat_session',session);
            sessionStorage.setItem('hivenest_chat_token',token);
            showConversation();
            poll();
            clockTimer = window.setInterval(updateClock,1000);
        } catch (error) {
            console.error('Unable to start live chat:', error);
            errorBox.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
    compose.addEventListener('submit', async event => {
        event.preventDefault();
        const input = compose.elements.message;
        const message = input.value.trim();
        if (!message) return;
        input.disabled = true;
        try {
            await request({method:'POST',body:JSON.stringify({action:'message',session,message})});
            input.value = '';
            await poll();
        } catch (error) {
            console.error('Unable to send chat message:', error);
            errorBox.textContent = error.message;
        } finally {
            input.disabled = false;
            input.focus();
        }
    });
    document.getElementById('live-chat-end').addEventListener('click', async () => {
        try {
            await request({method:'POST',body:JSON.stringify({action:'close',session})});
            await poll();
        } catch (error) {
            console.error('Unable to close chat:', error);
            errorBox.textContent = error.message;
        }
    });
    document.getElementById('live-chat-new').addEventListener('click', () => {
        sessionStorage.removeItem('hivenest_chat_session');
        sessionStorage.removeItem('hivenest_chat_token');
        session = ''; token = ''; lastMessage = 0; messages.innerHTML = '';
        compose.hidden = false;
        document.getElementById('live-chat-end').hidden = false;
        startView.hidden = false; conversation.hidden = true; startForm.reset();
    });
    if (session && token) open();
    clockTimer = window.setInterval(updateClock,1000);
})();
