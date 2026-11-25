Installation
============

1. Download the ZIP of the latest release and install it in *Site administration > Plugins > Install plugins*, or
   copy the code to ``admin/tool/extdeps`` (``public/admin/tool/extdeps`` on Moodle 5.1 and later).
2. Upgrade Moodle. Every course is flagged for the first scan, which runs in the next cron runs.
3. Optionally install ``report_extdeps`` (screens), ``local_alerthub`` (alerts) and ``tool_prometheus`` (metrics).

Outgoing HTTP requests go to the providers (YouTube, Vimeo, eduplay) from the Moodle server, through the proxy and
the allowed hosts of the site.
