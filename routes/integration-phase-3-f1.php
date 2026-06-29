<?php

/**
 * Integration Phase 3 — F1 Saved report scheduling.
 *
 * HTTP routes (already wired in routes/web.php — verify names remain registered):
 *   GET    saved-reports                      saved-reports.index
 *   POST   saved-reports                      saved-reports.store
 *   PUT    saved-reports/{savedReport}        saved-reports.update
 *   DELETE saved-reports/{savedReport}        saved-reports.destroy
 *   POST   saved-reports/{savedReport}/run    saved-reports.run
 *   GET    saved-reports/{savedReport}/export   saved-reports.export
 *
 * Scheduler (Integration Lead — register in app console schedule, not bootstrap/app.php
 * unless your project uses routes/console.php scheduling):
 *   $schedule->command('reports:process-scheduled')->everyMinute();
 *
 * Artisan command: reports:process-scheduled
 * Job: App\Jobs\RunSavedReportJob
 *
 * Nav: Reports → Saved reports (existing AdminTopNav / TenantHub entry).
 */
