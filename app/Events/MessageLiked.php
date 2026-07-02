<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // 💡 引入即時發射契約
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 🔥 核心檢查點：必須 implements ShouldBroadcastNow 確保最高優先權即時發射
class MessageLiked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // 📦 宣告公有變數：這些變數會被自動轉成 JSON 傳給前端帳號 B
    public $messageId;
    public $likesCount;
    public $isLiked;

    /**
     * 構造函式：當我們 new 這個事件時，負責把最新數據塞進包裹裡
     */
    public function __construct($messageId, $likesCount, $isLiked)
    {
        $this->messageId = (int) $messageId;     // 確保型態是整數，對齊前端型態 number
        $this->likesCount = (int) $likesCount;   // 最新總讚數
        $this->isLiked = (bool) $isLiked;        // 目前是用戶點讚(true)還是取消讚(false)
    }

    /**
     * 指定無線電台頻道：必須跟聊天訊息在同一個公共頻道，帳號 B 才聽得到
     */
    public function broadcastOn(): Channel
    {
        return new Channel('wall-channel'); // 收聽同一條 wall-channel
    }

    /**
     * 自訂前端識別暗號：讓前端 JS 可以用 .listen('.message.liked', ...) 來捕捉
     */
    public function broadcastAs(): string
    {
        return 'message.liked'; // 專屬於點讚事件的專用暗號
    }
}