
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
    </style>

    <script>
    // =========================================================
    // 1. 全域狀態初始化（統一用 window，不重複宣告）
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
        window.Echo.channel('wall-channel')
            .listen('.message.created', (e) => {
                handleNewMessage(e.message);
            })

            .listen('.message.status.updated', (e) => {
                handleNewMessage(e.message);
            })

            // 刪除監聽器
            .listen('.message.deleted', (e) => {
             handleDeletedMessage(e);
            })

            .listen('.message.liked', (e) => {
            handleLikeBroadcast(e);
            });
            
            
    }

    // 舊的
    // window.handleLikeBroadcast = function(e) {
    // console.log("📡 [雷達成功攔截廣播] 收到別人的點讚訊號！包裹內容：", e);
    
    // // 1. 解析目標 ID
    // const targetId = Number(e.messageId ?? e.id);
    
    // // 2. 升級改用 ?? 運算子，精準攔截數字 0
    // const newCount = Number(e.likesCount ?? e.likes_count ?? 0);
    
    // console.log(`[探針測試] 經過 ?? 判定後的 newCount 理論數值為: ${newCount}`);
    
    // // 3. 同步中央記憶體
    // if (window.globalMsgMap.has(targetId)) {
    //     const msg = window.globalMsgMap.get(targetId);
    //     msg.likes_count = newCount;
    //     console.log(`[記憶體同步] 已將地圖中的 ID: ${targetId} 讚數修正為: ${newCount}`);
    // }
    
    // // 4. 精準抹繪網頁 DOM 數字
    // const countEl = document.getElementById(`lcount-${targetId}`);
    // if (countEl) {
    //         countEl.textContent = newCount;
    //         console.log(`[DOM 抹繪] 已成功將網頁上的計數器更新為: ${newCount}`);
    //     }
    // };

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
    // =========================================================
    
    window.handleNewMessage = function(newMsg) {
        console.log("★★★★ 我改過 handleNewMessage 了 ★★★★");
        newMsg.id = Number(newMsg.id);
        newMsg.parent_id =
        newMsg.parent_id == null
            ? null
            : Number(newMsg.parent_id);
        console.log(typeof newMsg.id);
        console.log(typeof newMsg.parent_id);
        console.log(
        [...window.globalMsgMap.keys()]
            .slice(0,10)
            .map(k => [k, typeof k])
        );

        console.log("parent_id =", newMsg.parent_id);

        // 去重防呆：已存在就跳過
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
                console.log("===== 插入後 =====");
                console.log(document.getElementById(`msg-${newMsg.id}`));
                console.log(document.getElementById('messages-list').innerHTML);
        } else {
            // 回覆：找到根貼文並局部重繪

            console.log("========== handleNewMessage ==========");
            console.log("newMsg =", newMsg);
            const rootId = findRootId(newMsg.parent_id);
            console.log("rootId =", rootId);
            const trueParent = window.globalMsgMap.get(newMsg.parent_id);
            console.log("trueParent =", trueParent);
            if (trueParent) {
                console.log("children before =", trueParent.children);
                if (!trueParent.children) trueParent.children = [];
                // 檢查是否重複，不重複才塞入
                // 舊的
                // if (!trueParent.children.some(c => c.id === newMsg.id)) {
                // 新的
                if (!trueParent.children.some(c => c.id === existing.id)) {    
                    trueParent.children.push(newMsg);
                    console.log("children after =", trueParent.children);
                }
            }
            
            // 強制展開該根貼文的檢視狀態
            window.expandedSet.add(rootId);

            const rootEl = document.getElementById(`msg-${rootId}`);
            console.log("rootEl =", rootEl);
            const rootMsg = window.globalMsgMap.get(rootId);
            console.log("rootMsg =", rootMsg);

            if (rootEl && rootMsg) {
                // 防護：若使用者正在該卡片內打字，跳過重繪避免焦點丟失
                const activeInput = rootEl.querySelector('input[name="content"], textarea');
                const isFocused = activeInput && (document.activeElement === activeInput);
                const hasTyped = activeInput && activeInput.value.trim() !== '';

                if (!isFocused && !hasTyped) {
                    // 核心重繪：此時記憶體結構已正確，深層留言將會完美渲染
                    rootEl.outerHTML = buildRootHTML(rootMsg);
                }
            }
        }
    };
    // 刪除訊息資料並廣播
    window.handleDeletedMessage = function(messageId) {
        messageId = Number(messageId);
        console.log(`[刪除廣播] 收到刪除訊號，ID: ${messageId}`);

       // 1. 從全域記憶體移除
       if (window.globalMsgMap.has(messageId)) {
        const msg = window.globalMsgMap.get(messageId);
        
        // 2. 如果是子留言，從父留言的 children 陣列移除
        if (msg.parent_id) {
            const parent = window.globalMsgMap.get(msg.parent_id);
            if (parent && parent.children) {
                parent.children = parent.children.filter(c => c.id !== messageId);
            }
        }

        // 3. 從全域 Map 刪除
        window.globalMsgMap.delete(messageId);
    }
    
    // 4. 從 DOM 移除
    const element = document.getElementById(`msg-${messageId}`);
    if (element) {
        element.remove();
        console.log(`[DOM 移除] 成功移除 ID: ${messageId} 的訊息 DOM`);
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

    // ✅ 完美修正後的 submitReply 函式
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

        // 防重複點擊鎖定
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
            console.log("收到資料：", d);
            if (d.success && d.data) {
                // 1. 先清空表單，釋放 hasTyped 狀態
                form.reset();
                if (msgId) {
                    const preview = document.getElementById(`fprev-${msgId}`);
                    if (preview) preview.innerHTML = '';
                }
                console.log("開始更新畫面");
                // 2. 再安全觸發 DOM 重繪
                handleNewMessage(d.data);

                console.log("更新完成");
            }
        })
        .catch(err => {
            console.error('發送失敗:', err);
        })
        .finally(() => {
            // 解鎖按鈕
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
        .catch(err => console.error('貼文失敗:', err))
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
        const p = document.getElementById(`content-${id}`);
        if (!p) return;
        const orig = p.innerText;
        p.innerHTML = `<textarea id="edit-ta-${id}" rows="2" class="w-full text-sm border border-gray-300 rounded-lg p-2 resize-none">${orig}</textarea>
            <div class="flex gap-2 mt-1">
                <button onclick="saveEdit(${id})" class="text-xs bg-blue-500 text-white px-3 py-1 rounded-lg border-none cursor-pointer">儲存</button>
                <button onclick="loadMessages(true)" class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded-lg border-none cursor-pointer">取消</button>
            </div>`;
    };

    window.saveEdit = function(id) {
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
            if (d.success) loadMessages(true);
        });
    };

    window.toggleLike = function(id) {
        id = Number(id);
        
        console.log(`[探針 1][點擊觸發] 準備發送點讚請求，目標 ID: ${id}, 型態: ${typeof id}`);
        // 🚀 直接發送請求，不提前抓取可能變動的 DOM 節點
        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(d => {
            console.log(`[探針 2][後端回應抵達] 收到後端回傳物件:`, d);

            // ✅ 收到回應後的最後一刻，才抓取網頁上當下最新、活著的節點
            const btn = document.querySelector(`#msg-${id} .like-btn, [data-id="${id}"] .like-btn`);
            const countEl = document.getElementById(`lcount-${id}`);

            console.log(`[探針 3][DOM 節點盤查]`, {
            "搜尋目標按鈕": `#msg-${id} .like-btn`,
            "抓取到的按鈕結果 (btn)": btn,
            "搜尋目標計數器": `lcount-${id}`,
            "抓取到的計數器結果 (countEl)": countEl
            });

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
                    // 📡 探針 6：確認走入路線 B (後端沒給數字，前端自立自強模擬)
                    console.log(`[探針 6][執行路線 B] 後端沒給數字，啟動前端模擬計算`);

                    // 路線 B：後端未回傳數據時的備用前端模擬邏輯
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

            // 📡 探針 7：確認整套點讚邏輯全部執行完畢
            console.log(`[探針 7][流程圓滿結束] ID: ${id} 的點讚畫面更新動作已全部派發完畢。`);
        })
        .catch(err => {
        console.error('[探針 8][❌ 嚴重錯誤] 點讚發送失敗，錯誤詳細訊息:', err);
    });
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
    </script>
</x-app-layout> 