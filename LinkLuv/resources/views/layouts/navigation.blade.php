<nav class="flex items-center justify-between bg-white px-8 py-4 shadow-md rounded-lg">
    <div class="flex items-center">
        <a href="{{ route('dashboard') }}" class="text-3xl font-bold text-pink-600 mr-16">LinkLuv</a>

        <div class="flex items-center gap-10 text-sm text-gray-700 font-bold"> 
            <a href="{{ route('dashboard') }}" class="hover:text-pink-600">生活牆</a>
            <a href="#" class="hover:text-pink-600">同好圈</a>
            <a href="#" class="hover:text-pink-600">默契配對</a>
        </div>
    </div>

    <div class="flex items-center gap-6">
        @auth
            <a href="{{ route('profile.edit') }}" class="text-sm text-gray-700 font-semibold hover:text-pink-600">{{ Auth::user()->name }}</a>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-pink-600">登出</button>
            </form>
        @endauth
    </div>
</nav>