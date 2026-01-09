<?php

namespace App\Services\Ai;

interface PromptBuilderInterface
{
    public function build(array $ticket): string;
}