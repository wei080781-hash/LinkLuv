<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkLuv - 分享生活，找到同好</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">

    <nav class="flex justify-between items-center py-6 px-10">
        <div class="text-3xl font-bold text-pink-600">LinkLuv</div>
        <div class="space-x-6">
            <a href="{{ route('login') }}" class="text-gray-700 hover:text-pink-600">登入</a>
            <a href="{{ route('register') }}" class="bg-pink-600 text-white px-5 py-2 rounded-full hover:bg-pink-700">註冊</a>
        </div>
    </nav>

    <main>
        <header class="container mx-auto flex flex-col md:flex-row items-center py-20 px-6">
            <div class="md:w-1/2 mb-10 md:mb-0">
                <h1 class="text-5xl font-extrabold mb-6">分享生活，<br><span class="text-pink-600">遇見懂妳的同好</span></h1>
                <p class="text-xl text-gray-600 mb-8">在這裡，每個日常都值得分享。透過妳的興趣與生活點滴，找到那個頻率相同、懂妳的人。</p>
                <div class="flex space-x-4">
                    <button class="bg-pink-600 text-white px-8 py-4 rounded-lg text-lg font-bold hover:bg-pink-700 shadow-lg transition-all duration-300">開始分享生活</button>
                    <button class="bg-white text-pink-600 border border-pink-600 px-8 py-4 rounded-lg text-lg font-bold hover:bg-pink-50 transition-all duration-300">探索同好圈</button>
                </div>
            </div>
            
            <div class="md:w-1/2 flex justify-center items-center">
                <div class="relative">
                    <!-- Glowing Background -->
                    <div class="absolute inset-0 bg-pink-200 rounded-full blur-3xl opacity-50 animate-pulse"></div>
                    <!-- Interactive Placeholder -->
                    <div class="relative bg-white p-6 rounded-2xl shadow-2xl rotate-3 hover:rotate-0 transition-transform duration-500 w-72">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-12 h-12 bg-pink-300 rounded-full flex items-center justify-center text-white font-bold">柔</div>
                            <div>
                                <p class="font-bold text-gray-800">林小柔</p>
                                <p class="text-xs text-gray-500">2 小時前</p>
                            </div>
                        </div>
                        <div class="w-full h-40 bg-pink-200 rounded-lg flex items-center justify-center overflow-hidden">
                            <img src="https://images.unsplash.com/photo-1542038784456-1ea8e935640e?auto=format&fit=crop&q=80&w=300" alt="Post" class="object-cover w-full h-full">
                        </div>
                        <p class="mt-3 text-sm text-gray-700">午後的陽光灑在底片機上，這瞬間太美好了...✨</p>
                        <div class="mt-3 flex space-x-2">
                            <span class="px-3 py-1 bg-pink-100 text-pink-600 rounded-full text-xs font-medium">#底片攝影</span>
                            <span class="px-3 py-1 bg-pink-100 text-pink-600 rounded-full text-xs font-medium">#日常</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="container mx-auto py-20 px-6 grid md:grid-cols-3 gap-10">
            <div class="bg-white p-8 rounded-xl shadow-lg border-t-4 border-pink-500 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:border-pink-600">
                <h3 class="text-xl font-bold mb-4">生活牆</h3>
                <p class="text-gray-600">透過照片與文字記錄妳的點滴，讓志同道合的朋友看見真實的妳。</p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg border-t-4 border-pink-500 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:border-pink-600">
                <h3 class="text-xl font-bold mb-4">同好圈</h3>
                <p class="text-gray-600">加入攝影、旅行、烹飪等同好圈，讓連結更精準，不再孤單。</p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-lg border-t-4 border-pink-500 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:border-pink-600">
                <h3 class="text-xl font-bold mb-4">默契配對</h3>
                <p class="text-gray-600">不只看外表，我們看重妳的興趣與靈魂深度，精準媒合。</p>
            </div>
        </section>
    </main>

</body>
</html>