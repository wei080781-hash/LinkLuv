<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-6 text-left">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8 mt-4">
                最新生活動態
            </h2>

            <!-- 發表留言區塊 -->
            <div class="bg-white p-6 shadow-sm rounded-xl mb-8 border border-gray-100">
                @auth
                    <h3 class="text-lg font-bold text-gray-700 mb-4">發表動態</h3>
                    <form action="/messages" method="POST" enctype="multipart/form-data">
                        @csrf
                        <textarea name="content" required placeholder="分享你的生活點滴..." class="w-full border-gray-200 rounded-lg focus:ring-pink-500 focus:border-pink-500 shadow-sm"></textarea>
                        <input type="file" name="image" class="mt-3 text-sm text-gray-500">
                        <div class="flex justify-end mt-3">
                            <button type="submit" class="bg-pink-600 text-white px-6 py-2 rounded-full font-semibold hover:bg-pink-700 transition">送出</button>
                        </div>
                    </form>
                @else
                    <p class="text-gray-600">請先 <a href="{{ route('login') }}" class="text-pink-600 font-bold hover:underline">登入</a> 或 <a href="{{ route('register') }}" class="text-pink-600 font-bold hover:underline">註冊</a> 以發表留言。</p>
                @endauth
            </div>
            
            <!-- 精選動態 (輪播式卡片) -->
            <div class="mb-12">
                <h3 class="text-xl font-bold text-gray-800 mb-4">精選動態</h3>
                
                <!-- 父容器設定 relative 且確保高度足夠 -->
                <div class="relative flex items-center px-12">
                    <!-- 左箭頭 -->
                    <button onclick="scrollCarousel(-320)" class="absolute left-0 z-20 p-3 bg-white rounded-full shadow-md text-pink-600 border border-gray-200 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- 輪播容器 -->
                    <div id="carousel" class="flex gap-6 overflow-x-hidden scroll-smooth w-full">
                        @for ($i = 0; $i < 5; $i++)
                            <div class="carousel-item flex-shrink-0 w-80 bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 bg-gray-200 rounded-full"></div>
                                    <div>
                                        <p class="font-bold text-gray-800 text-sm">攝影玩家 {{ $i + 1 }}</p>
                                        <p class="text-[10px] text-gray-400">精彩時刻 #{{ $i + 1 }}</p>
                                    </div>
                                </div>
                                <p class="text-gray-700 mb-4 text-sm line-clamp-2">捕捉生活中的光影，這是我們探索世界的每一段紀錄。今天也是美好的一天。</p>
                                <div class="bg-white p-2 shadow-md mb-4 border border-gray-100">
                                    <div class="aspect-video bg-gray-100 flex items-center justify-center text-gray-400 text-xs">精選攝影作品 {{ $i + 1 }}</div>
                                </div>
                                <div class="flex border-t pt-3 gap-4 text-gray-500 font-bold text-xs justify-around">
                                    <button class="hover:text-pink-600">按讚</button>
                                    <button class="hover:text-pink-600">留言</button>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <!-- 右箭頭 -->
                    <button onclick="scrollCarousel(320)" class="absolute right-0 z-20 p-3 bg-white rounded-full shadow-md text-pink-600 border border-gray-200 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- 留言列表 -->
            <h3 class="text-xl font-bold text-gray-800 mb-4 px-2">最新留言</h3>
            <div class="space-y-4">
                @php
                    function renderMessages($messages) {
                        foreach ($messages as $message) {
                            $margin = min($message->depth * 30, 200); // 最大縮排限制
                            echo '<div id="message-' . $message->id . '" style="margin-left: ' . $margin . 'px;" class="message-container">';
                            echo '  <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-all">';
                            echo '      <div class="flex items-center gap-3 mb-3">';
                            echo '          <div class="w-10 h-10 bg-gray-200 rounded-full"></div>';
                            echo '          <p class="font-bold text-gray-800 text-sm">' . e($message->user->name ?? '匿名使用者') . '</p>';
                            echo '      </div>';
                            echo '      <p class="text-gray-700 mb-3 text-sm">' . e($message->content) . '</p>';
                            if ($message->image_path) {
                                echo '      <img src="' . asset('storage/' . $message->image_path) . '" class="max-w-xs rounded-xl mb-3 border">';
                            }
                            
                            // 卡片操作列
                            echo '      <div class="flex items-center gap-4 text-xs mt-2">';
                            // 展開/收起回覆框
                            echo '          <button onclick="document.getElementById(\'reply-form-' . $message->id . '\').classList.toggle(\'hidden\')" class="text-gray-400 hover:text-pink-600 transition">回覆</button>';
                            // 展開/收起子留言 (如果有回覆)
                            if ($message->children->count() > 0) {
                                echo '      <button onclick="toggleChildren(this)" class="text-gray-400 hover:text-pink-600 transition">收起/展開回覆</button>';
                            }
                            echo '      </div>';
                            // 下拉式回覆表單
                            echo '      <div id="reply-form-' . $message->id . '" class="hidden mt-3 pt-3 border-t border-gray-100 bg-gray-50/50 p-3 rounded-xl transition-all">';
                            echo '          <form action="/messages" method="POST" class="flex gap-2">';
                            echo '              <input type="hidden" name="_token" value="' . csrf_token() . '">';
                            echo '              <input type="hidden" name="parent_id" value="' . $message->id . '">';
                            echo '              <input type="text" name="content" required placeholder="寫下回覆..." class="flex-1 bg-white border border-gray-200 rounded-full text-sm py-1.5 px-4 focus:ring-pink-500 focus:border-pink-500 transition">';
                            echo '              <button type="submit" class="text-pink-600 text-sm font-bold px-3 hover:bg-pink-50 rounded-full transition">送出</button>';
                            echo '          </form>';
                            echo '      </div>';
                            echo '      <div class="children-container">';
                            if ($message->children->count() > 0) {
                                renderMessages($message->children);
                            }
                            echo '      </div>';
                            echo '  </div>';
                            echo '</div>';
                        }
                    }
                @endphp
                @php renderMessages($messages) @endphp
            </div> 
        </div>
    </div>
    <script>
        const carousel = document.getElementById('carousel');
        function scrollCarousel(offset) { carousel.scrollBy({ left: offset, behavior: 'smooth' }); }
        
        function toggleChildren(btn) {
            const container = btn.closest('.message-container').querySelector('.children-container');
            container.classList.toggle('hidden');
        }

        setInterval(() => {
            if (carousel.scrollLeft + carousel.offsetWidth >= carousel.scrollWidth) carousel.scrollTo({ left: 0, behavior: 'smooth' });
            else scrollCarousel(320);
        }, 5000);
    </script>
</x-app-layout>