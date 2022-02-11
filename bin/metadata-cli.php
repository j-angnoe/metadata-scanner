<?php

require_once __DIR__ . '/../src/MetadataScanner.php';

class Controller { 
    const NAME = 'Metadata';
    const BIN = 'metadata';

    /**
     * Ls: List the files that the scanner will look for.
     */
    function ls() { 
        // @todo - these must become a setting of sorts.

        $scanner = new MetadataScanner;
        $command = $scanner->createFindCommand('.');

        error_log('Running find command: ' . $command);
        return $this->runCommand($command);
        
    }

    private function runCommand($command) { 
        return  explode("\n", trim(shell_exec($command)));
    }

    /**
     * Search: Display the files that contain metadata.
     */
    function search() { 
        $scanner = new MetadataScanner;
        $baseCommand = $scanner->createFindCommand();

        $command = "$baseCommand | xargs " . $scanner->grepCommand;

        error_log("Running command: $command");
        return $this->runCommand($command);
    }

    /**
     * Scan: all for files for metadata, collect them, and
     * return the resulting metadata.
     * Usage: metadata scan [dir] [dir2] [--relative-to=dir]
     */
    function scan(...$opts) { 
        $exportArg = null;
        if (false !== ($index = array_search('--relative-to', $opts))) {
            $exportArg = $opts[$index + 1];
            $opts = array_slice($opts, 0, $index);
        } 
        if (empty($opts)) { 
            $opts = [null];
        }
        error_log('dirs: ' . print_r($opts, true));
        
        $scanner = new MetadataScanner();
        foreach ($opts as $dir) {
            error_log('Scanning ' . ($dir ?: getcwd()));
            $scanner->scan($dir);
        }
        return $scanner->export($exportArg);
    }

    /**
     * Display this usage information
     */
    function help() {
        echo static::NAME . " usage:\n\n";

        $x = new ReflectionClass($this);
        foreach ($x->getMethods() as $m) { 
            $comment = $m->getDocComment();
            if ($comment) { 
                echo static::BIN . ' ' . $m->getName() . "\n";
                echo str_replace("\n","\n\t", preg_replace("~(/\*+|[ \t]+\*\s|\*+/)~", "", $comment)) . "\n";
            }
        }
    }

    static function dispatch($argv, $controller = null) { 
        $controller = $controller ?: new static;
        
        if (!isset($argv[1]) || preg_match("~(-h|--help|help|-\?)~", $argv[1]) || !method_exists($controller, $argv[1])) {
            $controller->help();

            exit(1);
        }

        $result = call_user_func_array([$controller, $argv[1]], array_slice($argv, 2));
        if (is_array($result)) { 
            echo json_encode($result, JSON_PRETTY_PRINT);
            echo "\n";
        } else if (is_string($result)) { 
            echo $result;
        }
    }
}

Controller::dispatch($argv);


