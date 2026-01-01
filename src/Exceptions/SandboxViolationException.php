<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class SandboxViolationException extends PromptPipelineException
{
    public static function blockedFilter(string $filter): self
    {
        return new self("Filter '{$filter}' is not allowed in templates.");
    }

    public static function blockedFunction(string $function): self
    {
        return new self("Function '{$function}' is not allowed in templates.");
    }

    public static function blockedTag(string $tag): self
    {
        return new self("Tag '{$tag}' is not allowed in templates.");
    }

    public static function blockedMethod(string $class, string $method): self
    {
        return new self("Method '{$class}::{$method}' is not allowed in templates.");
    }

    public static function blockedProperty(string $class, string $property): self
    {
        return new self("Property '{$class}::{$property}' is not accessible in templates.");
    }
}
