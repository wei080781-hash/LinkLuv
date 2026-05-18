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
            
            
</x-app-layout>