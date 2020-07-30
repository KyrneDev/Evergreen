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

use Flarum\Api\Event\Serializing;
use Flarum\Api\Serializer\PostSerializer;

class InjectSettings
{
    /**
     * @param Serializing $event
     */
    public function handle(Serializing $event)
    {
        if ($event->isSerializer(PostSerializer::class)) {
            $event->attributes['replyTo'] = (int) $event->model->reply_to;
            $event->attributes['replyCount'] = (int) $event->model->reply_count;
        }
    }
}
