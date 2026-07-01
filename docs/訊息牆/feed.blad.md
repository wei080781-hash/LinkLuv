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
    /* 新增：回覆分支線樣式 */
    .reply-branch {
        position: relative;
    }

    .reply-branch::before {
        content: '';
        position: absolute;
        left: -16px;
        /* 連接父容器的垂直線 */
        top: 22px;
        /* 對準頭像垂直中心 */
        width: 16px;
        /* 線條長度 */
        height: 2px;
        /* 線條粗細 */
        background-color: #e5e7eb;
        /* border-gray-200 的顏色 */
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
    /* 讓 HTML 結構一目瞭然 */
    main { background-color: red !important; }
    main > div { background-color: blue !important; }
    </style>

    <script>
    // 💡 1. 放在這裡！將 Laravel 目前登入的 user ID 轉成 JavaScript 全域變數
    window.currentUserId = {{ auth()->id() ?? 'null' }};    

    /* ... (資料處理邏輯保持不變) ... */
    const expandedSet = new Set();
    let globalMsgMap = new Map();

    function renderMessages(messages) {
        const list = document.getElementById('messages-list');
        list.innerHTML = '';
        globalMsgMap = new Map();
        messages.forEach(m => globalMsgMap.set(m.id, {
            ...m,
            children: []
        }));
        const roots = [];
        messages.forEach(m => {
            if (!m.parent_id) roots.push(globalMsgMap.get(m.id));
            else {
                const parent = globalMsgMap.get(m.parent_id);
                if (parent) parent.children.push(globalMsgMap.get(m.id));
                else roots.push(globalMsgMap.get(m.id));
            }
        });
        roots.forEach(root => list.insertAdjacentHTML('beforeend', buildRootHTML(root)));
    }

    function buildRootHTML(msg) {
        const hasReplies = msg.children && msg.children.length > 0;
        const isOpen = expandedSet.has(msg.id);
        const count = msg.children ? msg.children.length : 0;

        const avatarCol = hasReplies ?
            `<div class="flex flex-col items-center flex-shrink-0 w-10">
                   <img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                   <div class="w-0.5 flex-1 bg-gray-300 mt-1 rounded-full min-h-3"></div>
               </div>` :
            `<img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">`;

        const toggleBtn = hasReplies ?
            `<button onclick="toggleReplies(${msg.id}, ${count})" id="tbtn-${msg.id}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 mt-1 select-none bg-transparent border-none cursor-pointer p-0"><span class="toggle-arrow ${isOpen ? 'rotate-180' : ''}">▼</span><span id="tlabel-${msg.id}">${isOpen ? '隱藏回覆' : `查看 ${count} 則回覆`}</span></button>` :
            '';

        const repliesHtml = hasReplies 
        ? msg.children.map(c => buildReplyHTML(c, msg.id, new Set(), 1)).join('') 
        : '';

        // 💡 2. 動態判斷：如果是這則訊息的主人，才顯示按鈕
        const ownerButtons = (window.currentUserId && msg.user_id == window.currentUserId) ? `
            <button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer  p-0 text-xs text-red-300">刪除</button>
            <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0   text-xs text-gray-400">編輯</button>
            ` : '';

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
        const branchBtn = hasChildren ?
            `<button class="hover:text-gray-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400" id="bbtn-${msg.id}" onclick="toggleBranch(${msg.id}, this)">▾ 收合</button>` :
            '';
        const replyingTo = buildReplyingToTag(msg.parent_id, rootId);
        const timeLabel = buildTimeLabel(msg.created_at);
        const childrenSection = hasChildren
        ? (depth < 3
            ? `<div id="branch-${msg.id}" class="ml-6 pl-4 border-l-2 border-gray-200 mt-1">${childrenHtml}</div>`
            : `<div id="branch-${msg.id}">${childrenHtml}</div>`)
        : '';


        // 💡 3. 子留言也做同樣的擁有者動態判斷
        const ownerButtons = (window.currentUserId && msg.user_id == window.currentUserId) ? `
            <button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer p-0 text-xs text-red-300">刪除</button>
            <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">編輯</button>
        ` : '';

        // 加入 reply-branch class 以觸發 CSS 偽元素
        return `
        <div id="msg-${msg.id}" class="reply-branch relative pt-2.5" data-id="${msg.id}" data-parent-id="${msg.parent_id || ''}">
            <div class="flex items-start gap-2">
                <img src="${msg.user.profile_photo_url}" class="w-7 h-7 rounded-full object-cover flex-shrink-0 mt-0.5 relative z-10">
                <div class="flex-1 min-w-0">
                    <div class="msg-bubble bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl px-3 py-2">
                        <div class="flex items-baseline gap-1 flex-wrap mb-0.5">
                            <span class="text-xs font-bold text-gray-700">${escHtml(msg.user.name)}</span>
                            ${replyingTo}
                            <span class="text-xs text-gray-400 ml-auto">${timeLabel}</span>
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

    /* ... (其餘函式維持不變) ... */
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
        const parent = globalMsgMap.get(parentId);
        if (!parent) return '';

        // 取前 20 個字作為預覽
        const preview = parent.content ? parent.content.slice(0, 20) + (parent.content.length > 20 ? '...' : '') : '';
        return `
        <span class="text-xs text-gray-400">回覆</span> 
        <span class="text-xs font-semibold text-blue-500 cursor-pointer hover:underline" 
              onclick="scrollToMsg(${parentId})">@${escHtml(parent.user.name)}</span>
        <span class="text-xs text-gray-300">·</span>
        <span class="text-xs text-gray-400 cursor-pointer hover:text-blue-400 hover:underline italic max-w-32 truncate inline-block align-bottom"
              onclick="scrollToMsg(${parentId})"
              title="${escHtml(preview)}">「${escHtml(preview)}」</span>
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
        // 🚀 已完美對接你們的 AWS S3 儲存桶網址
        const s3BaseUrl = 'https://linkluv-media-bucket.s3.ap-east-2.amazonaws.com/';
        if (msg.media_type === 'image' && msg.image_path) {
           // 💡 判斷路徑：如果是新資料走 S3 就補上 S3 網域，如果是舊資料本地暫存就走 /storage/ 
           const isS3 = msg.image_path.startsWith('images/') || !msg.image_path.startsWith('storage/');
           const imgUrl = isS3 ? `${s3BaseUrl}${msg.image_path}` : `/storage/${msg.image_path}`;
           return `<div class="msg-media"><img src="${imgUrl}" onclick="openLightbox('image','${imgUrl}')"></div>`;
        }
        if (msg.media_type === 'video' && msg.video_path) {
            // 💡 影片經過壓縮後也上傳到 S3 (路徑為 videos/xxx.mp4)，同樣補上 S3 網域
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
            expandedSet.delete(rootId);
        } else {
            wrap.classList.add('expanded');
            if (label) label.textContent = '隱藏回覆';
            if (arrow) arrow.classList.add('rotate-180');
            expandedSet.add(rootId);
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
                expandedSet.add(rootId);
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
        if (!contentInput.value.trim() && (!fileInput || !fileInput.files.length)) {
            alert('請輸入回覆內容或上傳媒體');
            return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(() => {
            expandedSet.add(rootId);
            loadMessages(true);
        });
    };
    window.submitPost = function(e) {
        e.preventDefault();
        const form = e.target;
        const fi = form.querySelector('input[type="file"]');

        // 1. 檔案大小檢查
        if (fi?.files.length > 0 && fi.files[0].size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            fi.value = '';
            return;
        }

        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(r => r.json())
        .then(d => {
            // 2. 判斷後端有沒有傳回新訊息的資料 (d.data)
           if (d.success && d.data) {
               const newMsg = d.data;
               const list = document.getElementById('messages-list');

               // 初始化子留言結構，並塞入全域 Map 確保後續互動（如點讚、回覆）正常運作
               newMsg.children = [];
               globalMsgMap.set(newMsg.id, newMsg);

               // ★ 核心改動：用 afterbegin 讓這則新 HTML 直接插在列表的最前面（第一項）
               list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));

               form.reset();

               // 如果你有做上傳預覽，發送成功後順便清空它
               const preview = document.getElementById('fprev-main'); // 或者是你主發文框的預覽 ID
               if (preview) preview.innerHTML = '';
        } else {
             // 防呆機制：如果後端目前還沒回傳 data，就先走原本的重載模式
             loadMessages(true);
             form.reset();
        }
    });
};              

    window.deleteMsg = function(id) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;
        fetch(`/messages/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages();
        });
    };
    window.editMsg = function(id) {
        const p = document.getElementById(`content-${id}`);
        if (!p) return;
        const orig = p.innerText;
        p.innerHTML =
            `<textarea id="edit-ta-${id}" rows="2" class="w-full text-sm border border-gray-300 rounded-lg p-2 resize-none">${orig}</textarea><div class="flex gap-2 mt-1"><button onclick="saveEdit(${id})" class="text-xs bg-blue-500 text-white px-3 py-1 rounded-lg border-none cursor-pointer">儲存</button><button onclick="loadMessages()" class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded-lg border-none cursor-pointer">取消</button></div>`;
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
            body: JSON.stringify({
                content: val
            }),
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages();
        });
    };
    window.toggleLike = function(id) {
        // 1. 自動抓取這則訊息對應的按鈕與數字欄位
        const btn = document.querySelector(`#msg-${id} .like-btn, [data-id="${id}"] .like-btn`);
        const countEl = document.getElementById(`lcount-${id}`);
        if (!btn || !countEl) return;

        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
        })
        .then(r => r.json())
        .then(d => {
          // 2. 如果後端有回傳最新的 likes_count 與 is_liked 狀態
          if (d.likes_count !== undefined) {
             countEl.textContent = d.likes_count;
             if (d.is_liked) {
                 btn.classList.add('text-pink-500', 'font-bold');
                 btn.classList.remove('text-gray-400');
            } else {
                btn.classList.remove('text-pink-500', 'font-bold');
                btn.classList.add('text-gray-400');
            }
            
            // 同步更新全域 Map 緩存，防止未來意外觸發重繪時狀態倒退
            if (globalMsgMap.has(id)) {
                const msg = globalMsgMap.get(id);
                msg.likes_count = d.likes_count;
                msg.is_liked = d.is_liked;
            }
        } else {
            // 備用防呆：如果後端只回傳 { success: true }，前端就自己做加減切換
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
            
            if (globalMsgMap.has(id)) {
               const msg = globalMsgMap.get(id);
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
        el.innerHTML = file.type.startsWith('image/') ? `<img src="${url}" class="max-w-40 rounded-xl mt-1">` :
            `<video src="${url}" controls class="max-w-48 rounded-xl mt-1"></video>`;
    };
    window.openLightbox = function(type, src) {
        document.getElementById('lightbox-content').innerHTML = type === 'image' ? `<img src="${src}">` :
            `<video src="${src}" controls autoplay></video>`;
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
        // 找到目標訊息，展開它所在的 replies-wrapper
        const rootWrap = document.querySelector(`[data-id="${msgId}"]`)?.closest('.replies-wrapper');
        if (rootWrap && !rootWrap.classList.contains('expanded')) {
        rootWrap.classList.add('expanded');
    }

    // 用 data-id 取代 id 避免重複選到
    const el = document.querySelector(`[data-id="${msgId}"]`);
    if (!el) return;
    // 清除舊高亮
    document.querySelectorAll('.msg-highlight').forEach(e => e.classList.remove('msg-highlight'));
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // 只高亮 bubble，不影響子元素
    const bubble = el.querySelector(':scope > .flex > .flex-1 > .msg-bubble, :scope > div > .flex-1 > .msg-bubble');
    const target = bubble || el;
    target.classList.add('msg-highlight');
    setTimeout(() => target.classList.remove('msg-highlight'), 1500);
    };


// ==========================================
// 請完整替換這一段，確保括號完美對齊
// ==========================================
let currentPage = 1;
let isLoading = false;
let hasMore = true;

const sentinel = document.getElementById('scroll-sentinel');
const loadingIndicator = document.getElementById('loading-indicator');

const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && !isLoading && hasMore) {
        loadingIndicator.classList.remove('hidden');
        loadMessages();
    }
}, { rootMargin: '0px 0px 100px 0px' });

