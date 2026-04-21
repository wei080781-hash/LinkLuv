<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:messages,id',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('messages', 'public');
        }

        $parentId = $validated['parent_id'] ?? null;
        $depth = 0;

        if ($parentId) {
            $parent = Message::findOrFail($parentId);

            if ($parent->depth >= 30) {
                return back()->withErrors(['message' => '已達留言最大階層限制。']);
            }

            if ($parent->children()->count() >= 30) {
                return back()->withErrors(['message' => '該留言已達回覆數量限制。']);
            }

            $depth = $parent->depth + 1;
        }

        $message = Message::create([
            'content' => $validated['content'],
            'user_id' => auth()->id(),
            'parent_id' => $parentId,
            'depth' => $depth,
            'image_path' => $imagePath,
        ]);

        return back(303)->withFragment('message-' . $message->id);
    }
}
