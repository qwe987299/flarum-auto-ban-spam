import app from 'flarum/admin/app';

app.initializers.add('qwe987299-auto-ban-spam', () => {
  app.extensionData
    .for('qwe987299-auto-ban-spam')
    .registerSetting({
      setting: 'auto_ban_spam.keywords',
      label: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.keywords_label'),
      help: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.keywords_help'),
      type: 'textarea',
    })
    .registerSetting({
      setting: 'auto_ban_spam.action_type',
      label: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.action_type_label'),
      help: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.action_type_help'),
      type: 'select',
      options: {
        soft: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.action_type_soft'),
        hard: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.action_type_hard'),
      },
      default: 'soft',
    })
    .registerSetting({
      setting: 'auto_ban_spam.only_recent_days',
      label: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.only_recent_days_label'),
      help: app.translator.trans('qwe987299-auto-ban-spam.admin.settings.only_recent_days_help'),
      type: 'number',
      placeholder: '0',
      default: 0,
    })
    .registerPermission(
      {
        icon: 'fas fa-shield-alt',
        label: app.translator.trans('qwe987299-auto-ban-spam.admin.permissions.bypass_label'),
        permission: 'autoBanSpam.bypass',
      },
      'moderate'
    );
});