observer.observe(sentinel);

function loadMessages(reset = false) {
    if (isLoading || (!hasMore && !reset)) return;

    if (reset) {
        currentPage = 1;
        hasMore = true;
        document.getElementById('messages-list').innerHTML = '';
        globalMsgMap = new Map();
    }

    isLoading = true;

    fetch(`/api/messages?page=${currentPage}`)
        .then(r => r.json())
        .then(response => {
            appendMessages(response.data);
            hasMore = response.has_more;
            currentPage = response.next_page;
            isLoading = false;
            loadingIndicator.classList.add('hidden');
            if (!hasMore) {
                sentinel.innerHTML = '<span class="text-sm text-gray-300">已顯示全部訊息</span>';
                observer.disconnect();
            }
        });
}

function appendMessages(messages) {
    const list = document.getElementById('messages-list');
    messages.forEach(m => globalMsgMap.set(m.id, { ...m, children: [] }));

    const roots = [];
    messages.forEach(m => {
        if (!m.parent_id) roots.push(globalMsgMap.get(m.id));
        else {
            const parent = globalMsgMap.get(m.parent_id);
            if (parent) parent.children.push(globalMsgMap.get(m.id));
            else roots.push(globalMsgMap.get(m.id));
        }
    });

    roots.forEach(root => list.insertAdjacentHTML('beforeend', buildRootHTML(root)));
}

