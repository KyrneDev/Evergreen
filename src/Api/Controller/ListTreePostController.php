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

namespace Kyrne\Evergreen\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Discussion\Command\ReadDiscussion;
use Flarum\Forum\Content\Discussion;
use Flarum\Post\PostRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListTreePostController extends AbstractListController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = PostSerializer::class;

    /**
     * {@inheritdoc}
     */
    public $include = [
        'user',
        'user.groups',
        'editedUser',
        'hiddenUser',
        'discussion',

    ];

    public $optionalInclude = [
        'user.ranks',
        'upvotes',
        'downvotes',
        'reactions'
    ];

    /**
     * {@inheritdoc}
     */
    public $sortFields = ['createdAt'];

    /**
     * @var \Flarum\Post\PostRepository
     */
    protected $posts;

    /**
     * @var Dispatcher
     */
    protected $bus;

    /**
     * @param \Flarum\Post\PostRepository $posts
     */
    public function __construct(PostRepository $posts, Dispatcher $bus)
    {
        $this->bus = $bus;
        $this->posts = $posts;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = $request->getAttribute('actor');
        $include = $this->extractInclude($request);
        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        $query = $this->posts->query();

        $this->customScopeVisible($query, $actor);

        $query->where('reply_to', $id)->skip($offset)->take($limit);

        $posts = $this->posts->query()->whereIn('id', $query->pluck('id')->all())->get();

        $discussionId = $posts->first()->discussion_id;


		if (!$actor->isGuest()) {
			$this->bus->dispatch(
				new ReadDiscussion($discussionId, $actor, $posts->last()->number)
			);
		}

        return $posts->load($include);
    }

    private function customScopeVisible($query, $actor)
    {
        // Make sure the post's discussion is visible as well.
        $query->whereExists(function ($query) use ($actor) {
            $query->selectRaw('1')
                ->from('discussions')
                ->whereColumn('discussions.id', 'posts.discussion_id');
        });

        // Hide private posts by default.
        $query->where(function ($query) use ($actor) {
            $query->where('posts.is_private', false);
        });

        // Hide hidden posts, unless they are authored by the current user, or
        // the current user has permission to view hidden posts in the
        // discussion.
        if (!$actor->hasPermission('discussion.hidePosts')) {
            $query->where(function ($query) use ($actor) {
                $query->whereNull('posts.hidden_at')
                    ->orWhere('posts.user_id', $actor->id)
                    ->orWhereExists(function ($query) use ($actor) {
                        $query->selectRaw('1')
                            ->from('discussions')
                            ->whereColumn('discussions.id', 'posts.discussion_id')
                            ->where(function ($query) use ($actor) {
                                $query
                                    ->whereRaw('1=0');
                            });
                    });
            });
        }
    }
}
