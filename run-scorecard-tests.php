<?php

echo "🏌️ Cyprus Point Scorecard Analysis\n";
echo str_repeat('=', 50)."\n";
echo "This script will process the actual scorecard images\n";
echo "and display the extracted OCR data for manual verification.\n\n";

echo "Available tests:\n";
echo "1. Cyprus Point Front Nine\n";
echo "2. Cyprus Point Back Nine\n";
echo "3. Image Preprocessing Pipeline\n";
echo "4. Run All Tests\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    echo "Please run this script from the command line:\n";
    echo "php run-scorecard-tests.php\n";
    exit(1);
}

// Check if images exist
$frontImage = __DIR__.'/tests/scorecards/cyprus-point-front.jpg';
$backImage = __DIR__.'/tests/scorecards/cyprus-point-back.jpg';

if (! file_exists($frontImage)) {
    echo "❌ Error: cyprus-point-front.jpg not found at {$frontImage}\n";
    exit(1);
}

if (! file_exists($backImage)) {
    echo "❌ Error: cyprus-point-back.jpg not found at {$backImage}\n";
    exit(1);
}

echo "✅ Both scorecard images found\n\n";

// Menu selection
echo 'Select test to run (1-4): ';
$handle = fopen('php://stdin', 'r');
$choice = trim(fgets($handle));
fclose($handle);

$testCommands = [
    '1' => 'vendor/bin/phpunit tests/Feature/ActualScorecardProcessingTest.php::test_cyprus_point_front_nine_scorecard_processing --testdox-text',
    '2' => 'vendor/bin/phpunit tests/Feature/ActualScorecardProcessingTest.php::test_cyprus_point_back_nine_scorecard_processing --testdox-text',
    '3' => 'vendor/bin/phpunit tests/Feature/ActualScorecardProcessingTest.php::test_image_preprocessing_pipeline --testdox-text',
    '4' => 'vendor/bin/phpunit tests/Feature/ActualScorecardProcessingTest.php --testdox-text',
];

if (! isset($testCommands[$choice])) {
    echo "❌ Invalid choice. Please run again and select 1-4.\n";
    exit(1);
}

echo "\n🚀 Running selected test...\n\n";

// Execute the test
$command = $testCommands[$choice];
echo "Command: {$command}\n\n";

// Run the command and display output in real-time
$process = popen($command, 'r');
if ($process) {
    while (! feof($process)) {
        echo fread($process, 4096);
        flush();
    }
    $exitCode = pclose($process);

    echo "\n\n";
    if ($exitCode === 0) {
        echo "✅ Test completed successfully!\n";
    } else {
        echo "⚠️  Test completed with exit code: {$exitCode}\n";
    }
} else {
    echo "❌ Failed to execute test command\n";
    exit(1);
}

echo "\n".str_repeat('=', 50)."\n";
echo "Manual verification complete!\n";
echo "Please review the OCR output above and verify the extracted data.\n";
