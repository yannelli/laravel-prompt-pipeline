<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Providers;

use Yannelli\PromptPipeline\Contracts\VariableProvider;

class EnvironmentVariables implements VariableProvider
{
    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
        ];
    }
}
