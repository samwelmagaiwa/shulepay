<?php

namespace App\Support;

class NameSearch
{
    /**
     * Requires every whitespace-separated word in $search to match at least
     * one of $columns (LIKE %word%), rather than the whole raw string against
     * a single column. Without this, a query split across columns — e.g.
     * "Juma Tungucha" where "Juma" is the middle_name and "Tungucha" the
     * last_name — never matches, since no single column contains the full
     * two-word phrase.
     */
    public static function apply($query, array $columns, string $search): void
    {
        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words) || empty($columns)) {
            return;
        }

        foreach ($words as $word) {
            $query->where(function ($wordQuery) use ($word, $columns) {
                foreach ($columns as $column) {
                    $wordQuery->orWhere($column, 'like', '%'.$word.'%');
                }
            });
        }
    }
}
