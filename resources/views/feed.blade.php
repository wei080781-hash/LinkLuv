<x-app-layout>
    <div class="py-12 bg-gray-50 flex-1">
        <div class="max-w-4xl mx-auto px-6">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8">生活牆</h2>
            @include('components.message-form')
            <div id="messages-list" class="flex flex-col gap-4"></div>
            <div id="scroll-sentinel" class="h-8 flex items-center justify-center">
                <span id="loading-indicator" class="text-sm text-gray-400 hidden">載入中...</span>
            </div>
        </div>
    </div>

    {{-- Lightbox --}}
    <div id="lightbox" class="hidden fixed inset-0 bg-black/85 z-50 items-center justify-center">
        <button onclick="closeLightbox()"
            class="absolute top-4 right-5 text-white text-4xl leading-none bg-transparent border-none cursor-pointer">✕</button>
        <div id="lightbox-content"></div>
    </div>

    <style>
    .reply-branch {
        position: relative;
    }

    .reply-branch::before {
        content: '';
        position: absolute;
        left: -16px;
        top: 22px;
        width: 16px;
        height: 2px;
        background-color: #e5e7eb;
    }

    #lightbox.active {
        display: flex;
    }

    #lightbox img,
    #lightbox video {
        max-width: 90vw;
        max-height: 90vh;
        border-radius: 8px;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
    }

    .replies-wrapper {
        overflow: hidden;
        max-height: 0;
        opacity: 0;
        transition: max-height 0.35s ease, opacity 0.25s ease;
    }

    .replies-wrapper.expanded {
        max-height: 99999px;
        opacity: 1;
    }

    .toggle-arrow {
        display: inline-block;
        transition: transform 0.2s ease;
        font-size: 0.65rem;
    }

    .toggle-open .toggle-arrow {
        transform: rotate(180deg);
    }

    .msg-bubble.msg-highlight {
        background: #dbeafe !important;
        border-color: #93c5fd !important;
        border: 2px solid #93c5fd;
        border-radius: 1rem;
    }

    .msg-bubble {
        transition: background 0.15s;
    }

    .reply-form-wrap {
        display: none;
    }

    .reply-form-wrap.show {
        display: flex;
    }

    .msg-media img,
    .msg-media video {
        max-width: 240px;
        max-height: 240px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        margin-top: 6px;
        display: block;
        cursor: pointer;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    </style>

    <script>
    // =========================================================
    // 1. 全域狀態初始化
    // =========================================================
    window.globalMsgMap = new Map();
    window.expandedSet = new Set();
    window.currentUserId = {{ auth()->id() ?? 'null' }};

    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;

    // =========================================================
    // 2. 頁面載入後統一啟動
    // =========================================================
    document.addEventListener('DOMContentLoaded', () => {
        const sentinel = document.getElementById('scroll-sentinel');
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !isLoading && hasMore) {
                document.getElementById('loading-indicator').classList.remove('hidden');
                loadMessages();
            }
        }, { rootMargin: '0px 0px 100px 0px' });
        observer.observe(sentinel);

        loadMessages();
        initEchoWithRetry();
    });

    // =========================================================
    // 3. WebSocket 初始化
    // =========================================================
    function initEchoWithRetry() {
        let retries = 0;
        const checkEcho = setInterval(() => {
            if (window.Echo || typeof Echo !== 'undefined') {
                window.Echo = window.Echo || Echo;
                clearInterval(checkEcho);
                setupEcho();
            } else if (++retries > 25) {
                clearInterval(checkEcho);
                console.error('❌ Echo 無法啟動，請檢查 Reverb 設定');
            }
        }, 200);
    }

    function setupEcho() {
        window.Echo.channel('wall-channel')
            .listen('.message.created', (e) => {
                handleNewMessage(e.message);
            })
            .listen('.message.status.updated', (e) => {
                // 影片壓縮完成，局部更新該則訊息的播放器
                const updatedMsg = e.message;
                const msgId = Number(updatedMsg.id);
                const msg = window.globalMsgMap.get(msgId);

                if (msg) {
                    // 資料流向：把包裹裡的最新字串 "text 013"，強行寫入 B 帳號的快取地圖中！
                    msg.content = updatedMsg.content;
                    
                    // 更新記憶體中的狀態
                    msg.status = updatedMsg.status;
                    msg.video_path = updatedMsg.video_path;

                    // 找到根貼文並重繪
                    const rootId = msg.parent_id ? findRootId(msg.parent_id) : msgId;

                    // 把根貼文 ID 鎖定在展開名單中，防止重繪時卡片突然折疊收合
                    window.expandedSet.add(rootId);

                    const rootEl = document.getElementById(`msg-${rootId}`);
                    const rootMsg = window.globalMsgMap.get(rootId);
                    if (rootEl && rootMsg) {
                        rootEl.outerHTML = buildRootHTML(rootMsg);
                        console.log(`🤖 Echo 成功除錯：B 帳號已在背景將訊息 ${msgId} 的記憶體補完並完成全自動重繪！`);
                    }
                }
            })
            
            .listen('.message.deleted', (e) => {
                // 即時從畫面移除被刪除的訊息
                const msgId = Number(e.messageId);
                const el = document.getElementById(`msg-${msgId}`);
                if (el) el.remove();
                window.globalMsgMap.delete(msgId);
            });
    }

    // =========================================================
    // 4. 統一處理新訊息入口
    // =========================================================
    window.handleNewMessage = function(newMsg) {
        newMsg.id = Number(newMsg.id);
        newMsg.parent_id = newMsg.parent_id == null ? null : Number(newMsg.parent_id);

        if (window.globalMsgMap.has(newMsg.id)) return;

        newMsg.children = [];
        window.globalMsgMap.set(newMsg.id, newMsg);

        if (!newMsg.parent_id) {
            const list = document.getElementById('messages-list');
            if (list) list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));
        } else {
            const rootId = findRootId(newMsg.parent_id);
            const trueParent = window.globalMsgMap.get(newMsg.parent_id);
            if (trueParent) {
                if (!trueParent.children) trueParent.children = [];
                if (!trueParent.children.some(c => c.id === newMsg.id)) {
                    trueParent.children.push(newMsg);
                }
            }

            window.expandedSet.add(rootId);
            const rootEl = document.getElementById(`msg-${rootId}`);
            const rootMsg = window.globalMsgMap.get(rootId);

            if (rootEl && rootMsg) {
                const activeInput = rootEl.querySelector('input[name="content"], textarea');
                const isFocused = activeInput && (document.activeElement === activeInput);
                const hasTyped = activeInput && activeInput.value.trim() !== '';
                if (!isFocused && !hasTyped) {
                    rootEl.outerHTML = buildRootHTML(rootMsg);
                }
            }
        }
    };

    // =========================================================
    // 5. 輔助函式：回溯找出根貼文 ID
    // =========================================================
    function findRootId(parentId) {
        let current = window.globalMsgMap.get(parentId);
        while (current && current.parent_id) {
            current = window.globalMsgMap.get(current.parent_id);
        }
        return current ? current.id : parentId;
    }

    // =========================================================
    // 6. 載入訊息核心
    // =========================================================
    function loadMessages(reset = false) {
        if (isLoading || (!hasMore && !reset)) return;

        if (reset) {
            currentPage = 1;
            hasMore = true;
            document.getElementById('messages-list').innerHTML = '';
            window.globalMsgMap.clear();
        }

        isLoading = true;

        fetch(`/api/messages?page=${currentPage}`)
            .then(r => r.json())
            .then(response => {
                appendMessages(response.data);
                hasMore = response.has_more;
                currentPage = response.next_page;
                isLoading = false;
                document.getElementById('loading-indicator').classList.add('hidden');

                if (!hasMore) {
                    const sentinel = document.getElementById('scroll-sentinel');
                    if (sentinel) sentinel.innerHTML = '<span class="text-sm text-gray-300">已顯示全部訊息</span>';
                }
            });
    }

    function indexToMap(msg) {
        if (!window.globalMsgMap.has(msg.id)) {
            window.globalMsgMap.set(msg.id, { ...msg, children: [] });
        } else {
            const existing = window.globalMsgMap.get(msg.id);
            window.globalMsgMap.set(msg.id, { ...msg, children: existing.children });
        }
        if (msg.children && msg.children.length > 0) {
            msg.children.forEach(child => indexToMap(child));
        }
    }

    function appendMessages(messages) {
        const list = document.getElementById('messages-list');

        messages.forEach(m => indexToMap(m));

        messages.forEach(m => {
            if (m.parent_id) {
                const parent = window.globalMsgMap.get(m.parent_id);
                if (parent && !parent.children.some(c => c.id === m.id)) {
                    parent.children.push(window.globalMsgMap.get(m.id));
                }
            }
        });

        messages.forEach(m => {
            if (!m.parent_id && !document.getElementById(`msg-${m.id}`)) {
                list.insertAdjacentHTML('beforeend', buildRootHTML(window.globalMsgMap.get(m.id)));
            }
        });
    }

    // =========================================================
    // 7. HTML 建構函式
    // =========================================================
    function buildRootHTML(msg) {
        const hasReplies = msg.children && msg.children.length > 0;
        const isOpen = window.expandedSet.has(msg.id);
        const count = msg.children ? msg.children.length : 0;

        const avatarCol = hasReplies
            ? `<div class="flex flex-col items-center flex-shrink-0 w-10">
                <img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                <div class="w-0.5 flex-1 bg-gray-300 mt-1 rounded-full min-h-3"></div>
               </div>`
            : `<img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">`;

        const toggleBtn = hasReplies
            ? `<button onclick="toggleReplies(${msg.id}, ${count})" id="tbtn-${msg.id}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 mt-1 select-none bg-transparent border-none cursor-pointer p-0">
                <span class="toggle-arrow ${isOpen ? 'rotate-180' : ''}">▼</span>
                <span id="tlabel-${msg.id}">${isOpen ? '隱藏回覆' : `查看 ${count} 則回覆`}</span>
               </button>`
            : '';

        const repliesHtml = hasReplies
            ? msg.children.map(c => buildReplyHTML(c, msg.id, new Set(), 1)).join('')
            : '';

        const ownerButtons = (window.currentUserId && msg.user_id == window.currentUserId)
            ? `<button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer p-0 text-xs text-red-300">刪除</button>
               <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">編輯</button>`
            : '';

        return `
        <div id="msg-${msg.id}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4" data-id="${msg.id}">
            <div class="flex items-start gap-2.5">
                ${avatarCol}
                <div class="flex-1 min-w-0">
                    <div class="msg-bubble bg-gray-100 hover:bg-gray-200 rounded-2xl px-3 py-2">
                        <div class="flex items-baseline gap-1 flex-wrap mb-0.5">
                            <span class="text-sm font-bold text-gray-700">${escHtml(msg.user.name)}</span>
                            <span class="text-xs text-gray-400 ml-auto">${buildTimeLabel(msg.created_at)}</span>
                        </div>
                        <p class="text-sm text-gray-800 m-0 whitespace-pre-wrap" id="content-${msg.id}">${escHtml(msg.content)}</p>
                        ${buildMediaHtml(msg)}
                    </div>
                    <div class="flex gap-3 text-xs text-gray-400 mt-1">
                        <button onclick="toggleReply(${msg.id}, ${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">回覆</button>
                        ${ownerButtons}
                        <button onclick="toggleLike(${msg.id})" class="like-btn flex items-center gap-1 bg-transparent border-none cursor-pointer p-0 text-xs ${msg.is_liked ? 'text-pink-500 font-bold' : 'text-gray-400'}">❤️ <span id="lcount-${msg.id}">${msg.likes_count || 0}</span></button>
                    </div>
                    ${buildReplyForm(msg.id, msg.id)}
                    ${toggleBtn}
                </div>
            </div>
            <div id="rwrap-${msg.id}" class="replies-wrapper ${isOpen ? 'expanded' : ''}">
                <div class="ml-5 pl-4 border-l-2 border-gray-200 mt-2">
                    ${repliesHtml}
                </div>
            </div>
        </div>`;
    }

    function buildReplyHTML(msg, rootId, visited, depth = 1) {
        if (visited.has(msg.id)) return '';
        visited.add(msg.id);

        const hasChildren = msg.children && msg.children.length > 0;
        const childrenHtml = hasChildren
            ? msg.children.map(c => buildReplyHTML(c, rootId, new Set(visited), depth + 1)).join('')
            : '';

        const branchBtn = hasChildren
            ? `<button class="hover:text-gray-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400" id="bbtn-${msg.id}" onclick="toggleBranch(${msg.id}, this)">▾ 收合</button>`
            : '';

        const childrenSection = hasChildren
            ? (depth < 3
                ? `<div id="branch-${msg.id}" class="ml-6 pl-4 border-l-2 border-gray-200 mt-1">${childrenHtml}</div>`
                : `<div id="branch-${msg.id}">${childrenHtml}</div>`)
            : '';

        const ownerButtons = (window.currentUserId && msg.user_id == window.currentUserId)
            ? `<button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer p-0 text-xs text-red-300">刪除</button>
               <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">編輯</button>`
            : '';

        return `
        <div id="msg-${msg.id}" class="reply-branch relative pt-2.5" data-id="${msg.id}" data-parent-id="${msg.parent_id || ''}">
            <div class="flex items-start gap-2">
                <img src="${msg.user.profile_photo_url}" class="w-7 h-7 rounded-full object-cover flex-shrink-0 mt-0.5 relative z-10">
                <div class="flex-1 min-w-0">
                    <div class="msg-bubble bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl px-3 py-2">
                        <div class="flex items-baseline gap-1 flex-wrap mb-0.5">
                            <span class="text-xs font-bold text-gray-700">${escHtml(msg.user.name)}</span>
                            ${buildReplyingToTag(msg.parent_id, rootId)}
                            <span class="text-xs text-gray-400 ml-auto">${buildTimeLabel(msg.created_at)}</span>
                        </div>
                        <p class="text-sm text-gray-800 m-0 whitespace-pre-wrap" id="content-${msg.id}">${escHtml(msg.content)}</p>
                        ${buildMediaHtml(msg)}
                    </div>
                    <div class="flex gap-3 text-xs text-gray-400 mt-1">
                        <button onclick="toggleReply(${msg.id}, ${rootId})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">回覆</button>
                        ${branchBtn}
                        ${ownerButtons}
                        <button onclick="toggleLike(${msg.id})" class="like-btn flex items-center gap-1 bg-transparent border-none cursor-pointer p-0 text-xs ${msg.is_liked ? 'text-pink-500 font-bold' : 'text-gray-400'}">❤️ <span id="lcount-${msg.id}">${msg.likes_count || 0}</span></button>
                    </div>
                    ${buildReplyForm(msg.id, rootId)}
                </div>
            </div>
            ${childrenSection}
        </div>`;
    }

    // =========================================================
    // 8. 小型 Helper 函式
    // =========================================================
    function buildReplyForm(msgId, rootId) {
        return `<div id="rform-${msgId}" class="reply-form-wrap items-center gap-2 mt-1.5 flex-wrap">
            <form action="/messages" method="POST" onsubmit="submitReply(event, ${rootId})" class="flex gap-2 w-full items-center">
                <input type="hidden" name="parent_id" value="${msgId}">
                <input type="text" name="content" placeholder="回覆..." class="flex-1 min-w-24 rounded-full text-sm px-4 py-1.5 border border-gray-300 outline-none focus:border-blue-400 transition-colors">
                <label class="text-gray-400 text-base cursor-pointer flex-shrink-0" title="上傳圖片或影片">📎
                    <input type="file" name="media" accept="image/*,video/mp4,video/mov,video/ogg" class="hidden" onchange="previewMedia(this,'fprev-${msgId}')">
                </label>
                <button type="submit" class="text-blue-600 text-sm font-bold bg-transparent border-none cursor-pointer whitespace-nowrap">送出</button>
            </form>
            <div id="fprev-${msgId}" class="msg-media"></div>
        </div>`;
    }

    function buildReplyingToTag(parentId, rootId) {
        if (!parentId || parentId === rootId) return '';
        const parent = window.globalMsgMap.get(parentId);
        if (!parent) return '';
        const preview = parent.content ? parent.content.slice(0, 20) + (parent.content.length > 20 ? '...' : '') : '';
        return `
            <span class="text-xs text-gray-400">回覆</span>
            <span class="text-xs font-semibold text-blue-500 cursor-pointer hover:underline" onclick="scrollToMsg(${parentId})">@${escHtml(parent.user.name)}</span>
            <span class="text-xs text-gray-300">·</span>
            <span class="text-xs text-gray-400 cursor-pointer hover:text-blue-400 hover:underline italic max-w-32 truncate inline-block align-bottom"
                  onclick="scrollToMsg(${parentId})" title="${escHtml(preview)}">「${escHtml(preview)}」</span>
        `;
    }

    function buildTimeLabel(createdAt) {
        if (!createdAt) return '';
        const d = new Date(createdAt);
        const diff = Math.floor((Date.now() - d) / 1000);
        if (diff < 60) return '剛剛';
        if (diff < 3600) return Math.floor(diff / 60) + ' 分鐘前';
        if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
        if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
        return `${d.getMonth()+1}/${d.getDate()}`;
    }

    function buildMediaHtml(msg) {
        const s3BaseUrl = 'https://linkluv-media-bucket.s3.ap-east-2.amazonaws.com/';

        if (msg.media_type === 'image' && msg.image_path) {
            const isS3 = msg.image_path.startsWith('images/') || !msg.image_path.startsWith('storage/');
            const imgUrl = isS3 ? `${s3BaseUrl}${msg.image_path}` : `/storage/${msg.image_path}`;
            return `<div class="msg-media"><img src="${imgUrl}" onclick="openLightbox('image','${imgUrl}')"></div>`;
        }

        if (msg.media_type === 'video' && msg.video_path) {
            // 影片還在壓縮中：顯示轉圈提示
            if (msg.status === 'processing') {
                return `<div class="msg-media flex items-center gap-2 mt-2 text-xs text-gray-400">
                    <svg class="animate-spin w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    影片處理中，完成後將自動顯示...
                </div>`;
            }
            // 壓縮失敗：顯示錯誤提示
            if (msg.status === 'failed') {
                return `<div class="msg-media flex items-center gap-2 mt-2 text-xs text-red-400">
                    ⚠️ 影片轉檔失敗，請重新上傳
                </div>`;
            }
            // status === 'ready'：正常顯示播放器
            const isS3 = msg.video_path.startsWith('videos/') || !msg.video_path.startsWith('storage/');
            const videoUrl = isS3 ? `${s3BaseUrl}${msg.video_path}` : `/storage/${msg.video_path}`;
            return `<div class="msg-media"><video controls preload="metadata"><source src="${videoUrl}" type="video/mp4">您的瀏覽器不支援影片播放。</video></div>`;
        }

        return '';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // =========================================================
    // 9. 互動事件函式
    // =========================================================
    window.toggleReplies = function(rootId, count) {
        const wrap = document.getElementById(`rwrap-${rootId}`);
        const btn = document.getElementById(`tbtn-${rootId}`);
        const label = document.getElementById(`tlabel-${rootId}`);
        const arrow = btn ? btn.querySelector('.toggle-arrow') : null;
        if (!wrap) return;
        const isOpen = wrap.classList.contains('expanded');
        if (isOpen) {
            wrap.classList.remove('expanded');
            if (label) label.textContent = `查看 ${count} 則回覆`;
            if (arrow) arrow.classList.remove('rotate-180');
            window.expandedSet.delete(rootId);
        } else {
            wrap.classList.add('expanded');
            if (label) label.textContent = '隱藏回覆';
            if (arrow) arrow.classList.add('rotate-180');
            window.expandedSet.add(rootId);
        }
    };

    window.toggleBranch = function(msgId, btn) {
        const branch = document.getElementById(`branch-${msgId}`);
        if (!branch) return;
        const isHidden = branch.classList.contains('hidden');
        if (isHidden) {
            branch.classList.remove('hidden');
            btn.textContent = '▾ 收合';
        } else {
            branch.classList.add('hidden');
            btn.textContent = '▸ 展開';
        }
    };

    window.toggleReply = function(msgId, rootId) {
        if (rootId !== msgId) {
            const wrap = document.getElementById(`rwrap-${rootId}`);
            const label = document.getElementById(`tlabel-${rootId}`);
            const btn = document.getElementById(`tbtn-${rootId}`);
            const arrow = btn ? btn.querySelector('.toggle-arrow') : null;
            if (wrap && !wrap.classList.contains('expanded')) {
                wrap.classList.add('expanded');
                if (label) label.textContent = '隱藏回覆';
                if (arrow) arrow.classList.add('rotate-180');
                window.expandedSet.add(rootId);
            }
        }
        const form = document.getElementById(`rform-${msgId}`);
        if (!form) return;
        const isShown = form.classList.contains('show');
        if (isShown) {
            form.classList.remove('show');
        } else {
            form.classList.add('show');
            const input = form.querySelector('input[name="content"]');
            if (input) input.focus();
            highlightMsg(msgId);
        }
    };

    window.submitReply = function(e, rootId) {
        e.preventDefault();
        const form = e.target;
        const contentInput = form.querySelector('input[name="content"]');
        const fileInput = form.querySelector('input[type="file"]');
        const submitBtn = form.querySelector('button[type="submit"]');

        if (!contentInput.value.trim() && (!fileInput || !fileInput.files.length)) {
            alert('請輸入回覆內容或上傳媒體');
            return;
        }

        if (submitBtn) submitBtn.disabled = true;
        const msgId = form.querySelector('input[name="parent_id"]')?.value;

        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => {
            if (r.status === 419) {
                alert('登入已過期，頁面將自動重新整理');
                window.location.reload();
                return Promise.reject('419');
            }
            return r.json();
        })
        .then(d => {
            if (d.success && d.data) {
                form.reset();
                if (msgId) {
                    const preview = document.getElementById(`fprev-${msgId}`);
                    if (preview) preview.innerHTML = '';
                }
                handleNewMessage(d.data);
            }
        })
        .catch(err => {
            if (err !== '419') console.error('發送失敗:', err);
        })
        .finally(() => {
            if (submitBtn) submitBtn.disabled = false;
        });
    };

    window.submitPost = function(e) {
        e.preventDefault();
        const form = e.target;
        const fi = form.querySelector('input[type="file"]');
        const submitBtn = form.querySelector('button[type="submit"]');

        if (fi?.files.length > 0 && fi.files[0].size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            fi.value = '';
            return;
        }

        if (submitBtn) submitBtn.disabled = true;

        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => {
            if (r.status === 419) {
                alert('登入已過期，頁面將自動重新整理');
                window.location.reload();
                return Promise.reject('419');
            }
            return r.json();
        })
        .then(d => {
            if (d.success && d.data) {
                form.reset();
                const preview = document.getElementById('fprev-main');
                if (preview) preview.innerHTML = '';
                handleNewMessage(d.data);
            } else {
                loadMessages(true);
                form.reset();
            }
        })
        .catch(err => {
            if (err !== '419') console.error('貼文失敗:', err);
        })
        .finally(() => {
            if (submitBtn) submitBtn.disabled = false;
        });
    };

    window.deleteMsg = function(id) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;
        fetch(`/messages/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages(true);
        });
    };

    window.editMsg = function(id) {
        
        // 1. 先精準找出這則留言存放文字內容的舊標籤
        const p = document.getElementById(`content-${id}`);
        if (!p) return;// 安全機制：萬一找不到就直接退出

        // 2. 備份原本寫在裡面的文字內容
        const orig = p.innerText;

        // 3. 把原本的文字換成包含 <textarea> 輸入框與按鈕的 HTML 結構
        // 💡 關鍵修改：我們將輸入框的 ID 統一命名為 `edit-textarea-${id}`，方便儲存時抓取
        p.innerHTML = `<textarea id="edit-textarea-${id}" rows="2" class="w-full text-sm border border-gray-300 rounded-lg p-2 resize-none">${orig}</textarea>
            <div class="flex gap-2 mt-1">
                <button onclick="saveEdit(${id})" class="text-xs bg-blue-500 text-white px-3 py-1 rounded-lg border-none cursor-pointer">儲存</button>
                <button onclick="loadMessages(true)" class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded-lg border-none cursor-pointer">取消</button>
            </div>`;
    };

    window.saveEdit = function(id) {

    // 🍞 麵包屑 1：測試點擊儲存時，有沒有成功開門進入函式
    console.log("1111 系統回報：確認成功觸發 saveEdit 函式！傳進來的 ID 是:", id);

    // 1. 精準抓取剛剛在 editMsg 裡生出來的那個實體輸入框元件
    const textareaEl = document.getElementById(`edit-textarea-${id}`);

    // 🍞 麵包屑 2：測試有沒有成功抓到那個實體元件盒子
    console.log("2222 系統回報：抓到的輸入框元件是:", textareaEl);

    // 2. 提領出使用者在輸入框裡敲下的最新文字內容
    const val = textareaEl?.value;

    // 🍞 麵包屑 3：測試有沒有成功拿到裡面的字字串
    console.log("3333 系統回報：準備送出的最新文字是:", val);

    if (!val) {
        console.log("⚠️ 守門員警告：因為沒抓到文字（可能為空），程式在此處被強行阻斷攔停！");
        return; // 安全機制：萬一沒輸入內容就直接退出
    }   
    
    // 3. 發送非同步請求給後端
    fetch(`/messages/${id}`, {
        method: 'PATCH', // 使用 PATCH 方法進行局部更新
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ content: val }) // 將新文字打包成 JSON 傳送
    }).then(r =>{ 
        // 🍞 麵包屑 4：測試網路有沒有回應，以及有沒有過關（例如 200 或 419）
        console.log("4444 系統回報：網路有了回應！原始回應狀態碼是:", r.status);
        return r.json();
    })    
    .then(d => {
        // 🍞 麵包屑 5：測試解碼後的 Laravel Response 資料包長怎樣
        console.log("5555 系統回報：後端解碼後的 JSON 資料是:", d);

        // 5. 判斷後端資料庫是否順利寫入成功
        if (d.success) {

            // 🛠️ 【核心改造點】：我們不再抓取外層大盒子，也不再呼叫只會處理多媒體的 buildMediaHtml！
            // 我們拿著精準地圖，直接去網頁上抓出那塊專門用來放留言純文字的舊 <p> 標籤
            const contentEl = document.getElementById(`content-${id}`);
            
            if (contentEl) {
                // 提領出後端剛剛存入資料庫、熱騰騰的最新文字字串
                const latestText = d.message?.content || d.data?.content || val;
                
                // 核心微創手術：原地擦掉舊文字，換上經過 escHtml 安全過濾後的最新文字！
                contentEl.innerHTML = escHtml(latestText);
                
                console.log(`🎉 訊息 ${id} 原地局部純文字抽換成功！外殼完好，位置絕不移位！`);
            } else {
                console.log("⚠️ 警告：找不到 content-id 文字標籤，退回傳統整頁刷新重繪模式");
                // 備用機制：萬一畫面上找不到這個文字標籤，才退回原本的全頁重繪
                loadMessages(true);
            }
            
        } else {
            alert('修改失敗：' + d.message);
        }
    })
    .catch(err => {
        console.error('儲存編輯時發生網路錯誤:', err);
    });
};

    window.toggleLike = function(id) {
        id = Number(id);
        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(d => {
            // fetch 完成後才查詢 DOM，避免重繪導致拿到舊節點
            const btn = document.querySelector(`#msg-${id} .like-btn, [data-id="${id}"] .like-btn`);
            const countEl = document.getElementById(`lcount-${id}`);

            if (d.likes_count !== undefined) {
                if (countEl) countEl.textContent = d.likes_count;
                if (btn) {
                    if (d.is_liked) {
                        btn.classList.add('text-pink-500', 'font-bold');
                        btn.classList.remove('text-gray-400');
                    } else {
                        btn.classList.remove('text-pink-500', 'font-bold');
                        btn.classList.add('text-gray-400');
                    }
                }
                if (window.globalMsgMap.has(id)) {
                    const msg = window.globalMsgMap.get(id);
                    msg.likes_count = d.likes_count;
                    msg.is_liked = d.is_liked;
                }
            } else {
                const isLiked = btn && btn.classList.contains('text-pink-500');
                let count = parseInt(countEl?.textContent) || 0;
                if (isLiked) {
                    count = Math.max(0, count - 1);
                    if (btn) {
                        btn.classList.remove('text-pink-500', 'font-bold');
                        btn.classList.add('text-gray-400');
                    }
                } else {
                    count += 1;
                    if (btn) {
                        btn.classList.add('text-pink-500', 'font-bold');
                        btn.classList.remove('text-gray-400');
                    }
                }
                if (countEl) countEl.textContent = count;
                if (window.globalMsgMap.has(id)) {
                    const msg = window.globalMsgMap.get(id);
                    msg.likes_count = count;
                    msg.is_liked = !isLiked;
                }
            }
        })
        .catch(err => console.error('點讚發送失敗:', err));
    };

    window.previewMedia = function(input, previewId) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            input.value = '';
            return;
        }
        const url = URL.createObjectURL(file);
        const el = document.getElementById(previewId);
        if (!el) return;
        el.innerHTML = file.type.startsWith('image/')
            ? `<img src="${url}" class="max-w-40 rounded-xl mt-1">`
            : `<video src="${url}" controls class="max-w-48 rounded-xl mt-1"></video>`;
    };

    window.openLightbox = function(type, src) {
        document.getElementById('lightbox-content').innerHTML = type === 'image'
            ? `<img src="${src}">`
            : `<video src="${src}" controls autoplay></video>`;
        document.getElementById('lightbox').classList.add('active');
    };

    window.closeLightbox = function() {
        document.getElementById('lightbox').classList.remove('active');
        document.getElementById('lightbox-content').innerHTML = '';
    };

    document.getElementById('lightbox').addEventListener('click', function(e) {
        if (e.target === this) closeLightbox();
    });

    function highlightMsg(msgId) {
        document.querySelectorAll('.msg-highlight').forEach(el => el.classList.remove('msg-highlight'));
        const el = document.querySelector(`[data-id="${msgId}"]`);
        if (el) el.classList.add('msg-highlight');
    }

    window.scrollToMsg = function(msgId) {
        const rootWrap = document.querySelector(`[data-id="${msgId}"]`)?.closest('.replies-wrapper');
        if (rootWrap && !rootWrap.classList.contains('expanded')) {
            rootWrap.classList.add('expanded');
        }
        const el = document.querySelector(`[data-id="${msgId}"]`);
        if (!el) return;
        document.querySelectorAll('.msg-highlight').forEach(e => e.classList.remove('msg-highlight'));
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const bubble = el.querySelector(':scope > .flex > .flex-1 > .msg-bubble, :scope > div > .flex-1 > .msg-bubble');
        const target = bubble || el;
        target.classList.add('msg-highlight');
        setTimeout(() => target.classList.remove('msg-highlight'), 1500);
    };
    // 將你的非同步更新手術功能，完整放入這個 <script> 盒子中
    function sendUpdateToBackend(messageId) {
            console.log("1111 系統回報：確認成功觸發 sendUpdateToBackend 函式！傳進來的 ID 是:", messageId);
            const textareaEl = document.getElementById(`edit-textarea-${messageId}`);

            // 🍞 麵包屑 2：測試有沒有成功抓到那個輸入框元件
            console.log("2222 系統回報：抓到的輸入框元件是:", textareaEl);

            const newText = textareaEl.value;

            // 🍞 麵包屑 3：測試有沒有成功拿到裡面的字
            console.log("3333 系統回報：準備送出的最新文字是:", newText);

            fetch(`/messages/${messageId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ content: newText })
            })
            .then(response => {
                console.log("4444 系統回報：網路有了回應！原始回應狀態碼是:", response.status);
                return response.json();
            })    
            .then(data => {
                console.log("5555 系統回報：後端解碼後的 JSON 資料是:", data);
                if (data.success) {
                    const oldMessageEl = document.getElementById(`msg-${messageId}`);
                    const newHtmlString = buildMediaHtml(data.message);
                    
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = newHtmlString;
                    const newMessageEl = tempDiv.firstElementChild;
                    
                    oldMessageEl.replaceWith(newMessageEl);
                    console.log(`訊息 ${messageId} 原地更新成功！`);
                } else {
                    alert('修改失敗：' + data.message);
                }
            })
            .catch(error => {
                console.error('網路請求發生錯誤：', error);
            });
        }


    </script>
</x-app-layout>