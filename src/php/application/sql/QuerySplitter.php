<?php

namespace dbmigrate\application\sql;

class QuerySplitter
{
    /**
     * @param $sql
     *
     * @return array
     */
    private $sqlCommands = [
        'select', 'insert', 'drop', 'update', 'create', 'alter', 'delete', 'truncate', 'grant', 'comment'
    ];

    public function split($sql)
    {
        // add trailing ; if not exists
        $sql = rtrim(trim($sql), ';') . ';';

        // temporarily replace semicolons in quoted strings with a placeholder *ris_db_migrate_semicolon*
        $sql = preg_replace_callback('/([\'\"])[\s\S]*?\1/', function ($matches) {
            return str_replace(';', 'ris_db_migrate_semicolon', $matches[0]);
            // remove single quotes from comments (can only do if line starts with -- otherwise tbl_process --param will get picked up)
            // so please no single quotes in inline comments or this will fail
        }, preg_replace_callback('/^ *--.*/', function ($matches) {
            return '--' . preg_replace('/\'/', '', $matches[0]);
        }, $sql));
        $sql = preg_replace('/;(' . implode('|', $this->sqlCommands) . ')/i', ";\n$1", $sql);

        // sql blocks matching patterns (order matters)
        $patterns = [
            // find DO .. ; blocks
            '/^ *do[\s\r\n]+?\$\$[\s\S]+?;?[\s\S]*?\$\$;?/im',
            // find CREATE ... FUNCTION ... BEGIN ... END
            '/^ *create[\s\S]+?function[\s\S]+?begin[\s\S]+?end[\s\S]+?\$\$;?/im',
            // find CREATE ... RULE ... newLine x2 or end of file (create rule has to end with 2 line breaks for this to work) It could be done by recursive regex (?R), but I couldn't get it to work
            '/^ *create[\n\r\s]+(or[\n\r\s]+replace[\n\r\s]+)?rule[\s\S]+?;[\n\r\s]+([\n\r]|$)/im',
            // find any other queries until ; char
            '/^ *(' . implode('|', $this->sqlCommands) . ')[\s\S]*?;/im',
        ];
        $queries = [];
        $sqlReplacementSchema = $sql;

        // find query blocks and temporarily replace it with a placeholder *ris_db_migrate_replacement_XX_XX* to then know query order
        foreach ($patterns as $key => $pattern) {
            $sqlReplacementSchema = preg_replace_callback($pattern, function ($matches) use (&$queries, $key) {
                $queries[$key] = $queries[$key] ?? [];
                $queries[$key][] = $matches[0];
                return "\n" . 'ris_db_migrate_replacement_' . $key . '_' . (count($queries[$key]) - 1) . "\n";
            }, $sql);
            $sql = preg_replace($pattern, '', $sqlReplacementSchema);

        }

        $remainsToProcess = '';
        $queriesToProcess = [];
        // iterate placeholders (one per line) and build queries array based on the placeholders
        foreach (explode(PHP_EOL, $sqlReplacementSchema) as $line) {
            if (preg_match('/^ris_db_migrate_replacement_([0-9])+_([0-9]+)$/', trim($line), $matches)) {
                $remainsToProcess = trim($remainsToProcess);
                if ($remainsToProcess && !preg_match('/^[;\s]+$/', $remainsToProcess)) {
                    echo 'String didn\'t match any query pattern. Falling back to the old query splitter for the following string:' . "\n" . $remainsToProcess . "\n";
                    $queriesToProcess = array_merge($queriesToProcess, $this->splitOld($remainsToProcess));
                    $remainsToProcess = '';
                }
                $queriesToProcess[] = $queries[$matches[1]][$matches[2]];
            } elseif (!preg_match('/^--/', trim($line))) {
                $remainsToProcess .= preg_replace('/\s*-{2,}\s*.*<\/?[a-zA-Z0-9_.-]+>/', '', $line) . PHP_EOL;
            }
        }
        $remainsToProcess = trim($remainsToProcess);
        if ($remainsToProcess && !preg_match('/^[;\s]+$/', $remainsToProcess)) {
            echo 'String didn\'t match any query pattern. Falling back to the old query splitter for the following string:' . "\n" . $remainsToProcess . "\n";
            $queriesToProcess = array_merge($queriesToProcess, $this->splitOld($remainsToProcess));
        }
        // trim and remove empty queries
        $queries = array_values(array_filter(array_map('trim', $queriesToProcess), function ($query) {
            // filter out entries only consisting of whitespace and semicolons
            if (preg_match('/^[;\s]+$/', $query)) {
                return false;
            }
            return trim($query);
        }));

        // restore semicolons
        foreach ($queries as $key => $query) {
            $queries[$key] = str_replace('ris_db_migrate_semicolon', ';', $query);
        }
        return $queries;
    }

    public function splitOld($sql)
    {
        $sql = $this->removeCommentsAndSpaces($sql);
        return array_values(array_filter(explode(PHP_EOL . "" . PHP_EOL, $sql)));
    }

    /**
     * @param $sql
     * @return string|string[]|null
     */
    private function removeCommentsAndSpaces($sql)
    {
        $sql = preg_replace('@--.*?\n@mis', '', $sql);
        $sql = preg_replace('@\/\*.*?\*\/@mis', '', $sql);
        /* Trim spaces on blank lines */
        $sql = preg_replace('@^\s*\n@mis', "\n", $sql);
        return $sql;
    }
}
