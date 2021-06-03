<?php

namespace Prezly\KubernetesClient\Tests\Utils;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class StderrLogger implements LoggerInterface
{
    use LoggerTrait;

    public function log($level, $message, array $context = [])
    {
        $context = $context ? sprintf("(%s)", json_encode($context)) : '';

        error_log(sprintf("%-11s %s %s", "[$level]", $message, $context));
    }
}
