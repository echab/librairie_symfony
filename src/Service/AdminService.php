<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

class AdminService
{
    public function __construct(
        protected string $projectDir,
        protected LoggerInterface $logger,
        // private CacheInterface $pool,
    ) {}

    /**
     * @param string $zipFile
     * @param string $targetFolder
     * @param int $currentIndex
     * @param int $numFiles
     * @return \Generator<mixed, string, mixed, bool>
     */
    public function unzip(string $zipFile, string $targetFolder, int &$numFiles, int $currentIndex = 0): \Generator
    {
        $zip = new \ZipArchive;
        if (!$zip->open($zipFile, \ZipArchive::RDONLY)) {
            return false;
        }
        $numFiles = $zip->numFiles;

        $startTime = time();
        $timeout = $startTime + (int)ini_get('max_execution_time') - 5;

        if ($currentIndex > 0) {
            yield "Starting at $currentIndex";
        }

        $nbWritten = 0;
        $nbIdentical = 0;
        $nbError = 0;
        /** @var string[] */ $toBeExtracted = [];

        for (; $currentIndex < $zip->numFiles; $currentIndex++) {

            // $zip->getStreamIndex($currentIndex); // php 8.2
            $fileStat = $zip->statIndex($currentIndex);
            $filename = $fileStat['name']; // $zip->getNameIndex($currentIndex);

            $targetFile = $targetFolder . DIRECTORY_SEPARATOR . $filename;
            $targetExists = file_exists($targetFile);
            $targetSize = $targetExists ? filesize($targetFile) : 0;
            $fileOk = $targetExists && $targetSize === $fileStat['size']; // check $fileStat['crc'];
            if ($fileOk) {
                // compare mtime for files of same size
                $targetTime = filemtime($targetFile);
                $fileOk = $targetTime !== false && $fileStat['mtime'] === $targetTime;
                if (!$fileOk) {
                    yield "Newer file $filename (same size $targetSize) " .
                        date('Y-m-d H:m', $fileStat['mtime']) . ' ~ ' .
                        date('Y-m-d H:m', $targetTime);
                }
            }
            if ($fileOk) {
                // yield "$currentIndex ✅ $filename";
                $nbIdentical++;
            } else {
                // yield "$currentIndex " . ($targetExists ? '🔺' : '') ." $filename";
                $toBeExtracted[] = $filename;

                if (count($toBeExtracted) >= 100) {
                    $r = $zip->extractTo($targetFolder, $toBeExtracted);
                    $first = $toBeExtracted[array_key_first($toBeExtracted)];
                    $last = $toBeExtracted[array_key_last($toBeExtracted)];
                    $count = count($toBeExtracted);
                    if ($r) { $nbWritten += $count; } else { $nbError += $count; }
                    yield $r ? "✅ $count fichiers : $first 🡲 $last" : "🔺 $zipFile unzip error!";
                    $toBeExtracted = [];
                }
            }

            if (time() > $timeout) {
                yield "🔺 timeout after $currentIndex !";
                break;
            }
        }

        if (count($toBeExtracted)) {
            // yield "$currentIndex ";
            $r = $zip->extractTo($targetFolder, $toBeExtracted);
            $first = $toBeExtracted[array_key_first($toBeExtracted)];
            $last = $toBeExtracted[array_key_last($toBeExtracted)];
            $count = count($toBeExtracted);
            if ($r) { $nbWritten += $count; } else { $nbError += $count; }
            yield $r ? "✅ $count fichiers : $first 🡲 $last" : "🔺 $zipFile unzip error!";
        }

        yield "Total: ✅ $nbWritten fichiers écrits  ❎ $nbIdentical fichiers identiques" . ($nbError ? "  🔺 $nbError erreurs." : '.');

        $zip->close();
        return true;
    }

    /**
     * @param string $zipFile
     * @param string $sourceFolder
     * @param string $filePattern
     * @param \DateTime $fromDate
     * @return \Generator<mixed, string, mixed, bool>
     */
    public function zip(string $zipFile, string $sourceFolder, string $filePattern, ?\DateTime $fromDate): \Generator
    {
        $zip = new \ZipArchive;
        if (!$zip->open($zipFile, \ZipArchive::CREATE)) {
            return false;
        }
        $sourceFolder = realpath($sourceFolder);

        if ($fromDate) {
            $fromDate = $fromDate->getTimestamp();
        }

        foreach (glob("$sourceFolder/$filePattern") as $file) {
            if ($fromDate && filemtime($file) < $fromDate) {
                continue;
            }
            $entryName = preg_replace('/\\\\/', '/', substr($file, strlen($sourceFolder) + 1));
            yield "adding file $entryName";
            if (!$zip->addFile($file, $entryName)) {
                yield "🔺Error adding file $entryName";
                break;
            }
        }

        // echo "numfiles: " . $zip->numFiles . "\n";
        // echo "status:" . $zip->status . "\n";
        $zip->close();
        return true;
    }

    public function savePassword(string $userId, string $hash)
    {
        $envFile = "$this->projectDir/.env.local.php";
        if (!file_exists($envFile)) {
            return "Fichier introuvable $envFile";
        }

        $envData = require $envFile;

        $envData[strtoupper($userId) . '_PASSWORD'] = $hash;

        $envDump = [];
        foreach ($envData as $key => $value) {
            $envDump[] = "  '$key' => '$value',\n";
        }
        $envDump = implode($envDump);

        $envSource = <<<PHP
<?php

// This file was generated by running "composer dump-env prod"

return array (
 // 'APP_ENV' => 'prod',
 // 'APP_ENV' => 'dev',
 // 'APP_DEBUG' => true, // TODO for error 500 diagnostic only!!

$envDump);
PHP;

        if (false === file_put_contents($envFile, $envSource)) {
            return "Erreur d'écriture du fichier $envFile";
        }

        return false;
    }
}
