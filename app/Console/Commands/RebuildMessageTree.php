<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RebuildMessageTree extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rebuild-messages';

    protected $description = '重建留言樹的深度資料';

    public function handle()
    {
        $messages = \App\Models\Message::all();
        
        foreach ($messages as $message) {
            $depth = 0;
            $parent = $message->parent;
            
            while ($parent) {
                $depth++;
                $parent = $parent->parent;
            }
            
            $message->depth = $depth;
            $message->save();
        }
        
        $this->info('留言樹已重建。');
    }
}
