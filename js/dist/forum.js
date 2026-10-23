(() => {
  "use strict";
  function getModule(id) {
    if (typeof flarum !== "undefined") {
      if (flarum.reg && typeof flarum.reg.get === "function") {
        try {
          const mod = flarum.reg.get("core", id);
          if (mod) return (mod && mod.__esModule && typeof mod.default !== "undefined") ? mod.default : mod;
        } catch (e) {}
      }
      if (flarum.core && flarum.core.compat && flarum.core.compat[id]) {
        const mod = flarum.core.compat[id];
        return (mod && mod.__esModule && typeof mod.default !== "undefined") ? mod.default : mod;
      }
    }
    return null;
  }

  const app = getModule("forum/app") || (typeof flarum !== "undefined" ? flarum.app : null) || (typeof window !== "undefined" ? window.app : null);
  const commonExtend = getModule("common/extend");
  const extend = commonExtend ? commonExtend.extend : null;
  const UserControls = getModule("forum/utils/UserControls");
  const Button = getModule("common/components/Button");
  const Modal = getModule("common/components/Modal");

  if (Modal && app && extend && UserControls && Button) {

    class ManualBanModal extends Modal {
      className() {
        return "ManualBanModal Modal--small";
      }

      title() {
        return app.translator.trans("qwe987299-auto-ban-spam.forum.modal.title");
      }

      content() {
        const user = this.attrs.user;
        const username = user ? (typeof user.username === "function" ? user.username() : user.attribute("username")) : "";
        return [
          m("div", { className: "Modal-body" },
            m("p", null, app.translator.trans("qwe987299-auto-ban-spam.forum.modal.body", { username: username }))
          ),
          m("div", { className: "Modal-footer" }, [
            m(Button, {
              className: "Button Button--danger",
              style: { marginRight: "8px" },
              loading: this.loading,
              onclick: () => this.onsubmit()
            }, app.translator.trans("qwe987299-auto-ban-spam.forum.modal.confirm_button")),
            m(Button, {
              className: "Button",
              onclick: () => this.hide()
            }, app.translator.trans("qwe987299-auto-ban-spam.forum.modal.cancel_button"))
          ])
        ];
      }

      onsubmit() {
        this.loading = true;
        const user = this.attrs.user;
        const userId = user ? (typeof user.id === "function" ? user.id() : user.attribute("id")) : null;
        if (!userId) return;

        app.request({
          method: "POST",
          url: app.forum.attribute("apiUrl") + "/qwe987299-auto-ban-spam/ban/" + userId,
        }).then(() => {
          this.loading = false;
          this.hide();
          if (app.alerts && app.alerts.show) {
            app.alerts.show({ type: "success" }, app.translator.trans("qwe987299-auto-ban-spam.forum.modal.success_message"));
          }
          window.location.reload();
        }).catch((err) => {
          this.loading = false;
          if (typeof m !== "undefined" && m.redraw) {
            m.redraw();
          }
        });
      }
    }

    if (app && app.initializers) {
      app.initializers.add("qwe987299-auto-ban-spam-forum", function() {
        extend(UserControls, "userControls", function(items, user) {
          if (user && user.attribute("canAutoBanSpamManualBan") && !user.attribute("isAdmin")) {
            items.add("autoBanSpamManual", Button.component({
              icon: "fas fa-user-slash",
              onclick: () => app.modal.show(ManualBanModal, { user: user })
            }, app.translator.trans("qwe987299-auto-ban-spam.forum.user_controls.manual_ban_button")), -100);
          }
        });
      });
    }
  }

  if (typeof module !== "undefined") {
    module.exports = {};
  }
})();
module.exports = typeof module !== "undefined" && module.exports ? module.exports : {};

