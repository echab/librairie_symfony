<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Livre;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
// use Symfony\Component\Yaml\Yaml;

class StockRepository
{
    private string $stockFile;
    public ?\DateTime $stockDate;
    private $rayons;

    public const RE_FILENAME = '/stock_(20\d\d-\d\d-\d\dT\d\d-\d\d-\d\d)\.json(\.gz)?$/';

    public function __construct(
        private string $stockDir,
        private LoggerInterface $logger,
    ) {}

    /** Find more recent stock json.gz file, and init stockFile and stockDate class members */
    public function findStockFile(): ?string
    {
        if (isset($this->stockFile)) {
            return $this->stockFile;
        }
        $filename = null;
        $date = null;
        $dataNames = "$this->stockDir/stock_20[0-9][0-9]-[0-9][0-9]-[0-9][0-9]T[0-9][0-9]-[0-9][0-9]-[0-9][0-9].json*";

        foreach (glob($dataNames) as $f) {
            if (1 === preg_match(self::RE_FILENAME, $f, $matches)) {
                if ($date < $matches[1] || ($date === $matches[1] &&  0 === substr_compare($f, '.gz', -3))) {
                    $filename = $f;  //keep the last one, the more recent, prefering gz
                    $date = $matches[1];
                }
            }
        }
        if (!$filename) {
            return null;
        }
        $this->stockFile = $filename;
        $this->stockDate = $date ? date_create_from_format('Y-m-d\TH-i-s', $date) : null;
        return $this->stockFile;
    }

    /**
     * Returns an array of all livres metadata sorted by date desc
     * @param null|(callable(string):bool) $filter 
     * @return array<Livre>
     */
    public function findAll(
        int $rayon = 0, // si négatif cherche hors du rayon
        ?callable $filter = null,
        int $offset = 0,
        int $limit = 20
    ) {
        $stockFile = $this->findStockFile();
        if (!$stockFile) {
            // yield from [];
            return [];
        }

        $livres = $this->readStockFile($stockFile);
        // $livres = new \ArrayIterator($livres);

        if ($rayon !== 0 || !empty($filter)) {
            $livres = array_filter(
            // $livres = new \CallbackFilterIterator(
                $livres,
                fn(Livre $current) => (
                    $rayon === 0
                    || ($rayon > 0 && $current->rayon === $rayon)
                    || ($rayon < 0 && $current->rayon !== -$rayon)
                ) && (
                    $filter === null
                    || $filter("$current->titre $current->auteur")
                )
            );
        }

        // $livres = new \LimitIterator(
        if (\count($livres) > $limit) {
            $livres = \array_slice(
                // array_values($livres),
                $livres,
                $offset,
                $limit,
            );
        }

        return $livres;
    }

    public function findByEAN(int $ean): ?Livre
    {
        $stockFile = $this->findStockFile();
        if (!$stockFile) {
            return null;
        }

        $livres = $this->readStockFile($stockFile);

        foreach ($livres as $livre) {
            if ($livre->ean === $ean) {
                return $livre;
            }
        }
        return null;
    }

    /**
     * Read json.gz file and return its content as array of objects, sorted by date desc
     * @return array<Livre>
     */
    public function readStockFile(string $file)
    {
        try {
            //ungzip file
            $gz = file_get_contents($file);
            if ($gz[0] !== "\x1f" || $gz[1] !== "\x8b") {
                throw new \Exception('Signature gz invalide');
            }
            $json = gzdecode($gz, 2000000);
            if ($json[0] !== '[') {
                throw new \Exception('Liste json invalide');
            }

            //parse json
            $array = json_decode($json, false, 3);
        } catch (\Exception $e) {
            $this->logger->error("readStockFile $file error: $e");
            return [];
        }

        $result = [];
        foreach ($array as $o) {
            if (isset($o->i)) {
                $result[] = new Livre($o, '');
            }
        }

        // $this->stockDate = $array[array_key_last($array)]->d;

        usort(
            $result,
            fn(Livre $a, Livre $b) => $b->parution->getTimestamp() - $a->parution->getTimestamp()
        );

        return $result;
    }

    /** @return string[] */
    public function saveStockFile(UploadedFile $stockFiles)
    {

        /** @var string[] */ $errors = [];

        $error = $stockFiles->getError();
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur d'upload $error";
            return $errors;
        }

        $size = $stockFiles->getSize();
        if ($size < 100 || 500000 < $size) {
            $errors[] = "Taille de fichier invalide $size";
        }

        $filename = $stockFiles->getClientOriginalName(); // basename($stockFiles['name']);
        if (1 !== preg_match(StockRepository::RE_FILENAME, $filename, $matches)) {
            $errors[] = "Nom de fichier invalide $filename";
            return $errors;
        }

        //ungzip and decode the tmp file
        $stock = $this->readStockFile($stockFiles->getPathname());
        if (!$stock) {
            $err = error_get_last();
            $errors[] = "Erreur de décodage json.gz: " . $err['message'];
            return $errors;
        }

        // TODO decode json to get date into last item
        // $checkDate = end($stock)->d;
        // if (strpos($filename, str_replace(':', '-', $checkDate)) === FALSE) {
        //     $err = error_get_last();
        //     $errors[] = "Date invalide dans le fichier json.gz [$checkDate]: " . $err['message'];
        // }

        try {
            $stockFiles->move($this->stockDir, $filename);
        } catch (FileException $e) {
            $errors[] = "Failed to upload image $filename.";
        }

        return $errors;
    }
}
