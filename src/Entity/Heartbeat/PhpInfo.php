<?php

namespace Shed\Cli\Entity\Heartbeat;

final class PhpInfo implements \JsonSerializable
{
    public function get(): array
    {
        return [
            'version'             => PHP_VERSION,
            'extensions'          => $this->getLoadedExtensions(),
            'fpm_status'          => $this->getFpmStatus(),
            'memory_limit'        => ini_get('memory_limit'),
            'max_execution_time'  => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
        ];
    }

    private function getLoadedExtensions(): array
    {
        return array_values(get_loaded_extensions());
    }

    private function getFpmStatus(): ?array
    {
        if (!extension_loaded('fpm')) {
            return null;
        }

        switch (Os::getType()) {
            case Os::LINUX:
                $status = @file_get_contents('http://localhost/fpm-status');
                if ($status === false) {
                    return null;
                }

                preg_match_all('/^(.+):\s+(.+)$/m', $status, $matches, PREG_SET_ORDER);
                $result = [];
                foreach ($matches as $match) {
                    $result[strtolower(str_replace(' ', '_', $match[1]))] = $match[2];
                }
                return $result;

            default:
                return null;
        }
    }

    public function jsonSerialize(): array
    {
        return $this->get();
    }
} 
