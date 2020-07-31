import {extend, override} from 'flarum/extend';
import Post from 'flarum/components/Post';
import Button from 'flarum/components/Button';
import icon from 'flarum/helpers/icon';
import CommentPost from 'flarum/components/CommentPost';

export default function MakeTree() {
  override(Post.prototype, 'config', function() {
    const $actions = this.$('.Post-actions');
    const $controls = this.$('.Post-controls');

    $controls.on('click tap', function() {
      $(this).toggleClass('open');
    });
  });


  extend(Post.prototype, 'view', function (vdom) {
    const id = this.props.post.id();
    if (!app.cache.trees) {
      app.cache.trees = {};
      app.cache.pushTree = {};
    }
    if (!app.cache.trees[id]) {
      app.cache.trees[id] = [];
      app.cache.pushTree[id] = 0;
    }

    if (app.cache.trees[id].length > 1) {
      vdom.children.push(
        <div className='CommentTree' id={id}>
          {icon('fas fa-reply')}
          {app.cache.trees[id].filter((thing, index, self) => self.findIndex(t => t.id() === thing.id()) === index)
            .sort((a, b) => {
              return a.createdAt() - b.createdAt();
            }).map(post => {
              return CommentPost.component({post});
            })}
        </div>
      )
    }
    if (this.props.post.replyCount() > app.cache.trees[id].length - app.cache.pushTree[id] || (app.cache.trees[id].length === 0 && this.props.post.replyCount())) {
      const count = this.props.post.replyCount() - app.cache.trees[id].length + app.cache.pushTree[id];
      vdom.children.push(
        Button.component({
          className: 'Button Button--link Evergreen--show',
          icon: 'fas fa-caret-down',
          children: app.translator.trans('kyrne-evergreen.forum.post.show_' + (count > 1 ? 'replies' : 'reply'), {count}),
          onclick: () => {
            app.store.find('trees', id)
              .then(response => {
                delete response.payload;
                [].push.apply(app.cache.trees[id], response);
                m.redraw();
              })
          }
        })
      );
    }
  })
}

