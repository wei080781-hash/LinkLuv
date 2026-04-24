<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;

class MessageController extends Controller
{
    public function index()
    {
        return Message::with('user')->orderBy('created_at', 'asc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            'image' => 'nullable|image|max:2048',
            'parent_id' => 'nullable|exists:messages,id',
        ]);

        $parentId = $request->input('parent_id');

        $depth = 0;
        if ($parentId) {
            $parent = Message::find($parentId);
            if ($parent) {
                // 確保深度正確累加
                $depth = $parent->depth + 1;
            }
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('messages', 'public');
        }

        $message = Message::create([
            'content' => $validated['content'],
            'user_id' => auth()->id(),
            'parent_id' => $parentId,
            'depth' => $depth,
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message->load('user')
        ]);
    }

    public function destroy($id)
    {
        $message = Message::findOrFail($id);
        
        if (auth()->id() !== $message->user_id) {
            return response()->json(['success' => false, 'message' => '無權刪除'], 403);
        }

        // 遞迴刪除所有子留言，避免外鍵約束錯誤
        foreach ($message->children as $child) {
            $child->delete();
        }

        $message->delete();
        return response()->json(['success' => true]);
    }
}
