import app from 'flarum/app';
import {extend, override} from 'flarum/extend';
import DiscussionControls from 'flarum/utils/DiscussionControls';
import Alert from 'flarum/components/Alert';
import Button from 'flarum/components/Button';
import CommentPost from 'flarum/components/CommentPost';

import Post from 'flarum/models/Post';
import Model from 'flarum/Model';
import ReplyComposer from "flarum/components/ReplyComposer";
import LogInModal from "flarum/components/LogInModal";

import NotificationGrid from 'flarum/components/NotificationGrid';
import { getPlainContent } from 'flarum/utils/string';

import addPostMentionPreviews from './addPostMentionPreviews';
import addMentionedByList from './addMentionedByList';
import addPostReplyAction from './addPostReplyAction';
import addPostQuoteButton from './addPostQuoteButton';
import addComposerAutocomplete from './addComposerAutocomplete';
import PostMentionedNotification from './components/PostMentionedNotification';
import UserMentionedNotification from './components/UserMentionedNotification';
import UserPage from 'flarum/components/UserPage'
import LinkButton from 'flarum/components/LinkButton';
import MentionsUserPage from './components/MentionsUserPage';

import MakeTree from './MakeTree';

app.initializers.add('kyrne-everygreen', () => {
  Post.prototype.replyTo = Model.attribute('replyTo');
  Post.prototype.replyCount = Model.attribute('replyCount');

  DiscussionControls.replyAction = function (goToLast, forceRefresh, replyTo) {
    return new Promise((resolve, reject) => {
      if (app.session.user) {
        if (this.canReply()) {
          if (!app.composer.composingReplyTo(this) || forceRefresh) {
            app.composer.load(ReplyComposer, {
              user: app.session.user,
              discussion: this,
              replyTo
            });
          }
          app.composer.show();

          if (goToLast && app.viewingDiscussion(this) && !app.composer.isFullScreen()) {
            app.current.get('stream').goToNumber('reply');
          }

          return resolve(app.composer);
        } else {
          return reject();
        }
      }

      app.modal.show(LogInModal);

      return reject();
    });
  };

  extend(ReplyComposer.prototype, 'oninit', function() {
    this.replyTo = this.attrs.replyTo;
  });

  extend(ReplyComposer.prototype, 'data', function(data) {
    data.replyTo = this.replyTo
  });

  override(ReplyComposer.prototype, 'onsubmit', function() {
    const discussion = this.attrs.discussion;

    this.loading = true;
    m.redraw();

    const data = this.data();

    app.store
      .createRecord('posts')
      .save(data)
      .then((post) => {

        if (this.draft) {
          this.draft.delete();
        }
        // If we're currently viewing the discussion which this reply was made
        // in, then we can update the post stream and scroll to the post.
        if (app.viewingDiscussion(discussion)) {
          if (this.attrs.replyTo) {
            app.cache.trees[this.attrs.replyTo].push(post);
            app.cache.pushTree[this.attrs.replyTo]++;
            m.redraw();
          } else {
            const stream = app.current.get('stream');
            stream.update().then(() => stream.goToNumber(post.number()));
          }
        } else {
          // Otherwise, we'll create an alert message to inform the user that
          // their reply has been posted, containing a button which will
          // transition to their new post when clicked.
          let alert;
          const viewButton = Button.component(
            {
              className: 'Button Button--link',
              onclick: () => {
                m.route.set(app.route.post(post));
                app.alerts.dismiss(alert);
              },
            },
            app.translator.trans('core.forum.composer_reply.view_button')
          );
          alert = app.alerts.show(
            {
              type: 'success',
              controls: [viewButton],
            },
            app.translator.trans('core.forum.composer_reply.posted_message')
          );
        }


        app.composer.hide();
      }, this.loaded.bind(this));
  });

  // For every mention of a post inside a post's content, set up a hover handler
  // that shows a preview of the mentioned post.
  addPostMentionPreviews();

  // In the footer of each post, show information about who has replied (i.e.
  // who the post has been mentioned by).
  addMentionedByList();

  // Add a 'reply' control to the footer of each post. When clicked, it will
  // open up the composer and add a post mention to its contents.
  addPostReplyAction();

  // Show a Quote button when Post text is selected
  addPostQuoteButton();

  // After typing '@' in the composer, show a dropdown suggesting a bunch of
  // posts or users that the user could mention.
  addComposerAutocomplete();

  MakeTree();

  app.notificationComponents.postMentioned = PostMentionedNotification;
  app.notificationComponents.userMentioned = UserMentionedNotification;

  // Add notification preferences.
  extend(NotificationGrid.prototype, 'notificationTypes', function (items) {
    items.add('postMentioned', {
      name: 'postMentioned',
      icon: 'fas fa-reply',
      label: app.translator.trans('flarum-mentions.forum.settings.notify_post_mentioned_label')
    });

    items.add('userMentioned', {
      name: 'userMentioned',
      icon: 'fas fa-at',
      label: app.translator.trans('flarum-mentions.forum.settings.notify_user_mentioned_label')
    });
  });

  // Add mentions tab in user profile
  app.routes['user.mentions'] = {path: '/u/:username/mentions', component: MentionsUserPage};
  extend(UserPage.prototype, 'navItems', function(items) {
    const user = this.user;
    items.add('mentions',
      LinkButton.component({
        href: app.route('user.mentions', {username: user.slug()}),
        name: 'mentions',
        icon: 'fas fa-at'
      }, app.translator.trans('flarum-mentions.forum.user.mentions_link')),
      80
    );
  });

  // Remove post mentions when rendering post previews.
  getPlainContent.removeSelectors.push('a.PostMention');
});

export * from './utils/textFormatter';
