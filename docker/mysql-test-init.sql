-- Grant the test user privileges on per-worker databases created by
-- Laravel's `php artisan test --parallel` (ttsband_test_1, ttsband_test_2, ...).
GRANT ALL PRIVILEGES ON `ttsband_test_%`.* TO 'tts'@'%';
FLUSH PRIVILEGES;
