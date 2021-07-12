<?php
/**
 *
 *  This file is part of kyrne/sylloge
 *
 *  Copyright (c) 2020 Kyrne.
 *
 *  For the full copyright and license information, please view the license.md
 *  file that was distributed with this source code.
 *
 */

namespace Kyrne\Evergreen\Listener;

use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Illuminate\Support\Arr;

class SaveTreeList
{
    /**
     * @param Saving $event
     */
    public function handle(Saving $event)
    {
        $data = Arr::get($event->data, 'attributes.replyTo');
        if ($data) {
            $replyPost = Post::find($data);

            if ($replyPost->reply_to) {
                $event->post->reply_to = $replyPost->reply_to;
                $ogPost = Post::find($replyPost->reply_to);
                $ogPost->reply_count = $ogPost->reply_count +1;
                $ogPost->save();
            } else {
                $event->post->reply_to = $data;
                $replyPost->reply_count = $replyPost->reply_count +1;
                $replyPost->save();
            }
        }
    }
}