loadMessages();  






<!-- // if (typeof Echo !== 'undefined') {
    //     Echo.channel('wall-channel')
    //         .listen('.message.created', (e) => {
    //             const newMsg = e.message;

    //             // 1. 去重防呆：如果是自己發的，或是已經存在的訊息，直接無視
    //             if (globalMsgMap.has(newMsg.id)) return;

    //             // 2. 初始化子結構，並登入全域快取樹中
    //             newMsg.children = [];
    //             globalMsgMap.set(newMsg.id, newMsg);

    //             if (!newMsg.parent_id) {
    //                 // 💡 【情況 A：有人發了全新主貼文】
    //                 // 直接將新貼文插到生活牆最頂端，完全無感即時
    //                 const list = document.getElementById('messages-list');
    //                 if (list) {
    //                     list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));
    //                 }
    //             } else {
    //                 // 💡 【情況 B：有人回覆了某條貼文】
    //                 // 骨架回溯演算法：沿著 parent_id 一路向上找出最頂層的根貼文 ID
    //                 let rootId = newMsg.parent_id;
    //                 let parentMsg = globalMsgMap.get(newMsg.parent_id);
                    
    //                 while (parentMsg && parentMsg.parent_id) {
    //                     rootId = parentMsg.parent_id;
    //                     parentMsg = globalMsgMap.get(parentMsg.parent_id);
    //                 }

    //                 // 將新訊息追加進父節點物件的 children 陣列中，保持記憶體資料鏈完整
    //                 const trueParent = globalMsgMap.get(newMsg.parent_id);
    //                 if (trueParent) {
    //                     if (!trueParent.children) trueParent.children = [];
    //                     trueParent.children.push(newMsg);
    //                 }

    //                 // 局部更新：如果這個根貼文目前在使用者畫面上，我們只重繪這張貼文卡片！
    //                 // 這能保證留言樹、回覆計數器「啪」一聲瞬間精準更新，且不會害其他使用者的網頁彈跳！
    //                 const rootEl = document.getElementById(`msg-${rootId}`);
    //                 const rootMsg = globalMsgMap.get(rootId);
    //                 if (rootEl && rootMsg) {
    //                     // 強制維持展開狀態，讓使用者立刻看見新留言跳出來
    //                     expandedSet.add(rootId);
    //                     rootEl.outerHTML = buildRootHTML(rootMsg);
    //                 }
    //             }
    //         });
    // } -->
