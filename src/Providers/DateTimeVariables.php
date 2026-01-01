<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Providers;

use Yannelli\PromptPipeline\Contracts\VariableProvider;

class DateTimeVariables implements VariableProvider
{
    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        $now = now();

        return [
            'current_date' => $now->toDateString(),
            'current_time' => $now->toTimeString(),
            'current_datetime' => $now->toDateTimeString(),
            'current_timestamp' => $now->timestamp,
            'current_year' => $now->year,
            'current_month' => $now->month,
            'current_day' => $now->day,
            'current_day_of_week' => $now->dayName,
            'current_timezone' => $now->timezoneName,
        ];
    }
}
