<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_004_Erp217_Notification_Center_AddTemplates extends pgMigration {

    // protected $_db_group = 'default';

    public function up()
    {
        $this->run_query("
            INSERT INTO erp_217_templates (id, alias, channel, subject, body, description, created_at, updated_at, created_by, is_deleted) VALUES
            (4, 'erp221.activity.updated.reschedule', 'push', 'Срок перенесен', 'Активность {{activity_id}} {{activity_content}} была перенесена. <br>\nНовая дата: {{new_date}}. <br>\nПричина: {{reason}} \n<br>\n<a href=\"{{site_url}}/erp221/activity/view/{{activity_id}}\">ссылка</a>', 'Для события erp221.activity.updated.reschedule', '2026-04-17 12:45:04', '2026-04-17 15:02:16', 1, 0),
            (5, 'erp221.activity.updated.edit_content', 'push', 'Содержание активности изменено', 'Содержание активности с ID {{activity_id}} было изменено. <br>\n\nСтарое содержание: {{old_content}} <br>\nНовое содержание: {{new_content}} <br>\n\n<a href=\"{{site_url}}/erp221/activity/view/{{activity_id}}\">ссылка</a>', 'Для события erp221.activity.updated.edit_content', '2026-04-17 12:47:14', '2026-04-17 15:01:05', 1, 0),
            (6, 'erp221.activity.updated.assign', 'push', 'Задача изменена: переназначение', 'Задача с ID {{activity_id}} переназначена.\nБыло: {{old_assignee}} \nСтало: {{new_assignee}}.\n\n<br>\n<a href=\"{{site_url}}/erp221/activity/view/{{activity_id}}\">ссылка</a>', 'Для события erp221.activity.updated.assign', '2026-04-17 12:48:25', '2026-04-17 14:57:57', 1, 0),
            (7, 'erp221.activity.updated.complete', 'push', 'Задача завершена', 'Задача с ID {{activity_id}} завершена.  \n<br>\n<a href=\"{{site_url}}/erp221/activity/view/{{activity_id}}\">ссылка</a>', 'Для события erp221.activity.updated.complete', '2026-04-17 12:51:34', '2026-04-17 14:58:12', 1, 0),
            (9, 'erp221.daily_agenda.ready', 'push', 'Ежедневная повестка дня готова', 'Дата: {{date}}\nЗадачи:\n{{tasks_list}}', 'Для события erp221.daily_agenda.ready', '2026-04-17 13:22:35', '2026-04-17 13:23:47', 1, 0),
            (10, 'erp221.activity.added', 'push', 'Новая активность', 'Добавлен комментарий к сделке {{deal_id}}. Содержание: {{content}}\n<br>\n<a href=\"{{site_url}}/erp221/activity/view/{{activity_id}}\">ссылка</a>', 'Для события erp221.activity.added', '2026-04-17 13:34:55', '2026-04-17 14:58:32', 1, 0)
            ON CONFLICT (id) DO UPDATE SET 
                subject = EXCLUDED.subject, 
                body = EXCLUDED.body, 
                description = EXCLUDED.description,
                updated_at = EXCLUDED.updated_at;
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_217_templates WHERE id IN (4, 5, 6, 7, 9, 10);");
    }
}