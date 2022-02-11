# MetadataScanner

Scans directories for files containing `@meta()` annotations and collects this data.
Contains both a binary for command-line usage as well as a small library for inclusion
in projects.

# CLI Usage

```sh
./bin/metadata ls           - list files that are included in the search
./bin/metadata search       - displays matches that occur in said files.
./bin/metadata scan         - collects the data from matches.
```

# Library usage

```
$obj = new \Metadata\Scanner();
$obj->scan('/path/to/project1');
$obj->scan('/path/to/project2');

$metadata = $obj->export();
```
