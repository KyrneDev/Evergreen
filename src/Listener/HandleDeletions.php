<?php


namespace Kyrne\Evergreen\Listener;

use Flarum\Post\Event\Hidden;
use Flarum\Post\Event\Deleted;
use Flarum\Post\Event\Restored;
use Flarum\Post\Post;
use Illuminate\Contracts\Events\Dispatcher;

class HandleDeletions
{
    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Restored::class, [$this, 'restore']);
        $events->listen(Hidden::class, [$this, 'hide']);
        $events->listen(Deleted::class, [$this, 'delete']);
    }

    /**
     * @param Restored $event
     */
    public function restore(Restored $event)
    {
        if ($event->post->reply_to) {
            $post = Post::find($event->post->reply_to);
            $post->reply_count = $post->reply_count + 1;
            $post->save();
        }
    }

    /**
     * @param Hidden $event
     */
    public function hide(Hidden $event)
    {
        if ($event->post->reply_to) {
            $post = Post::find($event->post->reply_to);
            $post->reply_count = $post->reply_count - 1;
            $post->save();
        }
    }

    public function delete(Deleted $event)
    {
        if ($event->post->reply_to && is_null($event->post->hidden_at)) {
            $post = Post::find($event->post->reply_to);
            $post->reply_count = $post->reply_count - 1;
            $post->save();
        }
    }
}
