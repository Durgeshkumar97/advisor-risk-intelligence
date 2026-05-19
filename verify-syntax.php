<?php

// Quick syntax check for refactored files
$filesToCheck = [
    'app/Http/Controllers/PortfolioUploadController.php',
    'app/Http/Requests/StorePortfolioUploadRequest.php',
    'app/Services/PortfolioUploadService.php',
    'app/Services/PortfolioUploadException.php',
    'app/Services/UploadedPortfolioDTO.php',
    'app/Events/PortfolioFileUploaded.php',
    'app/Listeners/LogPortfolioFileUpload.php',
    'app/Jobs/ProcessPortfolioFile.php',
];

$basePath = __DIR__;
$allValid = true;

foreach ($filesToCheck as $file) {
    $path = $basePath . '/' . $file;
    if (!file_exists($path)) {
        echo "❌ File not found: $file\n";
        $allValid = false;
        continue;
    }

    $result = php_check_syntax($path);
    if ($result === true) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file: $result\n";
        $allValid = false;
    }
}

echo "\n" . ($allValid ? "✅ All files have valid syntax!" : "❌ Some files have syntax errors");
exit($allValid ? 0 : 1);
