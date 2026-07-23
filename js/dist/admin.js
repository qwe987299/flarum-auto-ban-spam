(() => {
  "use strict";
  if (typeof flarum !== "undefined" && flarum.core && flarum.core.compat) {
    const app = flarum.core.compat["admin/app"];
    if (app && app.initializers) {
      app.initializers.add("qwe987299-auto-ban-spam", function() {
        if (app.extensionData) {
          app.extensionData.for("qwe987299-auto-ban-spam")
            .registerSetting({
              setting: "auto_ban_spam.keywords",
              label: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.keywords_label"),
              help: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.keywords_help"),
              type: "textarea"
            })
            .registerSetting({
              setting: "auto_ban_spam.action_type",
              label: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_label"),
              help: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_help"),
              type: "select",
              options: {
                soft: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_soft"),
                hard: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_hard")
              },
              default: "soft"
            })
            .registerPermission({
              icon: "fas fa-shield-alt",
              label: app.translator.trans("qwe987299-auto-ban-spam.admin.permissions.bypass_label"),
              permission: "autoBanSpam.bypass"
            }, "moderate");

          app.extensionData.for("qwe987299-flarum-auto-ban-spam")
            .registerSetting({
              setting: "auto_ban_spam.keywords",
              label: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.keywords_label"),
              help: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.keywords_help"),
              type: "textarea"
            })
            .registerSetting({
              setting: "auto_ban_spam.action_type",
              label: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_label"),
              help: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_help"),
              type: "select",
              options: {
                soft: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_soft"),
                hard: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_hard")
              },
              default: "soft"
            })
            .registerPermission({
              icon: "fas fa-shield-alt",
              label: app.translator.trans("qwe987299-auto-ban-spam.admin.permissions.bypass_label"),
              permission: "autoBanSpam.bypass"
            }, "moderate");
        }
      });
    }
  }
})();
module.exports = {};
