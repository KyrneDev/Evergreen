<?php

namespace Kyrne\Evergreen;

use Flarum\Api\Controller;
use Flarum\Api\Event\Serializing;
use Flarum\Api\Event\WillSerializeData;
use Flarum\Api\Serializer\BasicPostSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Event\ConfigureNotificationTypes;
use Flarum\Event\ConfigurePostsQuery;
use Flarum\Event\ScopeModelVisibility;
use Flarum\Extend;
use Flarum\Formatter\Event\Rendering;
use Illuminate\Database\Eloquent\Builder;
use Kyrne\Evergreen\Api\Controller\ListTreePostController;
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
use Kyrne\Evergreen\Listener;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less'),
    new Extend\Locales(__DIR__ . '/resources/locale'),
    (new Extend\Routes('api'))
        ->get('/trees/{id}', 'evergeen.trees.get', ListTreePostController::class),
    (new Extend\Event())
        ->listen(Saving::class, Listener\SaveTreeList::class)
        ->subscribe(Listener\HandleDeletions::class)
        ->listen(Deleted::class, Listener\UpdateMentionsMetadataWhenInvisible::class)
        ->listen(Hidden::class, Listener\UpdateMentionsMetadataWhenInvisible::class)
        ->listen(Posted::class, Listener\UpdateMentionsMetadataWhenVisible::class)
        ->listen(Restored::class, Listener\UpdateMentionsMetadataWhenVisible::class)
        ->listen(Revised::class, Listener\UpdateMentionsMetadataWhenVisible::class),

    (new Extend\ModelVisibility(Post::class))
        ->scopeAll(function(User $actor, Builder $query, $ability) {
            $sql = $query->toSql();
            if (stripos($sql, 'from `posts') && !stripos($sql, 'update') && !stripos($sql, 'delete')  && strpos($sql, 'id')) {
                $query->where('reply_to', 0);
            }
        }),

    (new Extend\Notification())
        ->type(PostMentionedBlueprint::class, PostSerializer::class, ['alert'])
        ->type(UserMentionedBlueprint::class, PostSerializer::class, ['alert']),

    (new Extend\Model(Post::class))
        ->belongsToMany('mentionedBy', Post::class, 'post_mentions_post', 'mentions_post_id', 'post_id')
        ->belongsToMany('mentionsPosts', Post::class, 'post_mentions_post', 'post_id', 'mentions_post_id')
        ->belongsToMany('mentionsUsers', User::class, 'post_mentions_user', 'post_id', 'mentions_user_id'),

    (new Extend\ApiSerializer(PostSerializer::class))
        ->attributes(function (PostSerializer $serializer, Post $post, array $attributes) {
            $attributes['replyTo'] = (int) $post->reply_to;
            $attributes['replyCount'] = (int) $post->reply_count;

            return $attributes;
        }),

    (new Extend\ApiSerializer(BasicPostSerializer::class))
        ->hasMany('mentionedBy', BasicPostSerializer::class)
        ->hasMany('mentionsPosts', BasicPostSerializer::class)
        ->hasMany('mentionsUsers', BasicPostSerializer::class),

    (new Extend\ApiController(Controller\ShowDiscussionController::class))
        ->addInclude(['posts.mentionedBy', 'posts.mentionedBy.user', 'posts.mentionedBy.discussion']),

    (new Extend\ApiController(Controller\ShowPostController::class))
        ->addInclude(['mentionedBy', 'mentionedBy.user', 'mentionedBy.discussion']),

    (new Extend\ApiController(Controller\ListPostsController::class))
        ->addInclude(['mentionedBy', 'mentionedBy.user', 'mentionedBy.discussion']),

    (new Extend\ApiController(Controller\CreatePostController::class))
        ->addInclude(['mentionsPosts', 'mentionsPosts.mentionedBy']),

    (new Extend\ApiController(Controller\AbstractSerializeController::class))
        ->prepareDataForSerialization(FilterVisiblePosts::class),

    (new Extend\Settings)
        ->serializeToForum('allowUsernameMentionFormat', 'flarum-mentions.allow_username_format', 'boolval'),

    (new Extend\Formatter)
        ->configure(ConfigureMentions::class)
        ->render(Formatter\FormatPostMentions::class)
        ->render(Formatter\FormatUserMentions::class)
        ->unparse(Formatter\UnparsePostMentions::class)
        ->unparse(Formatter\UnparseUserMentions::class),

    (new Extend\Formatter)
        ->configure(ConfigureMentions::class),

    (new Extend\View())
        ->namespace('flarum-mentions', __DIR__ . '/views'),

    (new Extend\Model(Post::class))
        ->belongsToMany('mentionedBy', Post::class, 'post_mentions_post', 'mentions_post_id', 'post_id')
        ->belongsToMany('mentionsPosts', Post::class, 'post_mentions_post', 'post_id', 'mentions_post_id')
        ->belongsToMany('mentionsUsers', User::class, 'post_mentions_user', 'post_id', 'mentions_user_id'),

    (new Extend\Filter(PostFilterer::class))
        ->addFilter(Filter\MentionedFilter::class),
];
