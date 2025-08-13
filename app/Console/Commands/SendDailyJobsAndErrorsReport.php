<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Opcodes\LogViewer\LogFile;
use Opcodes\LogViewer\Readers\IndexedLogReader;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendDailyJobsAndErrorsReport extends Command
{
    protected $signature = 'report:daily-jobs-and-errors';

    protected $description = 'Send a daily report of failed jobs and errors/warnings';

    private function formatSlackMessage($failedJobs, $errors, $warnings)
    {
        $appName = config('app.name');

        return [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "$appName - Yesterday's Jobs and Errors Report",
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*:x: Failed Jobs:* *`$failedJobs`*",
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*:red_circle: Production Errors:* *`$errors`*",
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*:warning: Production Warnings:* *`$warnings`*",
                ],
            ],
        ];
    }

    public function handle()
    {
        $failedJobs = $this->getFailedJobsCount();
        $errors = $this->getErrorCount();
        $warnings = $this->getWarningCount();

        if ($failedJobs > 0 || $errors > 0 || $warnings > 0) {
            $blocks = $this->formatSlackMessage($failedJobs, $errors, $warnings);
            SlackAlert::to('daily-alerts')->blocks($blocks);
            $this->info('Daily report sent to Slack.');
        } else {
            $this->info('No issues to report. Slack message not sent.');
        }
    }

    private function getFailedJobsCount()
    {
        return DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();
    }

    private function getErrorCount()
    {
        return $this->countLogEntries('ERROR');
    }

    private function getWarningCount()
    {
        return $this->countLogEntries('WARNING');
    }

    // Note this assumes the log file channel is set to 'daily' in the environment file of the project
    private function countLogEntries($level)
    {
        $yesterday = now()->subDay()->format('Y-m-d');
        $filePath = storage_path("logs/laravel-{$yesterday}.log");

        // Check if the log file exists
        if (! file_exists($filePath)) {
            $this->info("Log file for {$yesterday} not found.");

            return 0;
        }

        $logFile = new LogFile($filePath);

        // Create a MultipleLogReader instance
        $logReader = new IndexedLogReader($logFile);

        // Get the level counts
        $levelCounts = $logReader->getLevelCounts();

        return $levelCounts[$level]->count ?? 0;
    }
}
