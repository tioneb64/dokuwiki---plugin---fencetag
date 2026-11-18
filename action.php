<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Parsing\Lexer\Lexer;

/**
 * Fencetag : ```balise attributs ... ``` équivaut à <balise attributs> ... </balise>,
 * pour toutes les balises <...> des plugins de syntaxe (détectées automatiquement) et celles
 * listées dans la configuration. Un bloc dont le « langage » n'est pas une balise reste un bloc de code.
 *
 * La conversion se fait sur le texte avant l'analyse : les plugins ne voient que la forme <balise>.
 */
class action_plugin_fencetag extends ActionPlugin
{
    /** @inheritdoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'BEFORE', $this, 'preprocess', null, 50);
    }

    public function preprocess(Event $event)
    {
        if (!is_string($event->data) || strpos($event->data, '```') === false) return;
        $tags = $this->tags();
        if (!$tags) return;

        // les zones littérales (<code>, <file>, <nowiki>, <html>, <php>, %%...%%) ne sont pas touchées
        $parts = preg_split(
            '#(<(code|file|nowiki|html|php)\b[^>]*>.*?</\2>|%%.*?%%|(?m:^~{3,}[^\n]*\n.*?\n~{3,}[ \t]*$))#is',
            $event->data,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        // avec DELIM_CAPTURE : texte, zone littérale, nom de la balise (ou rien pour %%), texte, ...
        $out = '';
        foreach ($parts as $i => $part) {
            if ($i % 3 === 0) {
                $out .= $this->convert($part, $tags);
            } elseif ($i % 3 === 1) {
                $out .= $part;
            }
        }
        $event->data = $out;
    }

    /** Convertit les blocs clôturés dont le nom est une balise connue */
    protected function convert(string $text, array $tags): string
    {
        // toutes les clôtures sont parcourues : le contenu d'un bloc de code n'est jamais converti
        return preg_replace_callback(
            '/^(`{3,})[ \t]*([A-Za-z][\w-]*)([^\n`]*)\n(.*?)\n\1`*[ \t]*(?=\n|$)/ms',
            function ($m) use ($tags) {
                if (!isset($tags[strtolower($m[2])])) return $m[0];
                return '<' . $m[2] . rtrim($m[3]) . ">\n" . $m[4] . "\n</" . $m[2] . '>';
            },
            $text
        );
    }

    /** @return array<string,true> balises convertibles (minuscules) */
    protected function tags(): array
    {
        static $memo = null;
        if ($memo !== null) return $memo;

        $list = [];
        if ($this->getConf('auto')) $list = $this->detected();
        foreach (explode(',', strtolower((string)$this->getConf('extra'))) as $t) {
            if (trim($t) !== '') $list[trim($t)] = true;
        }
        foreach (explode(',', strtolower((string)$this->getConf('exclude'))) as $t) {
            unset($list[trim($t)]);
        }
        return $memo = $list;
    }

    /**
     * Balises déclarées par les plugins de syntaxe : leurs motifs « <balise... » sont relevés
     * en faisant tourner connectTo() sur un analyseur factice. Résultat gardé en cache tant que
     * la liste ou les fichiers des plugins ne changent pas.
     *
     * @return array<string,true>
     */
    protected function detected(): array
    {
        global $conf;
        $names = plugin_list('syntax');
        sort($names);
        $stamp = [];
        foreach ($names as $n) {
            $dir = DOKU_PLUGIN . explode('_', $n)[0];
            $stamp[] = $n . (int)@filemtime($dir . '/syntax.php') . (int)@filemtime($dir . '/syntax');
        }
        $key = md5(implode('|', $stamp) . (int)@filemtime(DOKU_CONF . 'local.php'));
        $file = $conf['cachedir'] . '/fencetag.json';
        $cached = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        if (is_array($cached) && ($cached['key'] ?? '') === $key) return array_fill_keys($cached['tags'], true);

        $tags = [];
        foreach ($names as $n) {
            try {
                $plugin = plugin_load('syntax', $n);
                if (!$plugin) continue;
                $probe = new class (new \stdClass()) extends Lexer {
                    public $found = [];

                    public function addPattern($pattern, $mode = 'accept')
                    {
                        $this->found[] = $pattern;
                    }

                    public function addEntryPattern($pattern, $mode, $new_mode)
                    {
                        $this->found[] = $pattern;
                    }

                    public function addExitPattern($pattern, $mode)
                    {
                    }

                    public function addSpecialPattern($pattern, $mode, $special)
                    {
                        $this->found[] = $pattern;
                    }
                };
                // la propriété Lexer est protégée : on la renseigne par réflexion
                $prop = new \ReflectionProperty($plugin, 'Lexer');
                $prop->setAccessible(true);
                $prop->setValue($plugin, $probe);
                $plugin->connectTo('base');
                foreach ($probe->found as $pattern) {
                    if (preg_match('/^<(?:\((?:\?:)?([A-Za-z][\w|-]*)\)|([A-Za-z][\w-]*))/', $pattern, $mm)) {
                        foreach (explode('|', $mm[1] !== '' ? $mm[1] : $mm[2]) as $t) {
                            if (strlen($t) < 2) continue;
                            $tags[strtolower($t)] = true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // un plugin qui ne se laisse pas sonder est simplement ignoré
            }
        }
        io_saveFile($file, json_encode(['key' => $key, 'tags' => array_keys($tags)]));
        return $tags;
    }
}