</script>
</x-app-layout>


<!-- 最新版本 -->
<!-- <x-app-layout>
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
    /* 新增：回覆分支線樣式 */
    .reply-branch {
        position: relative;
    }

    .reply-branch::before {
        content: '';
        position: absolute;
        left: -16px;
        /* 連接父容器的垂直線 */
        top: 22px;
        /* 對準頭像垂直中心 */
        width: 16px;
        /* 線條長度 */
        height: 2px;
        /* 線條粗細 */
        background-color: #e5e7eb;
        /* border-gray-200 的顏色 */
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
    /* 讓 HTML 結構一目瞭然 */
    main { background-color: red !important; }
    main > div { background-color: blue !important; }
    </style>

    <script>
    window.globalMsgMap = new Map();
    window.expandedSet = new Set();    
    // 💡 1. 放在這裡！將 Laravel 目前登入的 user ID 轉成 JavaScript 全域變數
    window.currentUserId = {{ auth()->id() ?? 'null' }};    

    /* ... (資料處理邏輯) ... */
    const expandedSet = new Set();
    let globalMsgMap = new Map();

    function renderMessages(messages) {
        const list = document.getElementById('messages-list');
        list.innerHTML = '';
        globalMsgMap = new Map();
        messages.forEach(m => globalMsgMap.set(m.id, {
            ...m,
            children: []
        }));
        const roots = [];
        messages.forEach(m => {
            if (!m.parent_id) roots.push(globalMsgMap.get(m.id));
            else {
                const parent = globalMsgMap.get(m.parent_id);
                if (parent) parent.children.push(globalMsgMap.get(m.id));
                else roots.push(globalMsgMap.get(m.id));
            }
        });
        roots.forEach(root => list.insertAdjacentHTML('beforeend', buildRootHTML(root)));
    }

    function buildRootHTML(msg) {
        const hasReplies = msg.children && msg.children.length > 0;
        const isOpen = expandedSet.has(msg.id);
        const count = msg.children ? msg.children.length : 0;

        const avatarCol = hasReplies ?
            `<div class="flex flex-col items-center flex-shrink-0 w-10">
                    <img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                    <div class="w-0.5 flex-1 bg-gray-300 mt-1 rounded-full min-h-3"></div>
                </div>` :
            `<img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">`;

        const toggleBtn = hasReplies ?
            `<button onclick="toggleReplies(${msg.id}, ${count})" id="tbtn-${msg.id}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 mt-1 select-none bg-transparent border-none cursor-pointer p-0"><span class="toggle-arrow ${isOpen ? 'rotate-180' : ''}">▼</span><span id="tlabel-${msg.id}">${isOpen ? '隱藏回覆' : `查看 ${count} 則回覆`}</span></button>` :
            '';

        const repliesHtml = hasReplies 
        ? msg.children.map(c => buildReplyHTML(c, msg.id, new Set(), 1)).join('') 
        : '';

        // 💡 2. 動態判斷：如果是這則訊息的主人，才顯示按鈕
        const ownerButtons = (window.currentUserId && msg.user_id == window.currentUserId) ? `
            <button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer   p-0 text-xs text-red-300">刪除</button>
            <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0   text-xs text-gray-400">編輯</button>
            ` : '';

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
        const branchBtn = hasChildren ?
            `<button class="hover:text-gray-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400" id="bbtn-${msg.id}" onclick="toggleBranch(${msg.id}, this)">▾ 收合</button>` :
            '';
        const replyingTo = buildReplyingToTag(msg.parent_id, rootId);
        const timeLabel = buildTimeLabel(msg.created_at);
        const childrenSection = hasChildren
        ? (depth < 3
            ? `<div id="branch-${msg.id}" class="ml-6 pl-4 border-l-2 border-gray-200 mt-1">${childrenHtml}</div>`
            : `<div id="branch-${msg.id}">${childrenHtml}</div>`)
        : '';


        // 💡 3. 子留言也做同樣的擁有者動態判斷
        const ownerButtons = (window.currentUserId && msg.user_id == window.currentUserId) ? `
            <button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer p-0 text-xs text-red-300">刪除</button>
            <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">編輯</button>
        ` : '';

        // 加入 reply-branch class 以觸發 CSS 偽元素
        return `
        <div id="msg-${msg.id}" class="reply-branch relative pt-2.5" data-id="${msg.id}" data-parent-id="${msg.parent_id || ''}">
            <div class="flex items-start gap-2">
                <img src="${msg.user.profile_photo_url}" class="w-7 h-7 rounded-full object-cover flex-shrink-0 mt-0.5 relative z-10">
                <div class="flex-1 min-w-0">
                    <div class="msg-bubble bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl px-3 py-2">
                        <div class="flex items-baseline gap-1 flex-wrap mb-0.5">
                            <span class="text-xs font-bold text-gray-700">${escHtml(msg.user.name)}</span>
                            ${replyingTo}
                            <span class="text-xs text-gray-400 ml-auto">${timeLabel}</span>
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

    /* ... (其餘函式維持不變) ... */
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
        const parent = globalMsgMap.get(parentId);
        if (!parent) return '';

        // 取前 20 個字作為預覽
        const preview = parent.content ? parent.content.slice(0, 20) + (parent.content.length > 20 ? '...' : '') : '';
        return `
        <span class="text-xs text-gray-400">回覆</span> 
        <span class="text-xs font-semibold text-blue-500 cursor-pointer hover:underline" 
              onclick="scrollToMsg(${parentId})">@${escHtml(parent.user.name)}</span>
        <span class="text-xs text-gray-300">·</span>
        <span class="text-xs text-gray-400 cursor-pointer hover:text-blue-400 hover:underline italic max-w-32 truncate inline-block align-bottom"
              onclick="scrollToMsg(${parentId})"
              title="${escHtml(preview)}">「${escHtml(preview)}」</span>
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
        // 🚀 已完美對接你們的 AWS S3 儲存桶網址
        const s3BaseUrl = 'https://linkluv-media-bucket.s3.ap-east-2.amazonaws.com/';
        if (msg.media_type === 'image' && msg.image_path) {
           // 💡 判斷路徑：如果是新資料走 S3 就補上 S3 網域，如果是舊資料本地暫存就走 /storage/ 
           const isS3 = msg.image_path.startsWith('images/') || !msg.image_path.startsWith('storage/');
           const imgUrl = isS3 ? `${s3BaseUrl}${msg.image_path}` : `/storage/${msg.image_path}`;
           return `<div class="msg-media"><img src="${imgUrl}" onclick="openLightbox('image','${imgUrl}')"></div>`;
        }
        if (msg.media_type === 'video' && msg.video_path) {
            // 💡 影片經過壓縮後也上傳到 S3 (路徑為 videos/xxx.mp4)，同樣補上 S3 網域
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
            expandedSet.delete(rootId);
        } else {
            wrap.classList.add('expanded');
            if (label) label.textContent = '隱藏回覆';
            if (arrow) arrow.classList.add('rotate-180');
            expandedSet.add(rootId);
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
                expandedSet.add(rootId);
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
        if (!contentInput.value.trim() && (!fileInput || !fileInput.files.length)) {
            alert('請輸入回覆內容或上傳媒體');
            return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(() => {
            expandedSet.add(rootId);
            loadMessages(true);
        });
    };
    window.submitPost = function(e) {
        e.preventDefault();
        const form = e.target;
        const fi = form.querySelector('input[type="file"]');

        // 1. 檔案大小檢查
        if (fi?.files.length > 0 && fi.files[0].size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            fi.value = '';
            return;
        }

        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(r => r.json())
        .then(d => {
            // 2. 判斷後端有沒有傳回新訊息的資料 (d.data)
           if (d.success && d.data) {
               const newMsg = d.data;
               const list = document.getElementById('messages-list');

               // 初始化子留言結構，並塞入全域 Map 確保後續互動正常運作
               newMsg.children = [];
               globalMsgMap.set(newMsg.id, newMsg);

               // ★ 用 afterbegin 讓這則新 HTML 直接插在列表的最前面（第一項）
               list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));

               form.reset();

               // 如果你有做上傳預覽，發送成功後順便清空它
               const preview = document.getElementById('fprev-main'); 
               if (preview) preview.innerHTML = '';
        } else {
             // 防呆機制
             loadMessages(true);
             form.reset();
        }
    });
};              

    window.deleteMsg = function(id) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;
        fetch(`/messages/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages();
        });
    };
    window.editMsg = function(id) {
        const p = document.getElementById(`content-${id}`);
        if (!p) return;
        const orig = p.innerText;
        p.innerHTML =
            `<textarea id="edit-ta-${id}" rows="2" class="w-full text-sm border border-gray-300 rounded-lg p-2 resize-none">${orig}</textarea><div class="flex gap-2 mt-1"><button onclick="saveEdit(${id})" class="text-xs bg-blue-500 text-white px-3 py-1 rounded-lg border-none cursor-pointer">儲存</button><button onclick="loadMessages()" class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded-lg border-none cursor-pointer">取消</button></div>`;
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
            body: JSON.stringify({
                content: val
            }),
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages();
        });
    };
    window.toggleLike = function(id) {
        const btn = document.querySelector(`#msg-${id} .like-btn, [data-id="${id}"] .like-btn`);
        const countEl = document.getElementById(`lcount-${id}`);
        if (!btn || !countEl) return;

        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
        })
        .then(r => r.json())
        .then(d => {
          if (d.likes_count !== undefined) {
             countEl.textContent = d.likes_count;
             if (d.is_liked) {
                 btn.classList.add('text-pink-500', 'font-bold');
                 btn.classList.remove('text-gray-400');
            } else {
                btn.classList.remove('text-pink-500', 'font-bold');
                btn.classList.add('text-gray-400');
            }
            
            if (globalMsgMap.has(id)) {
                const msg = globalMsgMap.get(id);
                msg.likes_count = d.likes_count;
                msg.is_liked = d.is_liked;
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
            
            if (globalMsgMap.has(id)) {
               const msg = globalMsgMap.get(id);
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
        el.innerHTML = file.type.startsWith('image/') ? `<img src="${url}" class="max-w-40 rounded-xl mt-1">` :
            `<video src="${url}" controls class="max-w-48 rounded-xl mt-1"></video>`;
    };
    window.openLightbox = function(type, src) {
        document.getElementById('lightbox-content').innerHTML = type === 'image' ? `<img src="${src}">` :
            `<video src="${src}" controls autoplay></video>`;
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


    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;

    const sentinel = document.getElementById('scroll-sentinel');
    const loadingIndicator = document.getElementById('loading-indicator');

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isLoading && hasMore) {
            loadingIndicator.classList.remove('hidden');
            loadMessages();
        }
    }, { rootMargin: '0px 0px 100px 0px' });

    observer.observe(sentinel);

    function loadMessages(reset = false) {
        if (isLoading || (!hasMore && !reset)) return;

        if (reset) {
            currentPage = 1;
            hasMore = true;
            document.getElementById('messages-list').innerHTML = '';
            globalMsgMap = new Map();
        }

        isLoading = true;

        fetch(`/api/messages?page=${currentPage}`)
            .then(r => r.json())
            .then(response => {
                appendMessages(response.data);
                hasMore = response.has_more;
                currentPage = response.next_page;
                isLoading = false;
                loadingIndicator.classList.add('hidden');
                if (!hasMore) {
                    sentinel.innerHTML = '<span class="text-sm text-gray-300">已顯示全部訊息</span>';
                    observer.disconnect();
                }
            });
    }

    function appendMessages(messages) {
        const list = document.getElementById('messages-list');
        messages.forEach(m => globalMsgMap.set(m.id, { ...m, children: [] }));

        const roots = [];
        messages.forEach(m => {
            if (!m.parent_id) roots.push(globalMsgMap.get(m.id));
            else {
                const parent = globalMsgMap.get(m.parent_id);
                if (parent) parent.children.push(globalMsgMap.get(m.id));
                else roots.push(globalMsgMap.get(m.id));
            }
        });

        roots.forEach(root => list.insertAdjacentHTML('beforeend', buildRootHTML(root)));
    }

    loadMessages();
         

    // =========================================================================
    // 🔥 完美整合：Laravel Reverb WebSocket 0.5秒極速異步無感渲染演算法
    // =========================================================================
    
function setupEcho() {
    if (typeof Echo === 'undefined') {
       console.error('❌ Echo 未定義，請檢查 app.js 是否正確載入')
       return;
    }
    
    // 1. 萬用監聽：這會抓出所有經過這個 socket 的事件，不管頻道名對不對
    window.Echo.connector.pusher.bind_global((event, data) => {
       console.log('📡 [偵測到事件]:', event, '資料:', data);
    });    
        // 2. 你的目標頻道監聽
        Echo.channel('wall-channel')
            .subscribed(() => {
                console.log('✅ 已成功訂閱 wall-channel 頻道');
            })
            .error((error) => {
                console.error('❌ 頻道訂閱失敗:', error);
            })
            .listen('.message.created', (e) => { // ❗ 這裡改為 MessageSent
                console.log('🎉 WebSocket 收到廣播事件:', e); // ❗ 檢查這行有沒有印出來

                const newMsg = e.message;

                // 🟢 改成：如果畫面上已經有了，就跳過不重複渲染（避免 submitPost 渲染一次，WebSocket 又渲染一次）
                if (globalMsgMap.has(newMsg.id)) return;

                newMsg.children = [];
                globalMsgMap.set(newMsg.id, newMsg);

                if (!newMsg.parent_id) {
                    const list = document.getElementById('messages-list');
                    if (list) {
                        // 🔥 關鍵核心：別人發布全新貼文時，也強制啪一聲插到最前面第一項！
                        list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));
                    }
                } else {
                    // 你的回溯邏輯...
                    let rootId = newMsg.parent_id;
                    let parentMsg = globalMsgMap.get(newMsg.parent_id);
                    
                    while (parentMsg && parentMsg.parent_id) {
                        rootId = parentMsg.parent_id;
                        parentMsg = globalMsgMap.get(parentMsg.parent_id);
                    }

                    const trueParent = globalMsgMap.get(newMsg.parent_id);
                    if (trueParent) {
                        if (!trueParent.children) trueParent.children = [];
                        trueParent.children.push(newMsg);
                    }

                    const rootEl = document.getElementById(`msg-${rootId}`);
                    const rootMsg = globalMsgMap.get(rootId);
                    if (rootEl && rootMsg) {
                        expandedSet.add(rootId);
                        rootEl.outerHTML = buildRootHTML(rootMsg);
                    }
                }
    }        
}

// 2. 這是「啟動器」，放在函式外面，確保網頁載入後才執行
window.addEventListener('load', () => {
    // 建立輪詢機制，每 200ms 檢查一次 Echo 是否準備好
    let retries = 0;
    const maxRetries = 25; // 最多嘗試 5 秒 (25 * 200ms)

    const checkEcho = setInterval(() => {
        // 檢查方式：優先確認 window.Echo，若無則檢查全域 Echo
        const echoInstance = window.Echo || (typeof Echo !== 'undefined' ? Echo : null);

        if (echoInstance) {
            // 確保掛載到 window 以供 setupEcho 使用
            window.Echo = echoInstance;
            console.log('🚀 Echo 已偵測到，準備執行 setupEcho...');
            clearInterval(checkEcho);
            setupEcho();
        } else {
            retries++;
            if (retries >= maxRetries) {
                console.error('❌ 超時：無法偵測到 Echo，請檢查 app.js 或 Reverb 設定。');
                clearInterval(checkEcho);
            }
        }
    }, 200);
});
    </script>
</x-app-layout> -->


<!-- 新贈了即時更新 -->
<!-- <x-app-layout>
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
                const newMsg = e.message;

                // 去重防呆：已存在就跳過
                if (window.globalMsgMap.has(newMsg.id)) return;

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
                        trueParent.children.push(newMsg);
                    }
                    // 強制展開，讓使用者立刻看到新回覆
                    window.expandedSet.add(rootId);
                    const rootEl = document.getElementById(`msg-${rootId}`);
                    const rootMsg = window.globalMsgMap.get(rootId);
                    if (rootEl && rootMsg) {
                        // 防護：若使用者正在該卡片內打字，跳過重繪避免焦點丟失
                        const activeInput = rootEl.querySelector('input[name="content"], textarea');
                        const isFocused = activeInput && (document.activeElement === activeInput);
                        const hasTyped = activeInput && activeInput.value.trim() !== '';
                        if (!isFocused && !hasTyped) {
                            rootEl.outerHTML = buildRootHTML(rootMsg);
                        }
                    }
                }
            });
    }

    // =========================================================
    // 4. 輔助函式：回溯找出根貼文 ID
    // =========================================================
    function findRootId(parentId) {
        let current = window.globalMsgMap.get(parentId);
        while (current && current.parent_id) {
            current = window.globalMsgMap.get(current.parent_id);
        }
        return current ? current.id : parentId;
    }

    // =========================================================
    // 5. 載入訊息核心（修正：補回 parent-child 建立邏輯）
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

    function appendMessages(messages) {
        const list = document.getElementById('messages-list');

        // Step 1: 塞進 Map（防禦性寫法：已存在的訊息保留 children，不清空）
        messages.forEach(m => {
            if (!window.globalMsgMap.has(m.id)) {
                // 全新訊息：正常建立
                window.globalMsgMap.set(m.id, { ...m, children: [] });
            } else {
                // 已存在（分頁重疊）：更新內容，但明確保留現有 children
                const existing = window.globalMsgMap.get(m.id);
                window.globalMsgMap.set(m.id, { ...m, children: existing.children });
            }
        });

        // Step 2: 建立 parent-child 關係（這步驟不能省）
        messages.forEach(m => {
            if (m.parent_id) {
                const parent = window.globalMsgMap.get(m.parent_id);
                if (parent) parent.children.push(window.globalMsgMap.get(m.id));
            }
        });

        // Step 3: 只渲染根層貼文（子留言由 buildRootHTML 遞迴處理）
        messages.forEach(m => {
            if (!m.parent_id) {
                list.insertAdjacentHTML('beforeend', buildRootHTML(window.globalMsgMap.get(m.id)));
            }
        });
    }

    // =========================================================
    // 6. HTML 建構函式
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
    // 7. 小型 Helper 函式
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
    // 8. 互動事件函式
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
        if (!contentInput.value.trim() && (!fileInput || !fileInput.files.length)) {
            alert('請輸入回覆內容或上傳媒體');
            return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(r => r.json()).then(() => {
            window.expandedSet.add(rootId);
            loadMessages(true);
        });
    };

    window.submitPost = function(e) {
        e.preventDefault();
        const form = e.target;
        const fi = form.querySelector('input[type="file"]');
        if (fi?.files.length > 0 && fi.files[0].size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            fi.value = '';
            return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data) {
                const newMsg = d.data;

                // 防護：WebSocket 可能已經先渲染過了，不重複插入
                if (!window.globalMsgMap.has(newMsg.id)) {
                    newMsg.children = [];
                    window.globalMsgMap.set(newMsg.id, newMsg);
                    const list = document.getElementById('messages-list');
                    list.insertAdjacentHTML('afterbegin', buildRootHTML(newMsg));
                }

                form.reset();
                const preview = document.getElementById('fprev-main');
                if (preview) preview.innerHTML = '';
            } else {
                loadMessages(true);
                form.reset();
            }
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
        const btn = document.querySelector(`#msg-${id} .like-btn, [data-id="${id}"] .like-btn`);
        const countEl = document.getElementById(`lcount-${id}`);
        if (!btn || !countEl) return;

        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(d => {
            if (d.likes_count !== undefined) {
                countEl.textContent = d.likes_count;
                if (d.is_liked) {
                    btn.classList.add('text-pink-500', 'font-bold');
                    btn.classList.remove('text-gray-400');
                } else {
                    btn.classList.remove('text-pink-500', 'font-bold');
                    btn.classList.add('text-gray-400');
                }
                if (window.globalMsgMap.has(id)) {
                    const msg = window.globalMsgMap.get(id);
                    msg.likes_count = d.likes_count;
                    msg.is_liked = d.is_liked;
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
    </script>
</x-app-layout> -->