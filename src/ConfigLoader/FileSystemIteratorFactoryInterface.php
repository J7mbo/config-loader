<?php

namespace ConfigLoader;

interface FileSystemIteratorFactoryInterface
{
    public function build($directory);
}