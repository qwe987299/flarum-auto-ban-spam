import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserControls from 'flarum/forum/utils/UserControls';
import Button from 'flarum/common/components/Button';
import Modal from 'flarum/common/components/Modal';

class ManualBanModal extends Modal {
  className() {
    return 'ManualBanModal Modal--small';
  }

  title() {
    return app.translator.trans('qwe987299-auto-ban-spam.forum.modal.title');
  }

  content() {
    const user = this.attrs.user;
    return [
      <div className="Modal-body">
        <p>{app.translator.trans('qwe987299-auto-ban-spam.forum.modal.body', { username: user.username() })}</p>
      </div>,
      <div className="Modal-footer">
        <Button
          className="Button Button--danger"
          style={{ marginRight: '8px' }}
          loading={this.loading}
          onclick={() => this.onsubmit()}
        >
          {app.translator.trans('qwe987299-auto-ban-spam.forum.modal.confirm_button')}
        </Button>
        <Button
          className="Button"
          onclick={() => this.hide()}
        >
          {app.translator.trans('qwe987299-auto-ban-spam.forum.modal.cancel_button')}
        </Button>
      </div>
    ];
  }

  onsubmit() {
    this.loading = true;
    const user = this.attrs.user;
    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/qwe987299-auto-ban-spam/ban/' + user.id(),
    }).then(() => {
      this.loading = false;
      this.hide();
      app.alerts.show({ type: 'success' }, app.translator.trans('qwe987299-auto-ban-spam.forum.modal.success_message'));
      window.location.reload();
    }).catch((err) => {
      this.loading = false;
      m.redraw();
    });
  }
}

app.initializers.add('qwe987299-auto-ban-spam-forum', () => {
  extend(UserControls, 'userControls', function (items, user) {
    if (user && user.attribute('canAutoBanSpamManualBan') && !user.attribute('isAdmin')) {
      items.add('autoBanSpamManual', Button.component({
        icon: 'fas fa-user-slash',
        onclick: () => app.modal.show(ManualBanModal, { user: user })
      }, app.translator.trans('qwe987299-auto-ban-spam.forum.user_controls.manual_ban_button')), -100);
    }
  });
});
