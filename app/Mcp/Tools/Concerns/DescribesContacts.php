<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * JSON schema for a booking client contact, validated by
 * {@see \App\Services\BandApi\BandBookingsService::contactRules()}.
 */
trait DescribesContacts
{
    /**
     * @return array<string, Type>
     */
    protected function contactProperties(JsonSchema $schema, bool $requireNameAndEmail): array
    {
        $name = $schema->string()->max(255)->description('Contact\'s full name.');
        $email = $schema->string()->format('email')->max(255)->description('Contact\'s email. The band\'s existing contact with this email is reused (its name and phone are left as they are); otherwise a new contact is created.');

        return [
            'name' => $requireNameAndEmail ? $name->required() : $name,
            'email' => $requireNameAndEmail ? $email->required() : $email,
            'phone' => $schema->string()->max(255)->nullable()->description('Phone number (optional).'),
            'role' => $schema->string()->max(255)->nullable()->description('Role on this booking, e.g. "Bride", "Planner".'),
            'is_primary' => $schema->boolean()->nullable()->description('Primary contact for the booking. Defaults to true when the booking has no other primary contact.'),
        ];
    }

    protected function contactSchema(JsonSchema $schema): Type
    {
        return $schema->object($this->contactProperties($schema, true));
    }
}
