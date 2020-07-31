<?php

namespace Kyrne\Evergreen;

use Flarum\Api\Event\Serializing;
use Flarum\Api\Event\WillSerializeData;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Event\ConfigureNotificationTypes;
use Flarum\Event\ConfigurePostsQuery;
use Flarum\Event\ScopeModelVisibility;
use Flarum\Extend;
use Flarum\Formatter\Event\Rendering;
use function GuzzleHttp\Psr7\str;
use Kyrne\Evergreen\Api\Controller\ListTreePostController;
use Kyrne\Evergreen\ConfigureMentions;
use Kyrne\Evergreen\Notification\PostMentionedBlueprint;
use Kyrne\Evergreen\Notification\UserMentionedBlueprint;
use Flarum\Post\Event\Deleted;
use Flarum\Post\Event\Hidden;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored;
use Flarum\Post\Event\Revised;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory;
use Kyrne\Evergreen\Listener;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less'),
    new Extend\Locales(__DIR__ . '/resources/locale'),
    (new Extend\Routes('api'))
        ->get('/trees/{id}', 'evergeen.trees.get', ListTreePostController::class),
    (new Extend\Event())
        ->listen(Serializing::class, Listener\InjectSettings::class)
        ->listen(Saving::class, Listener\SaveTreeList::class)
        ->listen(ConfigurePostsQuery::class, Listener\AddFilterByMentions::class)
        ->listen(WillSerializeData::class, Listener\FilterVisiblePosts::class)
        ->listen(Rendering::class, Listener\FormatPostMentions::class)
        ->listen(Rendering::class, Listener\FormatUserMentions::class)
        ->listen(ConfigureNotificationTypes::class, function (ConfigureNotificationTypes $event) {
            $event->add(PostMentionedBlueprint::class, PostSerializer::class, ['alert']);
            $event->add(UserMentionedBlueprint::class, PostSerializer::class, ['alert']);
        })
        ->listen(ScopeModelVisibility::class, function(ScopeModelVisibility $event) {
            $sql = $event->query->toSql();
            if (stripos($sql, 'from `posts') && !stripos($sql, 'update') && !stripos($sql, 'delete')  && strpos($sql, 'id')) {
                $event->query->where('reply_to', 0);
            }
        }),

    (new Extend\Formatter)
        ->configure(ConfigureMentions::class),

    (new Extend\Model(Post::class))
        ->belongsToMany('mentionedBy', Post::class, 'post_mentions_post', 'mentions_post_id', 'post_id')
        ->belongsToMany('mentionsPosts', Post::class, 'post_mentions_post', 'post_id', 'mentions_post_id')
        ->belongsToMany('mentionsUsers', User::class, 'post_mentions_user', 'post_id', 'mentions_user_id'),

    function (Dispatcher $events, Factory $views) {
        $events->subscribe(Listener\AddPostMentionedByRelationship::class);
        $events->subscribe(Listener\HandleDeletions::class);

        $events->listen(
            [Deleted::class, Hidden::class],
            Listener\UpdateMentionsMetadataWhenInvisible::class
        );

        $events->listen([Posted::class, Restored::class, Revised::class],
            Listener\UpdateMentionsMetadataWhenVisible::class
        );

        $views->addNamespace('flarum-mentions', __DIR__ . '/views');
    }
];
