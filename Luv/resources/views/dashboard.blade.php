<x-app-layout>
    <!-- 留言板內容區 -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                @auth
                    <h3 class="text-lg font-bold mb-4">發表留言</h3>
                    <form action="/messages" method="POST" class="mb-8">
                        @csrf
                        <textarea name="content" required placeholder="寫點什麼..." class="w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        <button type="submit" class="mt-2 bg-pink-600 text-white px-4 py-2 rounded hover:bg-pink-700">送出</button>
                    </form>
                @endauth

                <h3 class="text-lg font-bold mb-4 border-b pb-2">留言列表</h3>
                
                @php
                    // 檢查是否有留言，若無則顯示假資料
                    if ($messages->isEmpty()) {
                        $dummy = new stdClass();
                        $dummy->content = "這是一個範例留言，歡迎開始交流！";
                        $dummy->depth = 0;
                        $dummy->children = collect();
                        $messages = collect([$dummy]);
                    }

                    function renderMessages($messages) {
                        foreach ($messages as $message) {
                            $hasChildren = isset($message->children) && $message->children->count() > 0;
                            echo '<div style="margin-left: ' . ($message->depth * 20) . 'px; border-bottom: 1px solid #f3f4f6; margin-bottom: 10px; padding: 10px;" class="message-container">';
                            echo '<div>' . e($message->content) . ' ';
                            if ($hasChildren) {
                                echo '<button onclick="toggleChildren(this)" style="font-size: 12px; cursor: pointer; color: #db2777; margin-left: 10px;">[-] 收起</button>';
                            }
                            echo '</div>';
                            
                            // 只有在非假資料時才顯示回覆表單
                            if (isset($message->id) && auth()->check()) {
                                echo '<form action="/messages" method="POST" style="margin-top:8px;">';
                                echo '<input type="hidden" name="_token" value="' . csrf_token() . '">';
                                echo '<input type="hidden" name="parent_id" value="' . $message->id . '">';
                                echo '<input type="text" name="content" placeholder="回覆..." required class="border-gray-300 rounded text-sm px-2 py-1">';
                                echo '<button type="submit" class="ml-2 text-sm text-pink-600 hover:text-pink-800">回覆</button>';
                                echo '</form>';
                            }
                            
                            if ($hasChildren) {
                                echo '<div class="children-container mt-2">';
                                renderMessages($message->children);
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    }
                @endphp

                @php renderMessages($messages) @endphp
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    function toggleChildren(btn) {
        const container = btn.closest('.message-container').querySelector('.children-container');
        if (container.style.display === 'none') {
            container.style.display = 'block';
            btn.innerText = '[-] 收起';
        } else {
            container.style.display = 'none';
            btn.innerText = '[+] 展開';
        }
    }
</script>