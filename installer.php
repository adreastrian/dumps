<?php
/**
 * Laravel Driver Installer
 * Detects Valet/Herd, copies Drivers.zip, extracts and cleans up
 */

function getDriverBasePath() {
    $homeDir = $_SERVER['HOME'] ?? getenv('HOME');

    // Check for Herd first (more modern)
    if (is_dir('/Applications/Herd.app') || commandExists('herd')) {
        $herdDriverPath = $homeDir . '/Library/Application Support/Herd/config/valet';
        if (is_dir($herdDriverPath)) {
            return ['tool' => 'Herd', 'path' => $herdDriverPath];
        }
        // Alternative Herd path
        $herdAltPath = $homeDir . '/.config/herd';
        if (is_dir($herdAltPath)) {
            return ['tool' => 'Herd', 'path' => $herdAltPath];
        }
    }

    // Check for Valet
    if (is_dir($homeDir . '/.config/valet') || commandExists('valet')) {
        $valetDriverPath = $homeDir . '/.config/valet';
        if (is_dir($valetDriverPath)) {
            return ['tool' => 'Valet', 'path' => $valetDriverPath];
        }
    }

    return null;
}

function commandExists($command) {
    $result = shell_exec("which $command 2>/dev/null");
    return !empty(trim($result));
}

function getUserConfirmation($message) {
    echo $message;
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    return strtolower($input) === 'y' || strtolower($input) === 'yes';
}

// Main execution
echo "🔍 Detecting Laravel environment...\n";

$result = getDriverBasePath();

if (!$result) {
    echo "❌ Neither Valet nor Herd detected\n";
    exit(1);
}

echo "✅ Using: {$result['tool']}\n";
echo "📁 Base path: {$result['path']}\n";

// Check if Drivers.zip exists in current directory
$zipFile = 'Drivers.zip';
if (!file_exists($zipFile)) {
    echo "❌ Drivers.zip not found in current directory\n";
    exit(1);
}

echo "📦 Found Drivers.zip\n";

// Show destination and ask for confirmation
$destinationPath = $result['path'];
echo "\n🎯 Will extract to: $destinationPath\n";
echo "   (The zip contains Drivers folder, so it will create/merge with $destinationPath/Drivers)\n\n";

if (!getUserConfirmation("Proceed with installation? (y/n): ")) {
    echo "❌ Installation cancelled\n";
    exit(0);
}

echo "\n📋 Installing drivers...\n";

// Copy zip file to destination
$destinationZip = $destinationPath . '/' . $zipFile;
echo "1. Copying Drivers.zip to destination...\n";
if (!copy($zipFile, $destinationZip)) {
    echo "❌ Failed to copy zip file\n";
    exit(1);
}
echo "   ✅ Copied successfully\n";

// Extract zip file
echo "2. Extracting Drivers.zip...\n";
$zip = new ZipArchive;
if ($zip->open($destinationZip) === TRUE) {

    $finalDir = $destinationPath.DIRECTORY_SEPARATOR.'Drivers';
    if(!is_dir($finalDir)){
        //make dir
        mkdir($finalDir);

    }
    echo 'will extract to: ' . $finalDir;

    $zip->extractTo($destinationPath);

    $zip->close();
    echo "   ✅ Extracted successfully\n";
} else {
    echo "❌ Failed to extract zip file\n";
    exit(1);
}

// Remove zip file from destination
echo "3. Cleaning up zip file...\n";
if (unlink($destinationZip)) {
    echo "   ✅ Zip file removed\n";
} else {
    echo "⚠️  Warning: Could not remove zip file\n";
}

// Remove laradump folder from vendor if exists
$driversPath = $destinationPath . '/Drivers';
$laradumpPath = $driversPath . '/vendor/laradumps';
// Run composer install in Drivers folder

echo "\n🎉 Installation complete!\n";
echo "📁 Drivers installed at: $driversPath\n";

// Show installed drivers
if (is_dir($driversPath)) {
    $drivers = array_filter(scandir($driversPath), function($item) use ($driversPath) {
        return $item != '.' && $item != '..' && is_file($driversPath . '/' . $item);
    });

    if (!empty($drivers)) {
        echo "\n📝 Installed drivers:\n";
        foreach ($drivers as $driver) {
            echo "   • $driver\n";
        }
    }
}

echo "\n🔗 Next steps: just install the app from here\n";
echo "\033]8;;https://laradumps.dev/get-started/installation.html\033\\https://laradumps.dev/get-started/installation.html\033]8;;\033\\\n";


function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $filePath = $dir . '/' . $file;
        if (is_dir($filePath)) {
            removeDirectory($filePath);
        } else {
            unlink($filePath);
        }
    }
    return rmdir($dir);
}
?>