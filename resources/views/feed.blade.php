
<x-app-layout>
    <div class="py-12 bg-gray-50 flex-1">
        <div class="max-w-3xl mx-auto px-4 flex flex-col gap-6">
            <div class="flex items-center  w-full h-12">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight flex-shrink-0">生活牆</h2>
                </div>
            </div>
            
            <!-- 【第二層】發表動態表單 -->
             <div class="w-full">
                @include('components.message-form')
            </div>

            <!-- 訊息列表區 -->
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

    {{-- 上傳 Toast 通知：固定右下角，預設隱藏 --}}
    <div id="toast-stack" class="toast-stack"></div>
        <div class="upload-toast-body">
             <span id="upload-toast-text">檔案上傳中...</span>
        </div>
        <button type="button" onclick="hideToastNow()" class="upload-toast-close" aria-label="關閉">✕</button>
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

    /* ============================================================
       上傳 Toast 通知樣式（取代原本的進度條）
    ============================================================ */
    .toast-stack {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 60;
    display: flex;
    flex-direction: column-reverse; /* 新的疊在舊的上面 */
    gap: 10px;
    align-items: flex-end;
}

    .toast-item {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 220px;
        max-width: 320px;
        padding: 12px 14px;
        background: #1f2937;
        color: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        opacity: 0;
        transform: translateY(12px);
        transition: opacity 0.25s ease, transform 0.25s ease;
    }

    .toast-item.show {
        opacity: 1;
        transform: translateY(0);
    }

    .toast-item.fade-out {
        opacity: 0;
        transform: translateY(12px);
    }

    .toast-item .toast-icon {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .toast-item .toast-spinner {
        width: 18px;
        height: 18px;
        border: 2.5px solid rgba(255, 255, 255, 0.25);
        border-top-color: #60a5fa;
        border-radius: 50%;
        animation: toast-spin 0.8s linear infinite;
    }
 
    @keyframes toast-spin {
        to { transform: rotate(360deg); }
    }

    .toast-item .toast-body {
    flex: 1;
    font-size: 13px;
    font-weight: 500;
}

    .toast-item.success .toast-body { color: #86efac; }
    .toast-item.error .toast-body { color: #fca5a5; }

    .toast-item .toast-close {
        flex-shrink: 0;
        background: transparent;
        border: none;
        color: #9ca3af;
        font-size: 14px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
}

    .toast-item .toast-close:hover { color: #fff; }
    </style>

    <script>
    // =========================================================
    // 1. 全域狀態初始化（統一用 window，不重複宣告）
    // =========================================================
    window.globalMsgMap = new Map();
    window.expandedSet = new Set();
    window.currentUserId = {{ auth()->id() ?? 'null' }};

    // 🎯 影片上傳號碼牌：目前是否有一支影片正在後端處理中
    //    有值時，前端會擋下「下一支影片」的送出（圖片、文字不受影響）
    window.pendingVideoUploadId = null;
    window.pendingImageUploadId = null; // 🆕 新增：圖片也要限制一次一張

    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;

    // =========================================================
    // 2. 頁面載入後統一啟動
    // =========================================================
    document.addEventListener('DOMContentLoaded', () => {
        // A. 啟動無限滾動觀察器
        const sentinel = document.getElementById('scroll-sentinel');
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !isLoading && hasMore) {
                document.getElementById('loading-indicator').classList.remove('hidden');
                loadMessages();
            }
        }, { rootMargin: '0px 0px 100px 0px' });
        observer.observe(sentinel);

        // B. 載入第一頁
        loadMessages();

        // C. 啟動 WebSocket（輪詢等待 Echo 載入）
        initEchoWithRetry();
    });

    // =========================================================
    // 3. WebSocket 初始化（帶重試機制）
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
        // 公開頻道：畫面渲染用（貼文/回覆/刪除/按讚）
        window.Echo.channel('wall-channel')
            .listen('.message.created', (e) => {
                handleNewMessage(e.message);
            })

            .listen('.message.status.updated', (e) => {
                handleNewMessage(e.message);
            })

        
            .listen('.message.deleted', (e) => {
             handleDeletedMessage(e);
            })

            .listen('.message.liked', (e) => {
            handleLikeBroadcast(e);
            });
    
        // 🎯 私人頻道：只用來通知「我自己」的上傳狀態，不負責畫面渲染
        //    ⚠️ 事件名稱 '.upload.completed' / '.upload.failed' 需與後端 Event 對齊，
        //       後端 broadcastAs() 完成後在這裡確認名稱是否一致。
        if (window.currentUserId) {
           window.Echo.private('user.' + window.currentUserId)
               .listen('.upload.completed', (e) => {
                    console.log('📡 [私人頻道] 上傳完成通知', e);
                    window.pendingVideoUploadId = null;
                    const title = truncateTitle(e?.message?.content, '影片');
                    pushToast(`「${title}」發布成功`, 'success', 5000);
                })
                 .listen('.upload.failed', (e) => {
                    console.log('📡 [私人頻道] 上傳失敗通知', e);
                    window.pendingVideoUploadId = null;
                    pushToast(e?.message || '上傳失敗，請重新上傳', 'error', 5000);
                });
        }            
 
    }

    
    window.handleLikeBroadcast = function(e) {
    console.log("📡 [雷達成功攔截廣播] 收到別人的點讚訊號！包裹內容：", e);
    
    // 1. 解析目標 ID
    const targetId = Number(e.messageId ?? e.id);
    
    // 2. 升級改用 ?? 運算子，精準攔截數字 0
    const newCount = Number(e.likesCount ?? e.likes_count ?? 0);
    
    console.log(`[探針測試] 經過 ?? 判定後的 newCount 理論數值為: ${newCount}`);
    
    // 3. 同步中央記憶體
    if (window.globalMsgMap.has(targetId)) {
        const msg = window.globalMsgMap.get(targetId);
        msg.likes_count = newCount;
        console.log(`[記憶體同步] 已將地圖中的 ID: ${targetId} 讚數修正為: ${newCount}`);
    }
    
    // 4. 精準抹繪網頁 DOM 數字
    const countEl = document.getElementById(`lcount-${targetId}`);
    if (countEl) {
            countEl.textContent = newCount;
            console.log(`[DOM 抹繪] 已成功將網頁上的計數器更新為: ${newCount}`);
        }
    };
    
    
    window.handleDeletedMessage = function(e) {
        const messageId = Number(e?.messageId ?? e?.id ?? e?.message_id);
        const parentId = e?.parentId != null ? Number(e.parentId) : null;
        const rootId = e?.rootId != null ? Number(e.rootId) : null;

        if (!Number.isFinite(messageId)) {
            return;
        }

        const collectDescendants = (id) => {
            const node = window.globalMsgMap.get(id);
            if (!node || !Array.isArray(node.children)) return [];

            const ids = [];
            node.children.forEach(child => {
                ids.push(child.id);
                ids.push(...collectDescendants(child.id));
            });
            return ids;
        };

        const idsToRemove = new Set([messageId, ...collectDescendants(messageId)]);

        idsToRemove.forEach((id) => {
            const node = window.globalMsgMap.get(id);
            if (node && node.parent_id != null) {
                const parent = window.globalMsgMap.get(node.parent_id);
                if (parent && Array.isArray(parent.children)) {
                    parent.children = parent.children.filter(child => child.id !== id);
                }
            }

            window.globalMsgMap.delete(id);
        });

        const targetRootId = Number.isFinite(rootId)
            ? rootId
            : (Number.isFinite(parentId) ? parentId : messageId);

        const rootEl = document.getElementById(`msg-${targetRootId}`);

        if (targetRootId === messageId) {
            if (rootEl) {
                rootEl.remove();
            }
            return;
        }

        if (rootEl && window.globalMsgMap.has(targetRootId)) {
            rootEl.outerHTML = buildRootHTML(window.globalMsgMap.get(targetRootId));
        }
    };

    // =========================================================
    // 4. 統一處理新訊息入口
    //    WebSocket、submitPost、submitReply 全部走這裡
    //    ⚠️ 新模式下：後端在影片轉檔完成前「完全不會」廣播到 wall-channel，
    //    所以這裡實際上不會再收到 status === 'processing' 的訊息了。
    // =========================================================
    
    window.handleNewMessage = function(newMsg) {
        console.log("★★★★ 我改過 handleNewMessage 了 ★★★★");
        newMsg.id = Number(newMsg.id);
        newMsg.parent_id =
        newMsg.parent_id == null
            ? null
            : Number(newMsg.parent_id);

        // 去重防呆：已存在就跳過(更新既有訊息內容)
        if (window.globalMsgMap.has(newMsg.id)) {
            const existing = window.globalMsgMap.get(newMsg.id);
            const children = existing.children || [];
            // 舊的
            // Object.assign(existing, newMsg);
            //     ...existing,
            //     ...newMsg,
            //     children,
            // };

            // merged.id = Number(merged.id);
            // merged.parent_id = merged.parent_id == null ? null : Number(merged.parent_id);

            // 新的
            Object.assign(existing, newMsg);
            existing.children = children;
            existing.id = Number(existing.id);
            existing.parent_id = existing.parent_id == null ? null : Number(existing.parent_id);

            // 新的
            // 這裡要加
            if (existing.parent_id) {
                const parent = window.globalMsgMap.get(existing.parent_id);
                if (parent && parent.children) {
                    const idx = parent.children.findIndex(c => c.id === existing.id);
                    if (idx !== -1) parent.children[idx] = existing;
                }
            }
            // 舊的
            // window.globalMsgMap.set(merged.id, merged);

            // 新的
            window.globalMsgMap.set(existing.id, existing);

            // 舊的是merge.id
            const contentEl = document.getElementById(`content-${existing.id}`);
            if (contentEl) {
                const activeInput = contentEl.querySelector('input[name="content"], textarea');
                const isFocused = activeInput && document.activeElement === activeInput;
                const hasTyped = activeInput && activeInput.value.trim() !== '';

                if (!isFocused && !hasTyped) {
                    contentEl.innerText = existing.content ?? '';
                }
            }
            // 舊的
            // const rootId = findRootId(merged.parent_id ?? merged.id);
            // 新的
            const rootId = findRootId(existing.parent_id ?? existing.id);
            const rootEl = document.getElementById(`msg-${rootId}`);
            const rootMsg = window.globalMsgMap.get(rootId);

            if (rootEl && rootMsg) {
                const activeInputRoot = rootEl.querySelector('input[name="content"], textarea');
                const isFocusedRoot = activeInputRoot && document.activeElement === activeInputRoot;
                const hasTypedRoot = activeInputRoot && activeInputRoot.value.trim() !== '';

                if (!isFocusedRoot && !hasTypedRoot) {
                    rootEl.outerHTML = buildRootHTML(rootMsg);
                }
            }

            return;
        }

        newMsg.children = [];
        window.globalMsgMap.set(newMsg.id, newMsg);

        if (!newMsg.parent_id) {
            // 全新貼文：插到最頂端
            const list = document.getElementById('messages-list');
            if (list) list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));
        } else {
            // 回覆：找到根貼文並局部重繪
            const rootId = findRootId(newMsg.parent_id);
            const trueParent = window.globalMsgMap.get(newMsg.parent_id);
            

            if (trueParent) {
                if (!trueParent.children) trueParent.children = [];
                // 檢查是否重複，不重複才塞入
                if (!trueParent.children.some(c => c.id === newMsg.id)) {   
                    trueParent.children.push(newMsg);
                }
            }
            
            // 強制展開該根貼文的檢視狀態
            window.expandedSet.add(rootId);

            const rootEl = document.getElementById(`msg-${rootId}`);
            const rootMsg = window.globalMsgMap.get(rootId);
            

            if (rootEl && rootMsg) {
                // 防護：若使用者正在該卡片內打字，跳過重繪避免焦點丟失
                const activeInput = rootEl.querySelector('input[name="content"], textarea');
                const isFocused = activeInput && (document.activeElement === activeInput);
                const hasTyped = activeInput && activeInput.value.trim() !== '';

                if (!isFocused && !hasTyped) {
                    // 🛠️ 修正：原本這裡誤寫成 HTML(rootMsg)，已修正為 buildRootHTML(rootMsg)
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
        // const count = msg.children ? msg.children.length : 0;
        // 新的修改
        const count = msg.children ? countAllReplies(msg) : 0;


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

        // const branchBtn = hasChildren
        //     ? `<button class="hover:text-gray-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400" id="bbtn-${msg.id}" onclick="toggleBranch(${msg.id}, this)">▾ 收合</button>`
        //     : '';
        // 新的修改
        const replyCount = hasChildren ? countAllReplies(msg) : 0;

        const branchBtn = hasChildren
            ? `<button class="hover:text-gray-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400" id="bbtn-${msg.id}" onclick="toggleBranch(${msg.id}, this)">
                <span id="barrow-${msg.id}">▾</span> <span id="blabel-${msg.id}">收合</span>
            </button>`
            : '';

        // const childrenSection = hasChildren
        //     ? (depth < 3
        //         ? `<div id="branch-${msg.id}" class="ml-6 pl-4 border-l-2 border-gray-200 mt-1">${childrenHtml}</div>`
        //         : `<div id="branch-${msg.id}">${childrenHtml}</div>`)
        //     : '';
        // 新的修改
        const childrenSection = hasChildren
            ? (depth < 3
            ? `<div id="branch-${msg.id}" class="ml-6 pl-4 border-l-2 border-gray-200 mt-1" data-count="${replyCount}">${childrenHtml}</div>`
            : `<div id="branch-${msg.id}" data-count="${replyCount}">${childrenHtml}</div>`)
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
        // 影片的處理
        // ⚠️ 新模式下，其他使用者理論上不會再收到 status === 'processing' 的貼文
        //    （因為後端在轉檔完成前不會廣播），這兩段留著作為保險，不會被觸發到。
        if (msg.media_type === 'video') {
           // 壓縮處理中：顯示轉圈動畫
           if (msg.status === 'processing') {
             return `<div class="msg-media msg-media-processing">
                        <div class="spinner"></div>
                    </div>`;
            }

            // 壓縮失敗：顯示錯誤提示
            if (msg.status === 'failed') {
            return `<div class="msg-media msg-media-failed">
                        <p>⚠️ 影片轉檔失敗，請重新上傳</p>
                    </div>`;
            }

            // 處理完成：正常渲染 video 標籤
            if (msg.media_type === 'video' && msg.video_path) {
               const isS3 = msg.video_path.startsWith('videos/') || !msg.video_path.startsWith('storage/');
               const videoUrl = isS3 ? `${s3BaseUrl}${msg.video_path}` : `/storage/${msg.video_path}`;
               return `<div class="msg-media"><video controls preload="metadata"><source src="${videoUrl}" type="video/mp4">您的瀏覽器不支援影片播放。</video></div>`;
            }
        }

        return '';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // =========================================================
    // 9. 🆕 新增整節：上傳 Toast 通知 Helper 函式
    //    （原本這裡完全沒有這節，是取代進度條 Helper 新增的）
    // =========================================================
    let toastHideTimer = null;

    // 🆕 新增：判斷檔案是不是影片，用來決定要不要套用「一次只能傳一支」的鎖
    function isVideoFile(file) {
        return !!(file && file.type && file.type.startsWith('video/'));
    }

    function isImageFile(file) {
         return !!(file && file.type && file.type.startsWith('image/'));
    }
     
    function truncateTitle(content, fallback) {
        const text = (content || '').trim();
        if (!text) return fallback;
        return text.length > 20 ? text.slice(0, 20) + '...' : text;
    }
 
    // type: 'info'/'processing'（轉圈）｜'success'/'error'（emoji）
    function pushToast(message, type = 'info', autoHideMs = null) {
     const stack = document.getElementById('toast-stack');
     if (!stack) return null;

     const el = document.createElement('div');
     el.className = 'toast-item';

     const isDone = type === 'success' || type === 'error';
     el.innerHTML = `
         <div class="toast-icon">
             ${isDone
                 ? `<span>${type === 'success' ? '✅' : '⚠️'}</span>`
                 : `<div class="toast-spinner"></div>`}
         </div>
         <div class="toast-body">${message}</div>
         <button type="button" class="toast-close" aria-label="關閉">✕</button>
     `;
     if (isDone) el.classList.add(type);

     stack.appendChild(el);
     requestAnimationFrame(() => el.classList.add('show'));

     const remove = () => {
         el.classList.remove('show');
         el.classList.add('fade-out');
         setTimeout(() => el.remove(), 250);
     };

     el.querySelector('.toast-close').addEventListener('click', remove);
     if (autoHideMs) setTimeout(remove, autoHideMs);

     return el;
}

    // =========================================================
    // 9. 互動事件函式
    // =========================================================
    // 共用的遞迴計算函式
    function countAllReplies(msg) {
    if (!msg.children || msg.children.length === 0) return 0;
    let total = msg.children.length;
    msg.children.forEach(child => {
        total += countAllReplies(child);
    });
    return total;
    }

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

    // window.toggleBranch = function(msgId, btn) {
    //     const branch = document.getElementById(`branch-${msgId}`);
    //     if (!branch) return;
    //     const isHidden = branch.classList.contains('hidden');
    //     if (isHidden) {
    //         branch.classList.remove('hidden');
    //         btn.textContent = '▾ 收合';
    //     } else {
    //         branch.classList.add('hidden');
    //         btn.textContent = '▸ 展開';
    //     }
    // };
    // 新的修改
    window.toggleBranch = function(msgId, btn) {
        const branch = document.getElementById(`branch-${msgId}`);
        if (!branch) return;

        const arrow = document.getElementById(`barrow-${msgId}`);
        const label = document.getElementById(`blabel-${msgId}`);
        const count = branch.dataset.count || 0;

        const isHidden = branch.classList.contains('hidden');
        if (isHidden) {
            branch.classList.remove('hidden');
            if (arrow) arrow.textContent = '▾';
            if (label) label.textContent = '收合';
        } else {
            branch.classList.add('hidden');
            if (arrow) arrow.textContent = '▸';
            if (label) label.textContent = `查看 ${count} 則回覆`;
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

    // 🔄 submitReply 改用 XHR + Toast
    window.submitReply = function(e, rootId) {
        e.preventDefault();
        const form = e.target;
        const contentInput = form.querySelector('input[name="content"]');
        const fileInput = form.querySelector('input[type="file"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        const file = fileInput && fileInput.files && fileInput.files[0];
        const hasFile = !!file;
        // 🐛 修正 bug：原本這裡「上面」有一行 console.log('...', hasFile)
        //    印在 const hasFile 宣告「之前」，會直接噴 ReferenceError（TDZ）。
        //    已經把 hasFile 的宣告移到最前面，並拿掉那行過早的 console.log。

        // 1. 防呆：沒文字也沒檔案，不處理
        if (!contentInput.value.trim() && !hasFile) {
            alert('請輸入回覆內容或上傳媒體');
            return;
        }

        // 2. 防呆：檔案大小限制 10MB
        if (hasFile && file.size > 10 * 1024 * 1024) {
            alert('檔案太大，最大限制為 10MB');
            fileInput.value = '';
            return;
        }

        // 🆕 新增：影片上傳鎖，若已有一支影片在後端處理中，擋下這次「影片」送出
        //    （純文字、圖片完全不受影響）
        if (hasFile && isVideoFile(file) && window.pendingVideoUploadId) {
            alert('您有一支影片正在處理中，請稍候完成後再上傳下一支影片');
            return;
        }


        // 防重複點擊鎖定
        if (submitBtn) submitBtn.disabled = true;

        const msgId = form.querySelector('input[name="parent_id"]')?.value;

        // 🔄 取代：原本這裡展開進度條 DOM，現在改成呼叫 showToast()
        if (hasFile) {
            showToast('檔案上傳中...', 'info');
        }
 
        const xhr = new XMLHttpRequest();
        xhr.open('POST', "{{ route('messages.store') }}", true);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
        xhr.setRequestHeader('Accept', 'application/json');

        // 🔄 取代：原本這裡逐步更新進度條百分比，現在只在傳輸「完成」那一刻換文字成「發布中...」
        if (hasFile && xhr.upload) {
            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable && event.loaded === event.total) {
                    updateToastMessage('發布中...');
                }
            };
        }
 
        xhr.onload = function() {
            if (submitBtn) submitBtn.disabled = false;
 
            let data = {};
            try {
                data = JSON.parse(xhr.responseText);
            } catch (err) {
                console.error('JSON 解析失敗:', err);
            }
 
            if (xhr.status === 419) {
                alert('登入已過期，頁面將自動重新整理');
                window.location.reload();
                return;
            }
 
            if (xhr.status === 422) {
                const firstError = data.errors
                    ? Object.values(data.errors)[0][0]
                    : (data.message || '發生錯誤，請重新確認內容');
                alert(firstError);
                if (hasFile) { showToast(firstError, 'error'); hideToastWithDelay(5000); }
                return;
            }
            
            if (xhr.status >= 200 && xhr.status < 300 && data.success && data.data) {
                form.reset();
                // ✅ 補回：contentInput.blur()，你更早之前的「乾淨版」不小心漏掉這行，
                //    沒有它的話，若輸入框仍是 focus 狀態，handleNewMessage 的「使用者正在打字」
                //    保護機制會誤判，新回覆送出後畫面不會立刻更新。
                contentInput.blur();
                // ✅ 補回：清空媒體預覽，同樣是之前「乾淨版」漏掉的部分
                if (msgId) {
                    const preview = document.getElementById(`fprev-${msgId}`);
                    if (preview) preview.innerHTML = '';
                }
 
                const isProcessingVideo = data.data.media_type === 'video' && data.data.status === 'processing';
 
                if (isProcessingVideo) {
                    // 🆕 新增：領號碼牌，Toast 停在「處理中」，等私人頻道通知才會被清掉
                    window.pendingVideoUploadId = data.data.id;
                    showToast('影片處理中...', 'processing');
                } else {
                    handleNewMessage(data.data);
                    if (hasFile) {
                        showToast('發布成功！', 'success');
                        hideToastWithDelay(5000);
                    }
                }
            } else {
                if (typeof loadMessages === 'function') loadMessages(true);
                form.reset();
                if (hasFile) {
                    showToast(data.message || '發送失敗，請稍後再試', 'error');
                    hideToastWithDelay(5000);
                }
            }
        };

        xhr.onerror = function() {
            if (submitBtn) submitBtn.disabled = false;
            if (hasFile) {
                showToast('網路異常，請稍後再試', 'error');
                hideToastWithDelay(5000);
            } else {
                alert('網路異常，請稍後再試');
            }
        };
        // 🐛 修正 bug：原本這裡呼叫 cleanupUI()，但 function cleanupUI() {...} 整段被註解掉了，
        //    一樣會噴 ReferenceError。已經拿掉這個依賴，改成上面 inline 處理解鎖與 Toast。

        xhr.send(new FormData(form));
    }; 

    // 🔄 取代：submitPost 邏輯與 submitReply 對齊，同步改用 Toast
    window.submitPost = function(e) {
        e.preventDefault();
        const form = e.target;
        const fi = form.querySelector('input[type="file"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        const file = fi && fi.files && fi.files[0];
        const hasFile = !!file;

        if (hasFile && file.size > 10 * 1024 * 1024) {
            alert('檔案太大，最大限制為 10MB');
            fi.value = '';
            return;
        }

        // 🆕 分開檢查：影片鎖跟圖片鎖互相獨立，兩邊各自限制一次一個
        if (hasFile && isVideoFile(file) && window.pendingVideoUploadId) {
            alert('您有一支影片正在處理中，請稍候完成後再上傳下一支影片');
            return;
        }
        if (hasFile && isImageFile(file) && window.pendingImageUploadId) {
            alert('您有一張圖片正在處理中，請稍候完成後再上傳下一張圖片');
            return;
        }

        if (submitBtn) submitBtn.disabled = true;

        // 🆕 送出當下就推一則「動作提示」，2.5 秒後自動消失，不等實際完成
        if (hasFile) {
           if (isVideoFile(file)) {
              pushToast('影片處理中...', 'processing', 2500);
            } else if (isImageFile(file)) {
                window.pendingImageUploadId = 'pending'; // 佔位，onload 時清除
                pushToast('圖片上傳中...', 'processing', 2500);
            }
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', "{{ route('messages.store') }}", true);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onload = function() {
           if (submitBtn) submitBtn.disabled = false;
           if (isImageFile(file)) window.pendingImageUploadId = null; // 圖片同步完成，立刻解鎖

           let data = {};
           try { data = JSON.parse(xhr.responseText); } catch (err) { console.error('JSON 解析失敗:', err); }

           if (xhr.status === 419) {
                alert('登入已過期，頁面將自動重新整理');
                window.location.reload();
                return;
            }

            if (xhr.status === 409) {
                const msg = data.message || '請稍候完成後再上傳';
                alert(msg);
                pushToast(msg, 'error', 5000);
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300 && data.success && data.data) {
                form.reset();
                const preview = document.getElementById('fprev-main');
                if (preview) preview.innerHTML = '';

                const isProcessingVideo = data.data.media_type === 'video' && data.data.status === 'processing';

                if (isProcessingVideo) {
                   window.pendingVideoUploadId = data.data.id;
                   // 不用管 toast，動作提示已經在跑自己的 2.5 秒計時器
                } else {
                    handleNewMessage(data.data);
                    // 🆕 圖片（或純文字帶檔案的情況）：立刻推完成通知
                    if (hasFile) {
                        const fallback = data.data.media_type === 'image' ? '圖片' : '影片';
                        const title = truncateTitle(data.data.content, fallback);
                        pushToast(`「${title}」發布成功`, 'success', 5000);
                    }
                }
            } else {
               if (typeof loadMessages === 'function') loadMessages(true);
               form.reset();
               if (hasFile) pushToast(data.message || '發送失敗，請稍後再試', 'error', 5000);
            }
        };

        xhr.send(new FormData(form));
    };   
        
    //新的修改
    window.deleteMsg = function(id) {
    if (!confirm('確定要刪除這則訊息嗎？')) return;
    id = Number(id);
    fetch(`/messages/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) {
            const msg = window.globalMsgMap.get(id);

            // 往上一層一層找，直到找到真正沒有 parent_id 的頂層留言
            let rootId = id;
            let cursor = msg;
            while (cursor && cursor.parent_id != null) {
                const parentNode = window.globalMsgMap.get(cursor.parent_id);
                if (!parentNode) break;
                rootId = parentNode.id;
                cursor = parentNode;
            }

            handleDeletedMessage({
                messageId: id,
                parentId: msg?.parent_id ?? null,
                rootId: rootId
            });
        }
    });
};

    window.editMsg = function(id) {
    const p = document.getElementById(`content-${id}`);
    if (!p) return;
    const orig = p.innerText;
    p.innerHTML = `<textarea id="edit-ta-${id}" rows="2" class="w-full text-sm border border-gray-300 rounded-lg p-2 resize-none">${orig}</textarea>
        <div class="flex gap-2 mt-1">
            <button onclick="saveEdit(${id})" class="text-xs bg-blue-500 text-white px-3 py-1 rounded-lg border-none cursor-pointer">儲存</button>
            <button onclick="cancelEdit(${id}, '${orig.replace(/'/g, "\\'")}')" class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded-lg border-none cursor-pointer">取消</button>
        </div>`;
};

window.cancelEdit = function(id, orig) {
    const p = document.getElementById(`content-${id}`);
    if (p) p.innerText = orig;
};

    window.saveEdit = function(id) {
    id = Number(id);
    const val = document.getElementById(`edit-ta-${id}`)?.value;
    if (!val) return;
    fetch(`/messages/${id}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ content: val }),
    }).then(r => r.json()).then(d => {
        if (d.success) {
            // 只更新這則訊息的文字內容，不重載整頁
            const contentEl = document.getElementById(`content-${id}`);
            if (contentEl) contentEl.innerText = val;

            // 同步更新記憶體
            if (window.globalMsgMap.has(id)) {
                window.globalMsgMap.get(id).content = val;
            }
        } else {
            alert('編輯失敗，請稍後再試');
        }
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
            // ✅ 收到回應後的最後一刻，才抓取網頁上當下最新、活著的節點
            const btn = document.querySelector(`#msg-${id} .like-btn, [data-id="${id}"] .like-btn`);
            const countEl = document.getElementById(`lcount-${id}`);

            // ✅ 防空檢查：若在等待期間留言被刪除或找不到節點，直接結束不執行，防止程式當機
            if (!btn || !countEl) {
                console.warn(`[探針 4][📢 警告 - 程式在此中斷] 原因：在畫面上找不到對應的按鈕或計數器節點！`);
                return;
            }

            if (d.likes_count !== undefined) {

                console.log(`[探針 5][執行路線 A] 後端有給數字。準備將計數器改為: ${d.likes_count}, 按鈕點讚狀態: ${d.liked}`);

                countEl.textContent = d.likes_count;
                if (d.liked) {
                    btn.classList.add('text-pink-500', 'font-bold');
                    btn.classList.remove('text-gray-400');
                } else {
                    btn.classList.remove('text-pink-500', 'font-bold');
                    btn.classList.add('text-gray-400');
                }

                // 同步更新全域記憶體資料，避免下次被 WebSocket 重繪洗掉
                if (window.globalMsgMap.has(id)) {
                    console.log(`[探針 5-1] 成功找到 globalMsgMap 中的資料，正在同步記憶體狀態...`);
                    const msg = window.globalMsgMap.get(id);
                    msg.likes_count = d.likes_count;
                    msg.is_liked = d.liked;
                
                } else {
                    console.warn(`[探針 5-2][📢 警告] globalMsgMap 裡面竟然找不到 ID: ${id} 的留言！`);
                }
                } else {
                    const isLiked = btn.classList.contains('text-pink-500');
                    let count = parseInt(countEl.textContent) || 0;
                    if (isLiked) {
                        count = Math.max(0, count - 1);
                        btn.classList.remove('text-pink-500', 'font-bold');
                        btn.classList.add('text-gray-400');
                    } else {
                        count += 1;
                        btn.classList.add('text-pink-500', 'font-bold');
                        btn.classList.remove('text-gray-400');
                    }
                    countEl.textContent = count;

                    // 同步更新全域記憶體的模擬狀態
                    if (window.globalMsgMap.has(id)) {
                        const msg = window.globalMsgMap.get(id);
                        msg.likes_count = count;
                        msg.is_liked = !isLiked;
                    }
            }
        })
        .catch(err => {
         console.error('點讚發送失敗:', err);
    });
};

// 🔄 取代：檔案大小上限從原本的 50MB 改成 10MB，跟 submitPost/submitReply 的送出限制對齊
//    （原本這裡是 50MB，跟送出時的 10MB 限制不一致，選了 30MB 檔案預覽會過但送出會被擋，體驗矛盾）
    window.previewMedia = function(input, previewId) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            alert('檔案太大，最大限制為 10MB');
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
    </script>
</x-app-layout> 