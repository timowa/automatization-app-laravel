<?php

namespace App\Scenarios;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;

abstract class Scenario
{
    abstract public function type(): ScenarioType;
    abstract public function rules(): array;
    /**
     * @return PublicationTaskType[]
     */
    abstract public function tasks(): array;
}
