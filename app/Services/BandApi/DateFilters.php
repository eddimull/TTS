<?php

namespace App\Services\BandApi;

/**
 * The date filter set shared by the developer API (`/booked-dates`, `/events`)
 * and the MCP server's `check_availability` / `list_events` tools.
 *
 * Filters are the validated input array: only keys the caller actually sent are
 * present, in the order of {@see rules()}, which is also the order they are
 * echoed back in the response's `filters` object.
 */
final class DateFilters
{
    /** Filter key => SQL comparison operator against the event date. */
    public const OPERATORS = [
        'date' => '=',
        'from' => '>=',
        'to' => '<=',
        'before' => '<',
        'after' => '>',
    ];

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return array_fill_keys(array_keys(self::OPERATORS), 'nullable|date_format:Y-m-d');
    }

    /**
     * The filters that were supplied, as [operator, value] pairs in rule order.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{0: string, 1: mixed}>
     */
    public static function constraints(array $filters): array
    {
        $constraints = [];

        foreach (self::OPERATORS as $key => $operator) {
            if (array_key_exists($key, $filters)) {
                $constraints[] = [$operator, $filters[$key]];
            }
        }

        return $constraints;
    }

    /**
     * The `filters` echo for a response (empty when nothing was supplied).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function applied(array $filters): array
    {
        return array_intersect_key($filters, self::OPERATORS);
    }
}
