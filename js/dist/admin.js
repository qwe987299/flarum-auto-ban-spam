(() => {
  "use strict";

  function getModule(id) {
    if (typeof flarum !== "undefined") {
      if (flarum.reg && typeof flarum.reg.get === "function") {
        try {
          const mod = flarum.reg.get("core", id);
          if (mod) return (mod && mod.__esModule && mod.default) ? mod.default : mod;
        } catch (e) {}
      }
      if (flarum.core && flarum.core.compat && flarum.core.compat[id]) {
        const mod = flarum.core.compat[id];
        return (mod && mod.__esModule && mod.default) ? mod.default : mod;
      }
    }
    return null;
  }

  const app = getModule("admin/app");
  const Extend = getModule("common/extenders");

  const extenders = [];

  if (Extend && Extend.Admin) {
    extenders.push(
      new Extend.Admin()
        .setting(() => ({
          setting: "auto_ban_spam.keywords",
          label: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.keywords_label") : "Keywords",
          help: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.keywords_help") : "",
          type: "textarea"
        }))
        .setting(() => ({
          setting: "auto_ban_spam.action_type",
          label: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_label") : "Action Strategy",
          help: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_help") : "",
          type: "select",
          options: {
            soft: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_soft") : "Soft Delete",
            hard: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.action_type_hard") : "Hard Delete"
          },
          default: "soft"
        }))
        .setting(() => ({
          setting: "auto_ban_spam.only_recent_days",
          label: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.only_recent_days_label") : "Registration Threshold",
          help: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.settings.only_recent_days_help") : "",
          type: "number",
          placeholder: "0",
          default: 0
        }))
        .permission(() => ({
          icon: "fas fa-shield-alt",
          label: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.permissions.bypass_label") : "Bypass",
          permission: "autoBanSpam.bypass"
        }), "moderate")
        .permission(() => ({
          icon: "fas fa-user-slash",
          label: app ? app.translator.trans("qwe987299-auto-ban-spam.admin.permissions.manual_ban_label") : "Manual Ban",
          permission: "autoBanSpam.manualBan"
        }), "moderate")
    );
  }

  // Flarum 1.x legacy initializers fallback (if Extend.Admin is not loaded)
  if (app && app.initializers) {
    app.initializers.add("qwe987299-auto-ban-spam", function() {
      const registry = app.extensionData || app.registry;
      if (registry && (!extenders || extenders.length === 0)) {
        registry.for("qwe987299-auto-ban-spam")
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
          .registerSetting({
            setting: "auto_ban_spam.only_recent_days",
            label: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.only_recent_days_label"),
            help: app.translator.trans("qwe987299-auto-ban-spam.admin.settings.only_recent_days_help"),
            type: "number",
            placeholder: "0",
            default: 0
          })
          .registerPermission({
            icon: "fas fa-shield-alt",
            label: app.translator.trans("qwe987299-auto-ban-spam.admin.permissions.bypass_label"),
            permission: "autoBanSpam.bypass"
          }, "moderate")
          .registerPermission({
            icon: "fas fa-user-slash",
            label: app.translator.trans("qwe987299-auto-ban-spam.admin.permissions.manual_ban_label"),
            permission: "autoBanSpam.manualBan"
          }, "moderate");
      }
    });
  }

  const exportObj = {
    extend: extenders,
    default: extenders
  };

  if (typeof module !== "undefined") {
    module.exports = exportObj;
  }
})();
module.exports = typeof module !== "undefined" && module.exports ? module.exports : {};

