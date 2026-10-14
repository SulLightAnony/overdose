/**
 * ====================================================================================
 * MODULE: Global Page Logic (Live Chat & Global News)
 * FILE LOCATION: public/js/modules/global.js
 * ====================================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    const API_URL = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'api/global';
    const defaultAvatar = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'public/assets/img/logo.png';
    const currentUserId = typeof CURRENT_USER_ID !== 'undefined' ? Number(CURRENT_USER_ID) : 0;
    const currentUserRole = typeof CURRENT_USER_ROLE !== 'undefined' ? CURRENT_USER_ROLE : 'Keroco';
    const isAdmin = ['Sepuh', 'Primordial'].includes(currentUserRole);

    // Chat DOM Elements
    const chatMessages    = document.getElementById('chat-messages');
    const chatForm        = document.getElementById('chat-form');
    const chatInput       = document.getElementById('chat-input');
    const btnSendChat     = document.getElementById('btnSendChat');
    const btnSendChatIcon = document.getElementById('btnSendChatIcon');
    const btnCancelEdit   = document.getElementById('btnCancelEdit');
    const replyPreviewBar = document.getElementById('replyPreviewBar');
    const replyPreviewSender = document.getElementById('replyPreviewSender');
    const replyPreviewText = document.getElementById('replyPreviewText');
    const btnCancelReply  = document.getElementById('btnCancelReply');
    const globalAlert     = document.getElementById('globalAlert');

    // News DOM Elements
    const newsContainer      = document.getElementById('news-container');
    const openGlobalNewsForm  = document.getElementById('openGlobalNewsForm');
    const globalNewsModalEl  = document.getElementById('globalNewsModal');
    const globalNewsForm     = document.getElementById('globalNewsForm');
    const globalNewsModal    = globalNewsModalEl ? bootstrap.Modal.getOrCreateInstance(globalNewsModalEl) : null;

    // Chat State Tracking
    const chatCache = new Map();
    let oldestChatId = 0;
    let newestChatId = 0;
    let isFetchingOlder = false;
    let hasMoreOlderChats = true;
    let initialChatsLoaded = false;
    let lastRenderedUserId = null;
    let replyingToChat = null;
    let editingChatId = null;

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function notify(message, type = 'success') {
        if (window.appToast) {
            window.appToast(message, type);
        } else if (globalAlert) {
            globalAlert.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm mb-4" role="alert">
                ${escapeHTML(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    }

    function showCopyToast() {
        const toastEl = document.getElementById('copyToast');
        if (toastEl) {
            const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 });
            toast.show();
        } else {
            notify('Teks berhasil disalin', 'success');
        }
    }

    function copyMessageText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(showCopyToast).catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showCopyToast();
        } catch (e) {
            notify('Gagal menyalin teks.', 'danger');
        }
    }

    function renderRoleBadge(role) {
        if (!role) return '';
        const lower = role.toLowerCase();
        if (lower === 'primordial') {
            return `<span class="badge" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.6rem; font-weight: 600; width: fit-content;">Primordial</span>`;
        }
        if (lower === 'sepuh') {
            return `<span class="badge bg-secondary text-white" style="font-size: 0.6rem; width: fit-content;">Sepuh</span>`;
        }
        if (lower === 'keroco') {
            return `<span class="text-muted" style="font-size: 0.68rem; width: fit-content;">Keroco</span>`;
        }
        return '';
    }

    function isScrolledToBottom() {
        if (!chatMessages) return true;
        return (chatMessages.scrollHeight - chatMessages.clientHeight) <= (chatMessages.scrollTop + 80);
    }

    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // ------------------------------------------------------------------
    // Dynamic Textarea Auto-Expand (Max 3 lines ~ 80px)
    // ------------------------------------------------------------------
    if (chatInput) {
        chatInput.addEventListener('input', function () {
            this.style.height = 'auto';
            const newHeight = Math.min(this.scrollHeight, 80);
            this.style.height = newHeight + 'px';
            this.style.overflowY = this.scrollHeight > 80 ? 'auto' : 'hidden';
        });

        chatInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (chatForm) {
                    chatForm.requestSubmit();
                }
            }
        });
    }

    // ------------------------------------------------------------------
    // Reply & Edit Mode Management
    // ------------------------------------------------------------------
    function setReplyMode(chat) {
        if (editingChatId !== null) {
            cancelEditMode();
        }
        replyingToChat = chat;
        if (replyPreviewBar && replyPreviewSender && replyPreviewText) {
            replyPreviewSender.textContent = chat.user_name || 'Pengguna';
            const cleanMsg = (chat.message || '').replace(/\s+/g, ' ');
            replyPreviewText.textContent = cleanMsg.length > 55 ? cleanMsg.substring(0, 55) + '...' : cleanMsg;
            replyPreviewBar.classList.remove('d-none');
            replyPreviewBar.classList.add('d-flex');
        }
        if (chatInput) {
            chatInput.focus();
        }
    }

    function cancelReplyMode() {
        replyingToChat = null;
        if (replyPreviewBar) {
            replyPreviewBar.classList.add('d-none');
            replyPreviewBar.classList.remove('d-flex');
        }
        if (chatInput) {
            chatInput.focus();
        }
    }

    if (btnCancelReply) {
        btnCancelReply.addEventListener('click', cancelReplyMode);
    }

    function setEditMode(chat) {
        if (replyingToChat !== null) {
            cancelReplyMode();
        }
        cancelEditMode();

        editingChatId = Number(chat.id);
        if (chatInput) {
            chatInput.value = chat.message || '';
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 80) + 'px';
            chatInput.focus();
        }

        const row = chatMessages ? chatMessages.querySelector(`.chat-row[data-chat-id="${chat.id}"]`) : null;
        if (row) {
            const b = row.querySelector('.chat-bubble');
            if (b) b.classList.add('chat-bubble-editing');
        }

        if (btnCancelEdit) {
            btnCancelEdit.classList.remove('d-none');
            btnCancelEdit.classList.add('d-flex');
        }
        if (btnSendChatIcon) {
            btnSendChatIcon.className = 'bi bi-pencil-fill fs-6';
        }
        if (btnSendChat) {
            btnSendChat.title = 'Simpan Perubahan';
        }
    }

    function cancelEditMode() {
        editingChatId = null;
        if (chatInput) {
            chatInput.value = '';
            chatInput.style.height = 'auto';
        }
        if (chatMessages) {
            chatMessages.querySelectorAll('.chat-bubble-editing').forEach(el => el.classList.remove('chat-bubble-editing'));
        }
        if (btnCancelEdit) {
            btnCancelEdit.classList.add('d-none');
            btnCancelEdit.classList.remove('d-flex');
        }
        if (btnSendChatIcon) {
            btnSendChatIcon.className = 'bi bi-send-fill fs-6';
        }
        if (btnSendChat) {
            btnSendChat.title = 'Kirim Pesan';
        }
        if (chatInput) {
            chatInput.focus();
        }
    }

    if (btnCancelEdit) {
        btnCancelEdit.addEventListener('click', cancelEditMode);
    }

    // ------------------------------------------------------------------
    // Chat Rendering Logic (WhatsApp Style Grouping)
    // ------------------------------------------------------------------
    function createChatBubbleElement(chat, isGroupedHeader) {
        if (isGroupedHeader === undefined) isGroupedHeader = false;

        chatCache.set(Number(chat.id), chat);

        const isMe      = Number(chat.user_id) === currentUserId;
        const canDel    = isMe || isAdmin;
        const avatarSrc = chat.user_avatar || defaultAvatar;
        const userName  = escapeHTML(chat.user_name || 'Pengguna');
        const time      = chat.created_at
            ? new Date(chat.created_at.replace(/-/g, '/')).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            : '';

        const bubbleBg  = isMe ? 'bg-primary text-white' : 'bg-white text-dark shadow-sm border';
        const timeColor = isMe ? 'text-white-50' : 'text-muted';

        // ── Wrapper ──────────────────────────────────────────────────────
        const wrapper = document.createElement('div');
        wrapper.dataset.chatId = chat.id;
        wrapper.dataset.userId = chat.user_id;
        wrapper.className = 'd-flex chat-row'
            + (isMe ? ' justify-content-end chat-outgoing' : ' chat-incoming')
            + (isGroupedHeader ? ' mt-2' : ' mt-1');

        // ── Quote Box for Replied Message ────────────────────────────────
        const quoteBoxHtml = (chat.reply_to_id && (chat.reply_message || chat.reply_user_name))
            ? '<div class="chat-quote-box text-start" data-target-id="' + escapeHTML(String(chat.reply_to_id)) + '">'
                + '<div class="fw-bold ' + (isMe ? 'text-white' : 'text-primary') + '" style="font-size:0.75rem;">' + escapeHTML(chat.reply_user_name || 'Pengguna') + '</div>'
                + '<div class="text-truncate ' + (isMe ? 'text-white-50' : 'text-secondary') + '" style="max-width:260px; font-size:0.72rem;">' + escapeHTML(chat.reply_message || 'Pesan rujukan') + '</div>'
                + '</div>'
            : (chat.reply_to_id
                ? '<div class="chat-quote-box text-start" data-target-id="' + escapeHTML(String(chat.reply_to_id)) + '"><div class="text-muted fst-italic" style="font-size:0.72rem;">Pesan rujukan telah dihapus</div></div>'
                : '');

        // ── Edited Indicator ─────────────────────────────────────────────
        const editedHtml = Number(chat.is_edited) === 1
            ? '<span class="' + timeColor + ' chat-edited-indicator me-1" style="font-size:0.65rem;">(diedit)</span>'
            : '';

        // ── Options dropdown HTML ────────────────────────────────────────
        const dropSide = isMe ? 'dropdown-menu-end' : 'dropdown-menu-start';
        const optHtml = '<div class="dropdown chat-options-dropdown">'
            + '<button type="button" class="btn btn-link btn-sm p-0 text-secondary border-0 dropdown-toggle no-arrow shadow-none" data-bs-toggle="dropdown" aria-expanded="false" title="Opsi pesan">'
            + '<i class="bi bi-three-dots-vertical" style="font-size:1.05rem;"></i></button>'
            + '<ul class="dropdown-menu ' + dropSide + ' chat-options-menu shadow border-0 rounded-3 text-start py-1" style="z-index:1060;">'
            + '<li><button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-trigger-reply-chat" data-chat-id="' + escapeHTML(String(chat.id)) + '">'
            + '<i class="bi bi-reply-fill text-primary"></i> Balas</button></li>'
            + '<li><button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-trigger-copy-chat" data-chat-id="' + escapeHTML(String(chat.id)) + '">'
            + '<i class="bi bi-clipboard text-secondary"></i> Salin</button></li>'
            + (isMe ? '<li><button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-trigger-edit-chat" data-chat-id="' + escapeHTML(String(chat.id)) + '"><i class="bi bi-pencil-square text-warning"></i> Edit</button></li>' : '')
            + (canDel ? '<li><button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 btn-trigger-delete-chat" data-chat-id="' + escapeHTML(String(chat.id)) + '"><i class="bi bi-trash"></i> Hapus</button></li>' : '')
            + '</ul></div>';

        // ── INCOMING (left) ──────────────────────────────────────────────
        if (!isMe) {
            const sideCol = document.createElement('div');
            sideCol.style.cssText = 'display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; flex-shrink:0; width:45px; min-width:45px; margin-right:8px;';

            if (isGroupedHeader) {
                const img = document.createElement('img');
                img.alt = 'Avatar';
                img.className = 'rounded-circle border d-block';
                img.style.cssText = 'width:45px;height:45px;object-fit:cover;';
                img.setAttribute('referrerpolicy', 'no-referrer');
                img.onerror = function () { this.onerror = null; this.src = defaultAvatar; };
                img.src = avatarSrc;
                sideCol.appendChild(img);
                sideCol.innerHTML += renderRoleBadge(chat.user_role);
            }
            wrapper.appendChild(sideCol);

            const nameBadgeHtml = isGroupedHeader
                ? '<div style="margin-bottom:3px;line-height:1.2;">'
                    + '<span class="fw-bold d-block" style="font-size:0.8rem;color:#0d6efd;">' + userName + '</span>'
                    + '</div>'
                : '';

            const bubbleCol = document.createElement('div');
            bubbleCol.className = 'min-w-0 group-chat-item';
            bubbleCol.style.cssText = 'max-width:calc(100% - 60px);';
            bubbleCol.innerHTML = '<div class="d-flex align-items-center">'
                + optHtml
                + '<div class="chat-bubble py-2 px-3 rounded-4 ' + bubbleBg + ' text-break" style="font-size:0.875rem;line-height:1.5;white-space:pre-wrap;">'
                + quoteBoxHtml
                + nameBadgeHtml
                + '<div class="chat-message-text">' + escapeHTML(chat.message) + '</div>'
                + '<div class="chat-meta-info ' + timeColor + ' d-flex justify-content-end align-items-center mt-1" style="font-size:0.65rem;line-height:1;opacity:0.85;">'
                + editedHtml
                + '<span class="chat-time" style="font-size:0.65rem;">' + escapeHTML(time) + '</span>'
                + '</div>'
                + '</div>'
                + '</div>';

            wrapper.appendChild(bubbleCol);

        // ── OUTGOING (right) ─────────────────────────────────────────────
        } else {
            const nameBadgeHtml = isGroupedHeader
                ? '<div style="margin-bottom:3px;line-height:1.2; display:flex; justify-content:flex-end; align-items:flex-end; flex-direction:column; gap:5px;">'
                    + '<span class="fw-bold d-block" style="font-size:0.8rem;color:white;">' + userName + '</span>'
                    + '</div>'
                : '';

            const bubbleCol = document.createElement('div');
            bubbleCol.className = 'min-w-0 group-chat-item d-flex flex-column align-items-end';
            bubbleCol.style.cssText = 'max-width:calc(100% - 60px);';
            bubbleCol.innerHTML = '<div class="d-flex align-items-center">'
                + '<div class="chat-bubble py-2 px-3 rounded-4 ' + bubbleBg + ' text-break" style="font-size:0.875rem;line-height:1.5;white-space:pre-wrap;">'
                + quoteBoxHtml
                + nameBadgeHtml
                + '<div class="chat-message-text">' + escapeHTML(chat.message) + '</div>'
                + '<div class="chat-meta-info ' + timeColor + ' d-flex justify-content-end align-items-center mt-1" style="font-size:0.65rem;line-height:1;opacity:0.85;">'
                + editedHtml
                + '<span class="chat-time" style="font-size:0.65rem;">' + escapeHTML(time) + '</span>'
                + '</div>'
                + '</div>'
                + optHtml
                + '</div>';

            wrapper.appendChild(bubbleCol);

            const sideCol = document.createElement('div');
            sideCol.style.cssText = 'display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; flex-shrink:0; width:45px; min-width:45px; margin-left:8px;';

            if (isGroupedHeader) {
                const img = document.createElement('img');
                img.alt = 'Avatar';
                img.className = 'rounded-circle border d-block';
                img.style.cssText = 'width:45px;height:45px;object-fit:cover;';
                img.setAttribute('referrerpolicy', 'no-referrer');
                img.onerror = function () { this.onerror = null; this.src = defaultAvatar; };
                img.src = avatarSrc;
                sideCol.appendChild(img);
                sideCol.innerHTML += renderRoleBadge(chat.user_role);
            }
            wrapper.appendChild(sideCol);
        }

        return wrapper;
    }

    // Append single new message (Live / Polling)
    function appendNewChat(chat) {
        if (!chatMessages) return;

        chatCache.set(Number(chat.id), chat);

        const placeholder = document.getElementById('noChatsPlaceholder');
        if (placeholder) placeholder.remove();

        const isGroupedHeader = (lastRenderedUserId !== Number(chat.user_id));
        lastRenderedUserId = Number(chat.user_id);

        const elem = createChatBubbleElement(chat, isGroupedHeader);
        chatMessages.appendChild(elem);
    }

    // Re-render whole chat list (for initial load & lazy load prepending)
    function renderChatList(chats, appendAtEnd = true) {
        if (!chatMessages || !Array.isArray(chats) || chats.length === 0) return;

        const placeholder = document.getElementById('noChatsPlaceholder');
        if (placeholder) placeholder.remove();

        const fragment = document.createDocumentFragment();

        chats.forEach(chat => {
            chatCache.set(Number(chat.id), chat);

            const isGroupedHeader = (lastRenderedUserId !== Number(chat.user_id));
            lastRenderedUserId = Number(chat.user_id);

            const elem = createChatBubbleElement(chat, isGroupedHeader);
            fragment.appendChild(elem);

            const idNum = Number(chat.id);
            if (oldestChatId === 0 || idNum < oldestChatId) oldestChatId = idNum;
            if (idNum > newestChatId) newestChatId = idNum;
        });

        if (appendAtEnd) {
            chatMessages.appendChild(fragment);
        } else {
            chatMessages.insertBefore(fragment, chatMessages.firstChild);
        }
    }

    // ------------------------------------------------------------------
    // Initial Load & Lazy Load (Scroll Up for Older Chats)
    // ------------------------------------------------------------------
    async function loadInitialChats() {
        if (!chatMessages) return;
        try {
            const response = await fetch(`${API_URL}?action=get_chats&limit=50`);
            const result   = await response.json();

            if (result.success && Array.isArray(result.data)) {
                chatMessages.innerHTML = '';
                if (result.data.length > 0) {
                    lastRenderedUserId = null;
                    renderChatList(result.data, true);
                    scrollToBottom();
                    if (result.data.length < 50) {
                        hasMoreOlderChats = false;
                    }
                } else {
                    chatMessages.innerHTML = '<div class="text-center text-muted py-5 small" id="noChatsPlaceholder">Belum ada obrolan. Mulai obrolan pertama kamu!</div>';
                    hasMoreOlderChats = false;
                }
                initialChatsLoaded = true;
            }
        } catch (error) {
            console.error('Gagal memuat obrolan awal:', error);
            chatMessages.innerHTML = '<div class="text-center text-muted py-4 small">Terjadi kesalahan saat memuat obrolan.</div>';
        }
    }

    async function loadOlderChats() {
        if (isFetchingOlder || !hasMoreOlderChats || oldestChatId <= 0 || !chatMessages) return;

        isFetchingOlder = true;

        let topLoader = document.getElementById('chatTopLoader');
        if (!topLoader) {
            topLoader = document.createElement('div');
            topLoader.id = 'chatTopLoader';
            topLoader.className = 'text-center text-muted py-2 small';
            topLoader.innerHTML = '<div class="spinner-border spinner-border-sm text-success me-1" role="status"></div> Memuat obrolan terdahulu...';
            chatMessages.insertBefore(topLoader, chatMessages.firstChild);
        }

        const oldScrollHeight = chatMessages.scrollHeight;

        try {
            const response = await fetch(`${API_URL}?action=get_chats&before_id=${oldestChatId}&limit=50`);
            const result   = await response.json();

            if (topLoader) topLoader.remove();

            if (result.success && Array.isArray(result.data)) {
                if (result.data.length === 0) {
                    hasMoreOlderChats = false;
                } else {
                    if (result.data.length < 50) {
                        hasMoreOlderChats = false;
                    }

                    // Prepend older chats while preserving scroll position
                    const fragment = document.createDocumentFragment();
                    let prevUserId = null;

                    result.data.forEach(chat => {
                        chatCache.set(Number(chat.id), chat);
                        const isGroupedHeader = (prevUserId !== Number(chat.user_id));
                        prevUserId = Number(chat.user_id);
                        const elem = createChatBubbleElement(chat, isGroupedHeader);
                        fragment.appendChild(elem);

                        const idNum = Number(chat.id);
                        if (oldestChatId === 0 || idNum < oldestChatId) oldestChatId = idNum;
                    });

                    chatMessages.insertBefore(fragment, chatMessages.firstChild);

                    const newScrollHeight = chatMessages.scrollHeight;
                    chatMessages.scrollTop = newScrollHeight - oldScrollHeight;
                }
            }
        } catch (error) {
            console.error('Gagal memuat obrolan lama:', error);
            if (topLoader) topLoader.remove();
        } finally {
            isFetchingOlder = false;
        }
    }

    // Scroll Up Listener for Lazy Load
    if (chatMessages) {
        chatMessages.addEventListener('scroll', () => {
            if (chatMessages.scrollTop === 0 && !isFetchingOlder && hasMoreOlderChats && initialChatsLoaded) {
                loadOlderChats();
            }
        });
    }

    // ------------------------------------------------------------------
    // Live Polling (New Messages)
    // ------------------------------------------------------------------
    async function pollNewChats() {
        if (!chatMessages || !initialChatsLoaded) return;
        try {
            const response = await fetch(`${API_URL}?action=get_chats&last_id=${newestChatId}&limit=50`);
            const result   = await response.json();

            if (result.success && Array.isArray(result.data) && result.data.length > 0) {
                const autoScroll = isScrolledToBottom();

                result.data.forEach(chat => {
                    const idNum = Number(chat.id);
                    if (idNum > newestChatId) {
                        appendNewChat(chat);
                        newestChatId = idNum;
                    }
                });

                if (autoScroll) {
                    scrollToBottom();
                }
            }
        } catch (error) {
            console.error('Polling chat error:', error);
        }
    }

    // ------------------------------------------------------------------
    // Send / Edit Chat Form Submission
    // ------------------------------------------------------------------
    if (chatForm && chatInput) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = chatInput.value.trim();
            if (!message) return;

            chatInput.disabled = true;
            if (btnSendChat) btnSendChat.disabled = true;

            try {
                if (editingChatId !== null) {
                    // Action: edit_chat
                    const response = await fetch(`${API_URL}?action=edit_chat`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ chat_id: editingChatId, message: message })
                    });
                    const result = await response.json();

                    if (result.success && result.data) {
                        const updated = result.data;
                        chatCache.set(Number(updated.id), updated);

                        const row = chatMessages.querySelector(`.chat-row[data-chat-id="${updated.id}"]`);
                        if (row) {
                            const msgSpan = row.querySelector('.chat-message-text');
                            if (msgSpan) msgSpan.textContent = updated.message;

                            const metaContainer = row.querySelector('.chat-meta-info');
                            if (metaContainer && !metaContainer.querySelector('.chat-edited-indicator')) {
                                const isMe = Number(updated.user_id) === currentUserId;
                                const timeColor = isMe ? 'text-white-50' : 'text-muted';
                                const ind = document.createElement('span');
                                ind.className = timeColor + ' chat-edited-indicator me-1';
                                ind.style.fontSize = '0.65rem';
                                ind.textContent = '(diedit)';
                                metaContainer.insertBefore(ind, metaContainer.firstChild);
                            } else if (!metaContainer) {
                                const bubble = row.querySelector('.chat-bubble');
                                if (bubble && !bubble.querySelector('.chat-edited-indicator')) {
                                    const isMe = Number(updated.user_id) === currentUserId;
                                    const timeColor = isMe ? 'text-white-50' : 'text-muted';
                                    const ind = document.createElement('span');
                                    ind.className = timeColor + ' chat-edited-indicator me-1';
                                    ind.style.fontSize = '0.65rem';
                                    ind.textContent = '(diedit)';
                                    bubble.appendChild(ind);
                                }
                            }
                        }

                        notify('Pesan berhasil diperbarui.', 'success');
                        cancelEditMode();
                    } else {
                        notify(result.message || 'Gagal memperbarui pesan.', 'danger');
                    }
                } else {
                    // Action: send_chat
                    const payload = { message: message };
                    if (replyingToChat && replyingToChat.id) {
                        payload.reply_to_id = replyingToChat.id;
                    }

                    const response = await fetch(`${API_URL}?action=send_chat`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const result = await response.json();

                    if (result.success && result.data) {
                        chatInput.value = '';
                        chatInput.style.height = 'auto';
                        if (replyingToChat) {
                            cancelReplyMode();
                        }
                        appendNewChat(result.data);
                        newestChatId = Math.max(newestChatId, Number(result.data.id));
                        scrollToBottom();
                    } else {
                        notify(result.message || 'Gagal mengirim pesan.', 'danger');
                    }
                }
            } catch (error) {
                console.error('Gagal mengirim/mengedit pesan:', error);
                notify('Terjadi kesalahan saat memproses pesan.', 'danger');
            } finally {
                chatInput.disabled = false;
                if (btnSendChat) btnSendChat.disabled = false;
                chatInput.focus();
            }
        });
    }

    // ------------------------------------------------------------------
    // Chat Context Menu & Quote Box Click Delegations
    // ------------------------------------------------------------------
    let pendingDeleteChatId  = null;
    let pendingDeleteChatRow = null;
    const deleteModalEl      = document.getElementById('deleteChatConfirmModal');
    const deleteModal        = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;
    const btnConfirmDelete   = document.getElementById('btnConfirmDeleteChat');

    if (chatMessages) {
        chatMessages.addEventListener('click', (e) => {
            // 1. Reply Option Click
            const replyBtn = e.target.closest('.btn-trigger-reply-chat');
            if (replyBtn) {
                const id = Number(replyBtn.dataset.chatId);
                const chat = chatCache.get(id);
                if (chat) setReplyMode(chat);
                return;
            }

            // 2. Copy Option Click
            const copyBtn = e.target.closest('.btn-trigger-copy-chat');
            if (copyBtn) {
                const id = Number(copyBtn.dataset.chatId);
                const chat = chatCache.get(id);
                if (chat && chat.message) {
                    copyMessageText(chat.message);
                }
                return;
            }

            // 3. Edit Option Click
            const editBtn = e.target.closest('.btn-trigger-edit-chat');
            if (editBtn) {
                const id = Number(editBtn.dataset.chatId);
                const chat = chatCache.get(id);
                if (chat) setEditMode(chat);
                return;
            }

            // 4. Quote Box Click (Smooth Scroll & Flash Target)
            const quoteBox = e.target.closest('.chat-quote-box');
            if (quoteBox) {
                const targetId = quoteBox.dataset.targetId;
                if (targetId) {
                    const targetRow = chatMessages.querySelector(`.chat-row[data-chat-id="${targetId}"]`);
                    if (targetRow) {
                        const targetBubble = targetRow.querySelector('.chat-bubble') || targetRow;
                        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        targetBubble.classList.remove('chat-target-highlight');
                        void targetBubble.offsetWidth;
                        targetBubble.classList.add('chat-target-highlight');
                        setTimeout(() => {
                            targetBubble.classList.remove('chat-target-highlight');
                        }, 1600);
                    } else {
                        notify('Pesan rujukan berada di riwayat obrolan terdahulu.', 'info');
                    }
                }
                return;
            }

            // 5. Delete Option Click (Trigger Modal Confirmation)
            const delBtn = e.target.closest('.btn-trigger-delete-chat');
            if (delBtn) {
                pendingDeleteChatId  = delBtn.dataset.chatId;
                pendingDeleteChatRow = delBtn.closest('.chat-row');
                if (pendingDeleteChatId && deleteModal) {
                    deleteModal.show();
                }
                return;
            }
        });
    }

    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            if (!pendingDeleteChatId) return;

            btnConfirmDelete.disabled = true;
            try {
                const response = await fetch(`${API_URL}?action=delete_chat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ chat_id: pendingDeleteChatId, id: pendingDeleteChatId })
                });
                const result = await response.json();

                if (result.success) {
                    notify('Pesan berhasil dihapus.', 'success');
                    if (pendingDeleteChatRow) pendingDeleteChatRow.remove();
                    chatCache.delete(Number(pendingDeleteChatId));
                } else {
                    notify(result.message || 'Gagal menghapus pesan.', 'danger');
                }
            } catch (error) {
                notify('Terjadi kesalahan saat menghapus pesan.', 'danger');
            } finally {
                btnConfirmDelete.disabled = false;
                if (deleteModal) deleteModal.hide();
                pendingDeleteChatId  = null;
                pendingDeleteChatRow = null;
            }
        });
    }

    // ------------------------------------------------------------------
    // GLOBAL NEWS MODULE (CRUD for global_news table with Drive upload)
    // ------------------------------------------------------------------
    const globalNewsImageInput = document.getElementById('globalNewsImageInput');
    const globalNewsImagePreviewContainer = document.getElementById('globalNewsImagePreviewContainer');
    const globalNewsImagePreviewImg = document.getElementById('globalNewsImagePreviewImg');
    const globalNewsImagePreviewName = document.getElementById('globalNewsImagePreviewName');
    const globalNewsImagePreviewSize = document.getElementById('globalNewsImagePreviewSize');
    const btnRemoveGlobalNewsImage = document.getElementById('btnRemoveGlobalNewsImage');
    const globalNewsRemoveImage = document.getElementById('globalNewsRemoveImage');

    const imagePreviewModalEl = document.getElementById('imagePreviewModal');
    const imagePreviewModal = imagePreviewModalEl ? bootstrap.Modal.getOrCreateInstance(imagePreviewModalEl) : null;
    const modalImg = document.getElementById('imagePreviewImg');
    const modalImgTitle = document.getElementById('imagePreviewTitle');

    function getThumbnailUrl(imageUrl, width = 450) {
        if (!imageUrl) return '';
        if (imageUrl.includes('drive.google.com/thumbnail')) {
            if (/sz=[ws]\d+/i.test(imageUrl)) {
                return imageUrl.replace(/sz=[ws]\d+/i, `sz=w${width}`);
            }
            return imageUrl + `&sz=w${width}`;
        }
        if (imageUrl.includes('lh3.googleusercontent.com/d/')) {
            return imageUrl.replace(/=[ws]\d+/i, '') + `=w${width}`;
        }
        return imageUrl;
    }

    function getFullImageUrl(imageUrl) {
        if (!imageUrl) return '';
        if (imageUrl.includes('drive.google.com/thumbnail')) {
            if (/sz=[ws]\d+/i.test(imageUrl)) {
                return imageUrl.replace(/sz=[ws]\d+/i, 'sz=w1600');
            }
            return imageUrl + '&sz=w1600';
        }
        return imageUrl;
    }

    function initLazyImages(container = document) {
        const lazyImages = container.querySelectorAll('img.news-lazy-img[data-src]');
        if (!lazyImages.length) return;

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.getAttribute('data-src');
                        if (src) {
                            img.src = src;
                            img.removeAttribute('data-src');
                            img.onload = () => {
                                img.style.opacity = '1';
                                const placeholder = img.parentElement?.querySelector('.news-image-placeholder');
                                if (placeholder) placeholder.remove();
                            };
                            img.onerror = () => {
                                const placeholder = img.parentElement?.querySelector('.news-image-placeholder');
                                if (placeholder) {
                                    placeholder.style.animation = 'none';
                                    placeholder.innerHTML = '<span class="small text-muted opacity-50"><i class="bi bi-exclamation-circle me-1"></i>Gagal memuat</span>';
                                }
                            };
                        }
                        obs.unobserve(img);
                    }
                });
            }, {
                rootMargin: '100px 0px',
                threshold: 0.01
            });

            lazyImages.forEach(img => observer.observe(img));
        } else {
            lazyImages.forEach(img => {
                img.src = img.getAttribute('data-src');
                img.style.opacity = '1';
                const placeholder = img.parentElement?.querySelector('.news-image-placeholder');
                if (placeholder) placeholder.remove();
            });
        }
    }

    function resetGlobalNewsForm() {
        if (!globalNewsForm) return;
        globalNewsForm.reset();
        document.getElementById('globalNewsId').value = '';
        document.getElementById('globalNewsModalTitle').textContent = 'Tulis Berita Global';
        document.getElementById('submitGlobalNews').innerHTML = 'Terbitkan';
        if (globalNewsRemoveImage) globalNewsRemoveImage.value = '0';
        if (globalNewsImagePreviewContainer) {
            globalNewsImagePreviewContainer.classList.add('d-none');
            globalNewsImagePreviewContainer.classList.remove('d-flex');
        }
        if (globalNewsImagePreviewImg) globalNewsImagePreviewImg.src = '';
    }

    if (globalNewsImageInput) {
        globalNewsImageInput.addEventListener('change', () => {
            const file = globalNewsImageInput.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                notify('Hanya berkas gambar (JPG, PNG, WEBP) yang diizinkan.', 'danger');
                globalNewsImageInput.value = '';
                return;
            }

            if (file.size > 1024 * 1024) {
                notify('Ukuran gambar tidak boleh melebihi 1 MB.', 'danger');
                globalNewsImageInput.value = '';
                return;
            }

            if (globalNewsRemoveImage) globalNewsRemoveImage.value = '0';
            const reader = new FileReader();
            reader.onload = (e) => {
                if (globalNewsImagePreviewImg) globalNewsImagePreviewImg.src = e.target.result;
                if (globalNewsImagePreviewName) globalNewsImagePreviewName.textContent = file.name;
                if (globalNewsImagePreviewSize) {
                    const sizeStr = file.size < 1024 * 1024 ? (file.size / 1024).toFixed(1) + ' KB' : (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                    globalNewsImagePreviewSize.textContent = sizeStr;
                }
                if (globalNewsImagePreviewContainer) {
                    globalNewsImagePreviewContainer.classList.remove('d-none');
                    globalNewsImagePreviewContainer.classList.add('d-flex');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    if (btnRemoveGlobalNewsImage) {
        btnRemoveGlobalNewsImage.addEventListener('click', () => {
            if (globalNewsImageInput) globalNewsImageInput.value = '';
            if (globalNewsRemoveImage) globalNewsRemoveImage.value = '1';
            if (globalNewsImagePreviewContainer) {
                globalNewsImagePreviewContainer.classList.add('d-none');
                globalNewsImagePreviewContainer.classList.remove('d-flex');
            }
            if (globalNewsImagePreviewImg) globalNewsImagePreviewImg.src = '';
        });
    }

    async function loadGlobalNews() {
        if (!newsContainer) return;
        try {
            const response = await fetch(`${API_URL}?action=get_news`);
            const result   = await response.json();

            if (result.success && Array.isArray(result.data)) {
                const newsList = result.data;
                if (newsList.length === 0) {
                    newsContainer.innerHTML = `
                        <div class="col-12 text-center text-muted py-5 small">
                            <i class="bi bi-newspaper fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            Belum ada berita global. Klik "Tulis Berita" untuk menerbitkan.
                        </div>`;
                    return;
                }

                let html = '';
                newsList.forEach(article => {
                    const isOwner   = Number(article.user_id) === currentUserId;
                    const canManage = isOwner || isAdmin;
                    const dateStr   = article.created_at ? new Date(article.created_at.replace(/-/g, '/')).toLocaleString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                    }) : '-';
                    const avatar    = article.author_avatar ? article.author_avatar : defaultAvatar;
                    const hasImage  = Boolean(article.image_url);
                    const thumbUrl  = hasImage ? getThumbnailUrl(article.image_url, 450) : '';
                    const fullImageUrl = hasImage ? getFullImageUrl(article.image_url) : '';

                    html += `
                        <div class="col-md-6 col-xl-4" id="news-${Number(article.id)}">
                            <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column news-card" style="transition: transform 0.2s ease, box-shadow 0.2s ease;">
                                ${hasImage ? `
                                    <div class="position-relative overflow-hidden d-flex align-items-center justify-content-center p-2" style="height: 185px; background-color: #f8fafc; border-bottom: 1px solid #f1f5f9; cursor: pointer;" data-preview-image="${escapeHTML(fullImageUrl)}" data-preview-title="${escapeHTML(article.title)}" title="Klik untuk memperbesar">
                                        <div class="news-image-placeholder position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center text-muted" style="background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: newsShimmer 1.5s infinite;">
                                            <i class="bi bi-image fs-3 opacity-25"></i>
                                        </div>
                                        <img data-src="${escapeHTML(thumbUrl)}" alt="${escapeHTML(article.title)}" class="w-100 h-100 news-lazy-img" style="object-fit: contain; max-height: 100%; max-width: 100%; opacity: 0; transition: opacity 0.3s ease;" referrerpolicy="no-referrer">
                                        <div class="position-absolute top-0 end-0 m-2" style="z-index: 2;">
                                            <span class="badge bg-dark bg-opacity-75 rounded-pill px-2.5 py-1 small" style="backdrop-filter: blur(4px); font-size: 0.72rem;">
                                                <i class="bi bi-zoom-in me-1"></i>Perbesar
                                            </span>
                                        </div>
                                    </div>
                                ` : ''}

                                <!-- Author Header -->
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <img src="${escapeHTML(avatar)}" alt="${escapeHTML(article.author_name || 'Pengguna')}" class="rounded-circle border flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;" referrerpolicy="no-referrer" onerror="this.src='${defaultAvatar}'">
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;" title="${escapeHTML(article.author_name || 'Pengguna')}">
                                            ${escapeHTML(article.author_name || 'Pengguna')}
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            ${renderRoleBadge(article.author_role)}
                                            <span class="text-muted" style="font-size: 0.72rem;">
                                                <i class="bi bi-clock me-1"></i>${escapeHTML(dateStr)}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- News Title -->
                                <h5 class="card-title fw-bold text-dark mb-2 fs-6 text-break">
                                    ${escapeHTML(article.title)}
                                </h5>

                                <!-- News Content -->
                                <div class="text-secondary small text-break flex-grow-1 mb-3 news-content" style="white-space: pre-wrap; line-height: 1.6;">${escapeHTML(article.content)}</div>

                                <!-- Card Footer / Action Buttons -->
                                <div class="d-flex align-items-center justify-content-between pt-3 border-top border-light mt-auto flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-share-news="${Number(article.id)}" data-title="${escapeHTML(article.title)}">
                                        <i class="bi bi-share" style="font-size: 0.75rem;"></i>Bagikan
                                    </button>

                                    ${canManage ? `
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-edit-news="${article.id}" data-title="${escapeHTML(article.title)}" data-image="${escapeHTML(article.image_url || '')}">
                                                <i class="bi bi-pencil-square" style="font-size: 0.75rem;"></i>Edit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-delete-news="${article.id}">
                                                <i class="bi bi-trash3" style="font-size: 0.75rem;"></i>Hapus
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </article>
                        </div>`;
                });
                newsContainer.innerHTML = html;
                initLazyImages(newsContainer);
            } else {
                newsContainer.innerHTML = `<div class="col-12"><div class="alert alert-warning small rounded-3">${escapeHTML(result.message || 'Gagal memuat berita.')}</div></div>`;
            }
        } catch (error) {
            console.error('Gagal memuat berita global:', error);
            newsContainer.innerHTML = '<div class="col-12 text-center text-muted py-4 small">Terjadi kesalahan saat memuat berita global.</div>';
        }
    }

    if (openGlobalNewsForm) {
        openGlobalNewsForm.addEventListener('click', () => {
            resetGlobalNewsForm();
            if (globalNewsModal) globalNewsModal.show();
        });
    }

    if (globalNewsForm) {
        globalNewsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('submitGlobalNews');
            const originalText = submitBtn.innerHTML;

            const id      = document.getElementById('globalNewsId').value;
            const title   = document.getElementById('globalNewsTitle').value.trim();
            const content = document.getElementById('globalNewsContent').value.trim();

            if (!title || !content) {
                notify('Judul dan isi berita wajib diisi.', 'danger');
                return;
            }

            const action = id ? 'update_news' : 'create_news';
            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('title', title);
            formData.append('content', content);

            if (globalNewsRemoveImage) {
                formData.append('remove_image', globalNewsRemoveImage.value);
            }

            if (globalNewsImageInput && globalNewsImageInput.files && globalNewsImageInput.files[0]) {
                formData.append('image', globalNewsImageInput.files[0]);
            }

            try {
                let result;
                if (typeof window.uploadWithProgress === 'function') {
                    result = await window.uploadWithProgress(`${API_URL}?action=${action}`, formData, submitBtn);
                } else {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...`;
                    const response = await fetch(`${API_URL}?action=${action}`, {
                        method: 'POST',
                        body: formData
                    });
                    result = await response.json();
                }

                if (result && result.success) {
                    notify(result.message || 'Berita global disimpan.', 'success');
                    if (globalNewsModal) globalNewsModal.hide();
                    resetGlobalNewsForm();
                    await loadGlobalNews();
                } else {
                    notify(result?.message || 'Gagal menyimpan berita global.', 'danger');
                }
            } catch (error) {
                notify(error.message || 'Terjadi kesalahan saat menyimpan berita global.', 'danger');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    if (newsContainer) {
        newsContainer.addEventListener('click', async (e) => {
            // Image Preview Modal Zoom
            const previewEl = e.target.closest('[data-preview-image]');
            if (previewEl && imagePreviewModal) {
                const imgUrl = previewEl.dataset.previewImage;
                const imgTitle = previewEl.dataset.previewTitle || 'Pratinjau Gambar';
                if (modalImg) modalImg.src = imgUrl;
                if (modalImgTitle) modalImgTitle.textContent = imgTitle;
                imagePreviewModal.show();
                return;
            }

            // Share News Button
            const shareBtn = e.target.closest('[data-share-news]');
            if (shareBtn) {
                const shareUrl = `${BASE_URL}global#news-${shareBtn.dataset.shareNews}`;
                try {
                    await navigator.clipboard.writeText(shareUrl);
                    notify('Tautan berita global disalin ke clipboard.', 'success');
                } catch (err) {
                    notify(shareUrl, 'info');
                }
                return;
            }

            // Edit News Button
            const editBtn = e.target.closest('[data-edit-news]');
            if (editBtn) {
                resetGlobalNewsForm();
                const id = editBtn.dataset.editNews;
                const title = editBtn.dataset.title;
                const imageUrl = editBtn.dataset.image;
                const card = editBtn.closest('article');
                const content = card ? card.querySelector('.news-content').textContent : '';

                document.getElementById('globalNewsId').value = id;
                document.getElementById('globalNewsTitle').value = title;
                document.getElementById('globalNewsContent').value = content;
                document.getElementById('globalNewsModalTitle').textContent = 'Edit Berita Global';
                document.getElementById('submitGlobalNews').innerHTML = 'Simpan Perubahan';

                if (imageUrl) {
                    if (globalNewsImagePreviewImg) globalNewsImagePreviewImg.src = imageUrl;
                    if (globalNewsImagePreviewName) globalNewsImagePreviewName.textContent = 'Gambar Terlampir';
                    if (globalNewsImagePreviewSize) globalNewsImagePreviewSize.textContent = 'Tersimpan di Cloud';
                    if (globalNewsImagePreviewContainer) {
                        globalNewsImagePreviewContainer.classList.remove('d-none');
                        globalNewsImagePreviewContainer.classList.add('d-flex');
                    }
                }

                if (globalNewsModal) globalNewsModal.show();
                return;
            }

            // Delete News Button
            const delBtn = e.target.closest('[data-delete-news]');
            if (delBtn) {
                const id = delBtn.dataset.deleteNews;
                const confirmed = window.appConfirm ? await window.appConfirm('Hapus berita global ini?') : confirm('Hapus berita global ini?');
                if (!confirmed) return;

                try {
                    const response = await fetch(`${API_URL}?action=delete_news`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();

                    if (result.success) {
                        notify('Berita global berhasil dihapus.', 'success');
                        await loadGlobalNews();
                    } else {
                        notify(result.message || 'Gagal menghapus berita.', 'danger');
                    }
                } catch (error) {
                    notify('Terjadi kesalahan saat menghapus berita.', 'danger');
                }
            }
        });
    }

    // ------------------------------------------------------------------
    // Focus chat input when "Obrolan Global" tab is shown
    // ------------------------------------------------------------------
    const chatTabEl = document.getElementById('chat-tab');
    if (chatTabEl) {
        chatTabEl.addEventListener('shown.bs.tab', () => {
            if (chatInput) {
                chatInput.focus();
            }
        });
    }

    // ------------------------------------------------------------------
    // Init & Polling Execution
    // ------------------------------------------------------------------
    loadInitialChats();
    loadGlobalNews();

    // Poll new live chats every 3 seconds
    setInterval(pollNewChats, 3000);
});

