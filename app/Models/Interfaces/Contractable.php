<?php

namespace App\Models\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Contractable
{
    public function getContractRecipients(): array;
    public function getContractName(): string;
    public function contract(): MorphOne;
}
