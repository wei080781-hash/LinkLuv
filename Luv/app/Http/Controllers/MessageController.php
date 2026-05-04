<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;

class MessageController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        return Message::with(['user'])
        ->withCount('likes')// 這會自動產生 likes_count 欄位
        ->withExists(['likes as is_liked' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])// 這會產生 is_liked (boolean) 欄位
        ->orderBy('created_at', 'asc')->get();
    }
    // 處理留言的儲存與列表獲取，並處理階層深度邏輯。
    public function store(Request $request)
    {
        \Log::info('Files', $request->allFiles());
        \Log::info('Input', $request->except(['+']));
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            'image' => 'nullable|image|max:2048',
            'parent_id' => 'nullable|exists:messages,id',
        ]);

        $parentId = $request->input('parent_id');

        $depth = 0;
    if ($request->parent_id) {
        $parent = Message::findOrFail($request->parent_id);
        $depth = $parent->depth + 1;
    }

    $imagePath = $request->hasFile('image') ? $request->file('image')->store('messages', 'public') : null;

    Message::create([
        'content' => $request->content,
        'user_id' => auth()->id(),
        'parent_id' => $request->parent_id,
        'depth' => $depth,
        'image_path' => $imagePath,
    ]);

    return response()->json(['success' => true]);
}

    public function destroy(Message $message)
    {
        // === 在此處加入除錯紀錄 ===
        \Log::info('刪除嘗試:', [
            'current_user_id' => auth()->id(),
            'message_owner_id' => $message->user_id,
            'message_id' => $message->id
        ]);
        // 權限檢查
        if ($message->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '無權刪除'], 403);
        }
        // 同時刪除子留言（回覆）
        $message->replies()->delete();
        $message->delete();
        return response()->json(['success' => true]);
    }
    // 更新內容方式
    public function update(Request $request, Message $message)
    {
        \Log::info('Update 請求:', [
                'message_id' => $message->id,
                'input_data' => $request->all(),
                'current_user' => auth()->id(),
                'message_owner' => $message->user_id
            ]);
        //確認是本人的留言才能修改
        if ($message->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '您沒有權限修改此訊息'
            ], 403);
        }
        \Log::info('驗證前...');

        $validated = $request->validate([
        'content' => 'required|string|max:1000',
    ]);

         \Log::info('驗證通過，準備執行更新...');
    
    $message->update([
        'content' => $validated['content'],
    ]);

    return response()->json(['success' => true]);
    }
    


    public function like(Message $message) 
    {
        $userId = auth()->id();
        // 檢查使用者是否已經點過讚
         $like = $message->likes()->where('user_id',
         $userId)->first();
        
        if ($like) {
            $like->delete(); // 取消點讚
            $isLiked = false;
        } else {
            $message->likes()->create(['user_id' => $userId]); //點讚
            $isLiked = true;
        }
         return response()->json([
         'success' => true, 
         'liked' => $isLiked,
         'likes_count' => $message->likes()->count()
        ]);    
    }
}
