<?php

namespace ConfigLoader;

class FileSystemIteratorFactory implements FileSystemIteratorFactoryInterface
{
    public function build($directory)
    {
        return new \FilesystemIterator($directory);
    }    
}