<?php

class MetadataScanner { 
    public $excludeDirectories = ['node_modules','build','dist','public', 'vendor','.cache', 'cache', '.git', 'tmp'];
    //public $excludeExtensions = ['docx','csv','txt','md','json','xml','lock','gif','png','jpg','jpeg','ttf','yml'];
    public $includeExtensions = ['php','js','html'];
    public $grepCommand = 'grep -E "@meta\s*\(" -I';
    public $collectedData = null;

    function __construct($directory = '.') { 
        $this->scanDirectory = $directory;
    }

    function createFindCommand($directory = '.', $extraPipes = null) { 
        $excludeDirs = $this->excludeDirectories;

        $findCommand = "find $directory -type d \( " . join(' -o ', array_map(fn($n) => '-name '.$n, $excludeDirs)) ." \) -prune -false -o -type f -not -perm -111";

        if ($this->excludeExtensions ?? false) { 
            $findCommand .= ' | grep -i -v -E "\.('.join('|',$this->excludeExtensions).')$"';
        }
        if ($this->includeExtensions ?? false) { 
            $findCommand .= ' | grep -i -E "\.('.join('|',$this->includeExtensions).')$"';
        }

        if ($extraPipes) { 
            $findCommand .= ' | ' . ltrim($extraPipes, ' |');
        }

        return $findCommand;
    }

    function runCommand($command) { 
        return  explode("\n", trim(shell_exec($command)));
    }

    function scan($filesOrDirectory = null) { 
        if ($filesOrDirectory) { 
            if (is_string($filesOrDirectory)) { 
                $matchingFiles = $this->runCommand($this->createFindCommand($filesOrDirectory, 'xargs ' . $this->grepCommand . ' -l | sort | uniq'));
            } else {
                $matchingFiles = $filesOrDirectory;
            }
        } else { 
            $matchingFiles = $this->runCommand($this->createFindCommand($this->scanDirectory, 'xargs ' . $this->grepCommand . ' -l | sort | uniq'));
        } 

        $collectedData = $this->collectedData ?: [];
        foreach ($matchingFiles as $file) {
            // error_log('Scanning ' . $file);
            $collectedData = $this->extractFromFile($file, $collectedData);
        }

        $this->collectedData = $collectedData;
        return true;
    }

    function makePathsRelativeTo($basePath, $path) { 
        // basePath = /var/www/
        // path = /var/www/html

        // echo "PATH = $path\n";

        // Simple unit/
        if (strpos($path, $basePath) === 0) { 
            return substr($path, strlen(rtrim($basePath,'/')) + 1);
        }
        $basePathExploded = ['/'];
        foreach (array_slice(explode('/', trim($basePath,'/')), 0, -1) as $sub) { 
            $basePathExploded[] = end($basePathExploded) . $sub . DIRECTORY_SEPARATOR;
        }
        foreach (array_reverse($basePathExploded) as $index => $parent) { 
            // echo "SEE $parent\n";
            if (strpos($path, $parent) === 0) { 
                return str_repeat('../', $index+1) . substr($path, strlen(rtrim($parent,'/')) + 1);
            }
        }

        throw new \Exception(__METHOD__ . ' failed on `'.$path . '` relative to `'.$basePath.'`');
    }

    function export($relativeAgainst = null) { 
        $relativeAgainst = $relativeAgainst ?: getcwd();
        if (substr($relativeAgainst,0,1) === DIRECTORY_SEPARATOR) { 
            // ok. 
        } else { 
            $relativeAgainst = realpath($relativeAgainst);
            if (!$relativeAgainst) {
                throw new \Exception(func_get_arg(0) . ' could not be converted to absolute path');
            }
        }
        
        if ($this->collectedData === null) { 
            $this->scan();
        }

        foreach ($this->collectedData as $key => &$items) { 
            if (is_array($items)) { 
                foreach ($items as &$item) { 
                    if (isset($item['source'])) { 
                        $item['source'] = $this->makePathsRelativeTo(realpath($relativeAgainst), $item['source']);
                    }
                }
            }
        }
        return $this->collectedData;
    }

    function extractFromFile($file, $collectedData = []) { 
        $content = file_get_contents($file);

        preg_replace_callback(
            '~(@meta\s*\(.+?\)\s*;)~s',
            function ($match) use (&$collectedData, $file) {
                ob_start();
                $meta = function ($arg, $arg2 = null) {
                    if (is_string($arg)) {
                        if ($arg2 && !is_array($arg2)) {
                            $arg2 = [$arg2];
                        }
                        return [$arg => $arg2];
                    }
                    return $arg;
                };
                try {
                    $code = str_replace('@meta', '@$meta', $match[1]);
                    $data = eval('return ' . $code);
                    ob_end_clean();
                } catch (\Throwable $e) {
                    echo "Parse error in $file\n";
                    echo $code;
                    exit;
                }

                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        $value = compact('value');
                    }
                    $collectedData[$key] = $collectedData[$key] ?? [];
                    $value['source'] = realpath($file);
                    $collectedData[$key][] = $value;
                }
            },
            $content
        );
        return $collectedData;
    }
}