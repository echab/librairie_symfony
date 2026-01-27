<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Rayon;
use App\Entity\Post;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class MarkdownService
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function fromMarkdownFile(
        string $file,
        string $category,
    ): Post {
        $post = new Post($category);

        // $post->slug = basename($file, '.md');
        $post->slug = basename($file, '.md');

        $contents = file_get_contents($file);
        $post->date = Util::from_timestamp(filemtime($file));

        if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $contents, $matches)) {
            $yaml = $matches[1];
            $post->markdown = $matches[2];

            try {
                $data = Yaml::parse($yaml);
            } catch (ParseException $ex) {
                $this->logger->error("Parsing yaml in $file: $ex");
            }
            $post->titre = $data['titre'] ?? $post->slug;

            if (isset($data['libraire'])) {
                $post->libraire = $data['libraire'];
            }
            if (isset($data['date'])) {
                $post->date = toDate($data['date']) ?? $post->date;
            }
            if (isset($data['expire'])) {
                $post->expire = toDate($data['expire']);
            }
            if (isset($data['ean'])) {
                $post->ean = (int)$data['ean'];
            }
            if (isset($data['auteur'])) {
                $post->auteur = $data['auteur'];
            }
            if (isset($data['editeur'])) {
                $post->editeur = $data['editeur'];
            }
            if (isset($data['parution'])) {
                $post->parution = toDate($data['parution']);
            }
            if (isset($data['rayonCode'])) {
                $post->rayonCode = $data['rayonCode'];
            } else if (isset($data['rayon'])) {
                $post->rayonCode = Rayon::bySlug($data['rayon'])->code;
            }
            if (isset($data['prix'])) {
                $post->prix = $data['prix'];
            }

            if (isset($data['debut'])) {
                $post->debut = toDate($data['debut']);
                if (isset($data['fin'])) {
                    $post->fin = toDate($data['fin']);
                }
            }

        } else {
            $post->markdown = $contents;
            $post->titre = $post->slug;
        }

        return $post;
    }

    public function buildMarkdown(Post $post): string {
        // Compose markdown with YAML front matter
        $yaml = [
            'titre' => $post->titre,
        ];
        if (isset($post->auteur)) {
            $yaml['auteur'] = $post->auteur;
        }
        if (isset($post->editeur)) {
            $yaml['editeur'] = $post->editeur;
        }
        if (isset($post->parution)) {
            $yaml['parution'] = $post->parution->format('Y-m-d');
        }
        if (isset($post->prix)) {
            $yaml['prix'] = $post->prix;
        }
        if (isset($post->ean)) {
            $yaml['ean'] = $post->ean;
        }
        if (isset($post->rayonCode)) {
            $yaml['rayonCode'] = $post->rayonCode;
        }

        if (isset($post->debut)) {
            $yaml['debut'] = $post->debut->format('Y-m-d H:i');
            if (isset($post->fin)) {
                $yaml['fin'] = $post->fin->format('Y-m-d H:i');
            }
        }

        $yaml['libraire'] = $post->libraire;
        $yaml['date'] = $post->date->format('Y-m-d');
        if (isset($post->expire)) {
            $yaml['expire'] = $post->expire->format('Y-m-d');
        }
        $yaml = YAML::dump($yaml);

        return "---\n$yaml---\n$post->markdown";
    }
    
    public static function shortText(string $markdown, int $maxLine, int $maxChar) {
        $URL = "[A-Za-z0-9\-\._~:\/\?#\[\]@!\$&'\(\)\*\+,;%=]+";
        // $LINK = "!?\[[^]*\]\($URL\)";
        // preg_match_all("/$LINK/gi")
        $pos = 0;
        $nLine = 0;

        $p = strpos($markdown, "\n", $pos);
        while ($p !== false) {
            $nLine++;
            $pos = $p;
            if ($nLine >= $maxLine || $pos >= $maxChar) {
                break;
            }
            $p = strpos($markdown, "\n", $pos +1);
        }
        $result = substr($markdown, 0, $pos);

        // replace images by vignettes
        // $result = preg_replace_callback(
        //     "/(!\[[^]*\]\(($URL)(\))/",
        //     fn($a,$b,$c) => $a,
        //     $result
        // );
        return $result;
    }
}
