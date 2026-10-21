1. Diagnosis
   Component Finding Action
   Laravel 12.70.0 No action required
   Database Dashboard reports healthy Keep monitoring
   Debug mode APP_DEBUG=true Disable immediately
   Queue connection database Supported
   Cache database Supported
   ERP queue worker No ERP worker found Configure systemd
   ERP scheduler Tasks defined, execution unverified Configure cron
   Storage Mixed ownership Verify effective access
   Scheduler heartbeat Defined every minute Ensure cron executes
   CRM Blade template Syntax errors in logs Investigate if still occurring
   Your process listing shows a queue worker belonging to the USSD application, not the ERP. We should leave that worker untouched.
2. Fix debug mode immediately
   Run:
   cd /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp

sed -i 's/^APP_DEBUG=.\*/APP_DEBUG=false/' .env

/opt/plesk/php/8.4/bin/php artisan optimize:clear
/opt/plesk/php/8.4/bin/php artisan config:cache

This should clear the debug-mode warning after refreshing the dashboard. 3. Configure the ERP scheduler
Your application already contains these scheduled tasks:

- workspace:send-reminders
- sms:dispatch-scheduled
- erp-health-heartbeat
- tickets:check-sla
- vitalpbx:sync-calls
- CRM, finance, sales and contract automation jobs
  The ERP heartbeat is scheduled every minute, but your root crontab does not show an ERP scheduler entry.

First check whether Plesk has already configured one under the subscription user:
crontab -u erp.gwosevo.com_cwqrsff9a4 -l

Also inspect Plesk's scheduled tasks before adding another entry.

If no ERP scheduler exists, create one under the ERP website user:
crontab -u erp.gwosevo.com_cwqrsff9a4 -e

Add:

-   -   -   -   - cd /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp && /opt/plesk/php/8.4/bin/php artisan schedule:run >> /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp/storage/logs/scheduler.log 2>&1

This runs the scheduler every minute and records output in scheduler.log.

For an initial test:
runuser -u erp.gwosevo.com_cwqrsff9a4 -- \
 /opt/plesk/php/8.4/bin/php \
 /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp/artisan schedule:run

After a few minutes, refresh the Security Centre. 4. Configure a persistent ERP queue worker
I recommend systemd for your production Laravel database queue.

Create the service:
nano /etc/systemd/system/gwosevo-erp-queue.service

Paste:
[Unit]
Description=GWO SEVO ERP Laravel Queue Worker
After=network.target mariadb.service

[Service]
Type=simple
User=erp.gwosevo.com_cwqrsff9a4
Group=psacln

WorkingDirectory=/var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp

ExecStart=/opt/plesk/php/8.4/bin/php artisan queue:work database --queue=sms,default --sleep=3 --tries=3 --timeout=120 --max-time=3600

Restart=always
RestartSec=5

TimeoutStopSec=180
KillSignal=SIGTERM

StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target

Before enabling it, verify that the application's retry_after setting exceeds the 120-second worker timeout, and that any jobs requiring longer processing have appropriate timeouts.

Then activate:
systemctl daemon-reload

systemctl enable --now gwosevo-erp-queue.service

systemctl status gwosevo-erp-queue.service

Check logs:
journalctl -u gwosevo-erp-queue.service -n 50 --no-pager

This starts a persistent worker and automatically restarts it if it exits.

Note: The ERP heartbeat warning may remain until its custom worker heartbeat mechanism is verified. Laravel does not automatically publish a heartbeat simply because queue:work is running. 5. Investigate storage permissions
Your logs show:
storage root:root
storage/framework erp.gwosevo.com_cwqrsff9a4:psacln
storage/logs erp.gwosevo.com_cwqrsff9a4:psacln
bootstrap/cache erp.gwosevo.com_cwqrsff9a4:psacln

The storage directory being owned by root does not necessarily cause a problem because it is traversable, while the important child directories are owned by the ERP user.

First test write access:
runuser -u erp.gwosevo.com_cwqrsff9a4 -- \
 test -w /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp/storage/framework/views \
 && echo "Views writable"

runuser -u erp.gwosevo.com_cwqrsff9a4 -- \
 test -w /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp/storage/logs \
 && echo "Logs writable"

runuser -u erp.gwosevo.com_cwqrsff9a4 -- \
 test -w /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp/bootstrap/cache \
 && echo "Cache writable"

If these tests succeed, do not recursively change permissions yet. Use the System health details button to determine which exact storage check is failing. 6. Investigate the CRM Blade error
Your log contains repeated errors involving:
resources/views/crm/workspace.blade.php

Specifically:
syntax error, unexpected end of file,
expecting "elseif" or "else" or "endif"

This suggests an unclosed Blade conditional, such as @if without @endif.

However, the errors shown are historical. They do not establish that the problem is still present.

You can test Blade compilation:
cd /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp

/opt/plesk/php/8.4/bin/php artisan view:cache

If compilation fails, inspect the relevant Blade template.

7. Expected Security Centre results
   After the changes, your goal is:
   Component Target
   Database Healthy
   Application storage Healthy
   Application key Healthy
   Queue backlog Healthy
   Queue worker heartbeat Healthy
   Scheduler heartbeat Healthy
   Debug mode Disabled / Healthy
   The worker and scheduler heartbeat indicators will only turn healthy if the ERP's health-monitoring implementation correctly records their activity.
8. Final verification
   Run:
   echo "=== ERP QUEUE SERVICE ==="
   systemctl status gwosevo-erp-queue.service --no-pager

echo "=== ERP SCHEDULER CRON ==="
crontab -u erp.gwosevo.com_cwqrsff9a4 -l

echo "=== ERP WORKER PROCESS ==="
ps aux | grep '[a]rtisan queue:work'

echo "=== SCHEDULER LOG ==="
tail -n 30 /var/www/vhosts/erp.gwosevo.com/httpdocs/gwosevo-erp/storage/logs/scheduler.log
