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
use Flarum\Post\PostRepository;
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
        'discussion'
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
     * @param \Flarum\Post\PostRepository $posts
     */
    public function __construct(PostRepository $posts)
    {
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

        $query->where('reply_to', $id)->skip($offset)->take($limit);

        $posts = $this->posts->findByIds($query->pluck('id')->all(), $actor);

        return $posts->load($include);
    }
}