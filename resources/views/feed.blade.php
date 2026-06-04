<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-6">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8">生活牆</h2>
            @include('components.message-form')
            <div id="messages-list" class="space-y-4"></div>
        </div>
    </div>

    <script>
    // ──────────────────────────────────────────────────
    // 1. 主渲染函式：將數據分組後呼叫 buildThread
    // ──────────────────────────────────────────────────
    function renderMessages(messages) {
        const list = document.getElementById('messages-list');
        list.innerHTML = '';
        
        const map = new Map();
        messages.forEach(msg => map.set(msg.id, { ...msg, children: [] }));
        const roots = [];
        map.forEach(msg => {
            if (!msg.parent_id) {
                roots.push(msg);
            } else {
                const parent = map.get(msg.parent_id);
                if (parent) parent.children.push(msg);
            }
        });

        roots.forEach(root => {
            list.insertAdjacentHTML('beforeend', buildNodeHTML(root, 0));
        });
    }

    // ──────────────────────────────────────────────────
    // 2. 遞迴渲染函式
    // ──────────────────────────────────────────────────
    function buildNodeHTML(msg, depth) {
        const avatarSize = depth === 0 ? 40 : 32;
        const paddingLeft = avatarSize + 12;
        // const imageHtml = msg.image_path
        //     ? `<div class="mt-2"><img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100"></div>`
        //     : '';
        let mediaHtml = '';
        if (msg.media_type === 'image' && msg.image_path) {
            mediaHtml = `<div class="mt-2"><img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100"></div>`;
        } else if (msg.media_type === 'video' && msg.video_path) {
            mediaHtml = `
                <div class="mt-2">
                   <video controls class="max-w-xs rounded-xl shadow-sm border border-gray-100">
                          <source src="/storage/${msg.video_path}" type="video/mp4">
                            您的瀏覽器不支援影片播放。
                   </video>
                </div>
            `;
        }

        // 修正縮排邏輯：只有 depth 為 0 的容器才需要考慮容器縮排，所有回覆 (depth >= 1) 一律強制平齊
        const childrenHTML = msg.children.length > 0
        ? (() => {
        const lastAvatarSize = depth === 0 ? 32 : 32;
        const vLine = depth === 0
            ? `<div style="position:absolute; left:0; top:0; bottom:${lastAvatarSize / 2}px; width:2px; background:#d1d5db;"></div>`
            : '';
            return `<div style="position:relative; margin-left:${depth < 2 ? '40px' : '0'}; margin-top:4px;">
                    ${vLine}
                    ${msg.children.map(child => buildNodeHTML(child, depth + 1)).join('')}
                </div>`;
            })()
        : '';

        // 橫線：固定在 depth > 0 顯示，確保樣式一致
        const connector = depth > 0
        ? `<div style="position:absolute; left:-40px; top:${avatarSize / 2}px; width:40px; height:2px; background:#d1d5db;"></div>`
        : '';

        return `
        <div id="message-${msg.id}" class="mb-3" style="position:relative;">
            <div style="display:flex; align-items:flex-start; position:relative;">
                ${connector}
                <img src="${msg.user.profile_photo_url}"
                     style="width:${avatarSize}px; height:${avatarSize}px; border-radius:50%; flex-shrink:0; margin-right:8px; background:white; z-index:1;">
                <div class="p-2 bg-gray-100 rounded-2xl shadow-sm" style="flex:1;">
                    <p class="text-xs font-semibold text-gray-700">${msg.user.name}</p>
                    <p class="text-sm text-gray-800">${msg.content}</p>
                    ${mediaHtml}
                </div>
            </div>
            <div class="mt-1 flex gap-3 text-xs text-gray-400" style="padding-left:${paddingLeft}px;">
                <button onclick="toggleReply(${msg.id})" class="hover:text-blue-600">回覆</button>
                <button onclick="deleteMessage(${msg.id})" class="hover:text-red-500">刪除</button>
                <button onclick="editMessage(${msg.id})" class="hover:text-blue-600">編輯</button>
                <button onclick="toggleLike(${msg.id})" class="flex items-center gap-1 ${msg.is_liked ? 'text-pink-500' : ''}">
                    ❤️ ${msg.likes_count || 0}
                </button>
            </div>
            <div id="reply-form-${msg.id}" class="hidden mt-2" style="padding-left:${paddingLeft}px;">
                <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">
                    <input type="hidden" name="parent_id" value="${msg.id}">
                    <input type="text" name="content" required placeholder="回覆..."
                           class="rounded-full text-sm px-4 py-1.5 border w-full">
                    <button type="submit" class="text-pink-600 text-sm font-bold px-3">送出</button>
                </form>
            </div>
            ${childrenHTML}
        </div>
        `;
    }

    // ──────────────────────────────────────────────────
    // 3. 原有功能邏輯 (掛載到 window 以確保 onclick 可見)
    // ──────────────────────────────────────────────────
    window.toggleReply = function(id) {
        const el = document.getElementById(`reply-form-${id}`);
        if (el) {
            el.classList.toggle('hidden');
        } else {
            console.error('找不到表單: reply-form-' + id);
        }
    };

    window.deleteMessage = function(messageId) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;
        fetch(`/messages/${messageId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(r => r.json()).then(data => { if(data.success) loadMessages(); });
    };

    window.editMessage = function(messageId) {
        const p = document.querySelector(`#message-${messageId} p:nth-child(2)`);
        if (!p) return;
        const content = p.innerText;
        p.innerHTML = `<textarea id="edit-${messageId}" class="w-full text-sm border rounded-lg p-2">${content}</textarea>
                       <button onclick="saveEdit(${messageId})" class="text-xs bg-blue-500 text-white px-2 py-1 rounded">儲存</button>`;
    };

    window.saveEdit = function(messageId) {
        const newContent = document.getElementById(`edit-${messageId}`).value;
        fetch(`/messages/${messageId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ content: newContent })
        }).then(r => r.json()).then(data => { if(data.success) loadMessages(); });
    };

    window.toggleLike = function(messageId) {
        fetch(`/messages/${messageId}/like`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
        .then(() => loadMessages());
    };

    window.submitPost = function(e) {
    e.preventDefault();

        // 檢查檔案大小
        const fileInput = e.target.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const maxSize = 50 * 1024 * 1024; // 50MB

            if (file.size > maxSize) {
                alert('檔案太大，最大限制為 50MB');
                fileInput.value = ''; // 清空選擇
                return;
            }
        }

        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(e.target),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(d => { if(d.success) { loadMessages(); e.target.reset(); } });
    };

    window.submitReply = function(e, parentId) {
        e.preventDefault();
        fetch("{{ route('messages.store') }}", { method: 'POST', body: new FormData(e.target), headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
        .then(r => r.json()).then(() => loadMessages());
    };

    // 加在 loadMessages() 前面
    document.addEventListener('change', function(e) {
        if (e.target.type === 'file') {
            const file = e.target.files[0];
            if (!file) return;
            const maxSize = 50 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('檔案太大，最大限制為 50MB');
                e.target.value = '';
            }
        }
    });
    
    function loadMessages() { fetch('/api/messages').then(r => r.json()).then(renderMessages); }
    loadMessages();

    </script>
</x-app-layout>