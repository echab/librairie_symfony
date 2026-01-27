<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Post;
use App\Entity\Rayon;
use App\Service\PostRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// php bin/console app:import-wordpress-xml C:\Users\chabaud\Documents\perso\Librairie\data\librairie-lespassantes.WordPress.2025-07-31_coups-de-coeur_Tania.xml

// debug with "Launch symfony Command" in .vscode\launch.json

#[AsCommand(
    name: 'app:import-wordpress-xml',
    description: 'Import some posts from a WordPress xml file',
)]
class ImportWordpressXmlCommand extends Command
{
    private SymfonyStyle $io;

    private \XMLParser $parser;

    /** @var string[] */
    private array $ancestors = [];

    protected Post $post;

    protected string $content = '';

    protected string $status = '';
    protected string $metaKey = '';

    protected int $nbSave = 0;

    public function __construct(
        private PostRepository $postRepository,
    ) {
        parent::__construct();

        $this->parser = xml_parser_create();

        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);

        xml_set_element_handler($this->parser, self::startElement(...), self::endElement(...));
        xml_set_character_data_handler($this->parser, self::cdata(...));
    }

    public function __destruct()
    {
        xml_parser_free($this->parser);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('xmlFile', InputArgument::REQUIRED, 'WordPress exported xml file')
            // ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('xmlFile');

        $this->io->info("Parsing $file ...");

        if (!($fp = fopen($file, 'r'))) {
            $this->io->error("could not open XML file '{$file}'");
            return Command::FAILURE;
        }

        $this->ancestors = [];

        while ($data = fread($fp, 4096)) {
            if (!xml_parse($this->parser, $data, feof($fp))) {
                $this->io->error(sprintf(
                    "XML error: %s at line %d",
                    xml_error_string(xml_get_error_code($this->parser)),
                    xml_get_current_line_number($this->parser)
                ));
                fclose($fp);
                return Command::FAILURE;
            }
        }

        fclose($fp);

        $this->io->success("Import done, $this->nbSave posts.");
        return Command::SUCCESS;
    }

    //region xml parsing
    private function startElement(\XMLParser $parser, string $name, array $attrs)
    {
        if (count($this->ancestors) >= 2) {
            $parent = $this->ancestors[array_key_last($this->ancestors)];
            $parents = "$parent/$name";
            switch ($parents) {
                case 'channel/item':
                    $this->post = new Post('inconnu');
                    $this->content = '';
                    $this->metaKey = '';
                    $this->status = '';
                    break;
                case 'item/category':
                    if (preg_match('/^[a-z]-/', $attrs['nicename'])) {
                        $this->post->category = substr($attrs['nicename'], 2);
                        $rayon = Rayon::bySlug($this->post->category);
                        if ($rayon === null) {
                            $this->io->warning('Catégorie inconnue "' . $this->post->category . '" ligne ' . xml_get_current_line_number($parser));
                        }
                        $this->post->rayonCode = ($rayon ?? Rayon::$defaut)->code;
                    }
                    break;
            }
        }
        $this->ancestors[] = $name;
    }

    private function cdata(\XMLParser $parser, string $cdata)
    {
        $cdata = trim($cdata, " \n\r\t\v\0\"'");
        if (\strlen($cdata) && count($this->ancestors) >= 2) {
            $parent = $this->ancestors[array_key_last($this->ancestors)];
            $gparent = $this->ancestors[array_key_last($this->ancestors) - 1];
            $parents = "$gparent/$parent";
            switch ($parents) {
                case 'item/title':
                    $this->post->titre = self::parseHtml($cdata);
                    break;
                case 'item/dc:creator':
                    $this->post->libraire = $cdata;
                    break;
                case 'item/content:encoded':
                    $this->content .= $cdata;
                    break;
                case 'item/wp:post_date':
                    $this->post->date = new \DateTime($cdata);
                    break;
                case 'item/wp:post_name':
                    $this->post->slug = $this->post->date->format('Y-m-d') . '-' . $cdata;
                    break;

                case 'item/wp:status':
                    $this->status = $cdata;
                    break;
                case 'wp:postmeta/wp:meta_key':
                    $this->metaKey = $cdata;
                    break;

                case 'wp:postmeta/wp:meta_value':
                    switch ($this->metaKey) {
                        case 'EAN':
                            $this->post->ean = (int)$cdata;
                            break;
                        case 'Auteur':
                            $this->post->auteur = self::parseHtml($cdata);
                            break;
                        case 'Editeur':
                            $this->post->editeur = $cdata;
                            break;
                        case 'Prix':
                            $this->post->prix = (float)$cdata;
                            break;
                        case 'parution':
                            $this->post->parution = \DateTime::createFromFormat('!Ymd', $cdata);
                            break;
                    }
                    $this->metaKey = '';
                    break;
            }
        }
    }

    private function endElement(\XMLParser $parser, string $name)
    {
        array_pop($this->ancestors);

        if (count($this->ancestors) >= 2) {
            $parent = $this->ancestors[array_key_last($this->ancestors)];
            $parents = "$parent/$name";
            switch ($parents) {
                case 'channel/item':
                    if ($this->status === 'publish') {

                        $this->post->markdown = self::parseHtml($this->content);

                        // remove single local image, to be replaced by a static one below
                        if (
                            isset($this->post->ean)
                            && str_contains($this->post->markdown, '![')
                            && preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $this->post->markdown, $matches) === 1
                        ) {
                            if (str_starts_with($matches[2][0], '/wp-content/uploads/')) {
                                $this->post->markdown = trim(str_replace($matches[0][0], '', $this->post->markdown));
                            }
                        }

                        if (isset($this->post->ean) && !str_contains($this->post->markdown, '![')) {
                            $ean = $this->post->ean;
                            $this->post->markdown = "![couverture](https://products-images.di-static.com/image/livre/$ean-200x303-1.jpg#gauche)\n\n" . $this->post->markdown;
                        }

                        // $this->io->info("saving " . xml_get_current_line_number($parser) .' ' . $this->post->category . " - " . $this->post->titre);
                        $this->nbSave++;

                        $errors = $this->postRepository->save($this->post, null);
                        foreach ($errors as $error) {
                            $this->io->error($error . ' ligne ' . xml_get_current_line_number($parser) . ' saving ' . $this->post->category . " - " . $this->post->titre);
                        }
                    }
                    // $this->post = null;
                    $this->content = '';
                    $this->status = '';
                    $this->metaKey = '';
                    break;
            }
        }
    }
    //endregion

    public static function parseHtml(string $html)
    {
        $md = $html;
        $md = preg_replace('/<strong>|<\/strong>|<b>|<\/b>/', '**', $md);
        $md = preg_replace('/<i>|<\/i>|<em>|<\/em>/', '*', $md);

        $md = preg_replace('/<span style="text-decoration: underline;">([^<]+?)<\/span>/', '$1', $md);
        $md = preg_replace('/<span style="font-size: inherit;">([^<]+?)<\/span>/', '$1', $md);

        // $md = preg_replace('/<span\s+class="[^"]*">([^<]+?)<\/span>/', '$1', $md);
        // $md = preg_replace('/<span\s+class="[^"]*">([^<]+?)<\/span>/', '$1', $md); // twice
        // $md = preg_replace('/<span\s+data-contrast="none">([^<]+?)<\/span>/', '$1', $md); // twice
        // $md = preg_replace('/<span\s+id="[^"]+">([^<]+?)<\/span>/', '$1', $md);
        // $md = preg_replace('/<span\s+id="[^"]+">([^<]+?)<\/span>/', '$1', $md); // twice
        // $md = preg_replace('/<span\s+class=""\s+title="Modifié">([^<]+?)<\/span>/', '$1', $md);
        // $md = preg_replace('/<span style="border-radius[^>]+>([^<]+?)<\/span>/', '', $md);
        $md = preg_replace('/<span[^>]*>|<\/span>/', '', $md);

        $md = preg_replace('/<section class="[^"]*">([^<]*?)<\/section>/', "\n$1\n", $md);

        $md = preg_replace('/<br\s*\/?>/', "\n\n", $md);
        $md = preg_replace('/<(p|div)(\s+id="[^"]*")?(\s+class="[^"]*")?(\s+dir="auto")?(\s+style="[^"]+")?>|<!-- \/?wp:(paragraph|button|buttons|embed) (\{[^}]+\} ?)?-->/', "", $md);
        $md = preg_replace('/<\/(p|div)>/', "\n\n", $md);

        $md = preg_replace('/<a class="[^"]+"/', "<a", $md);
        $md = preg_replace('/<a href="(?:https?:\/\/librairie-?lespassantes\.fr)?([^"]+)"/', "<a href=\"$1\"", $md);
        $md = preg_replace('/\bhttp:\/\//', "https://", $md);

        $md = preg_replace('/<!-- wp:image [^-]*?-->|<!-- \/wp:image -->/', "", $md);
        $md = preg_replace('/<figure[^>]*?>|<\/figure>/', "", $md);
        $md = preg_replace('/<img(?: (?:width|height)="16")+ src="[^"]+" alt="([^"]{1,4})">/', "$1", $md); // emoticon
        $md = preg_replace('/<img src="(?:https?:\/\/librairie-?lespassantes\.fr)?([^"]+)"[^>]*>/', "\n![]($1)\n", $md);
        $md = preg_replace('/<img(?: class="[^"]*")? src="(?:https?:\/\/librairie-?lespassantes\.fr)?([^"]+)"(?: alt="([^"]*)")?[^>]*>/', "\n![$2]($1)\n", $md);

        $md = preg_replace('/<a href="([^"]+)">\s*([^<]+)(\s*)<\/a>/', "[$2]($1)$3", $md);

        $md = preg_replace('/&lt;/', "<", $md);
        $md = preg_replace('/&gt;/', ">", $md);
        $md = preg_replace('/&apos;/', "'", $md);
        $md = preg_replace('/&quot;/', '"', $md);
        $md = preg_replace('/&lsquo;/', '‘', $md);
        $md = preg_replace('/&rsquo;/', '’', $md);
        $md = preg_replace('/&hellip;|&#8230;/', '…', $md);
        $md = preg_replace('/&nbsp;/', ' ', $md);
        $md = preg_replace('/&amp;/', '&', $md);

        $md = preg_replace('/\.\n(?!\n)/', ".  \n", $md); // 2 blanks for line break

        $md = preg_replace('/\n\n\n+/', "\n\n", $md);
        $md = preg_replace('/^\n+|\n+$/', '', $md);

        return $md;
    }
}
